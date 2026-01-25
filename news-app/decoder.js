const { chromium } = require('playwright');
const axios = require('axios');
const xml2js = require('xml2js');
const cheerio = require('cheerio');
const fs = require('fs');

const args = process.argv.slice(2);
const rssUrl = args[0];
const excludeFlagIndex = args.indexOf('--exclude');
let excludeFile = null;

if (excludeFlagIndex !== -1 && args[excludeFlagIndex + 1]) {
    excludeFile = args[excludeFlagIndex + 1];
}

if (!rssUrl) process.exit(1);

// Load Exclusions
let excluded = new Set();
if (excludeFile && fs.existsSync(excludeFile)) {
    try {
        const fileContent = fs.readFileSync(excludeFile, 'utf8');
        const json = JSON.parse(fileContent);
        if (Array.isArray(json)) {
            excluded = new Set(json);
        }
    } catch (e) {
        console.error("Failed to load exclude file:", e);
    }
}

async function resolveUrl(page, url, retryCount = 0) {
    try {
        // First try with domcontentloaded
        await page.goto(url, { waitUntil: retryCount === 0 ? 'domcontentloaded' : 'networkidle', timeout: 20000 });
        let finalUrl = page.url();
        
        let attempts = 0;
        while ((finalUrl.includes('news.google.com') || finalUrl.includes('consent.google.com')) && attempts < 10) {
            await page.waitForTimeout(1000);
            finalUrl = page.url();
            if (finalUrl.includes('consent.google.com')) {
                try { await page.click('button[aria-label="Accept all"]', { timeout: 1000 }); } catch (e) {}
                try { await page.click('form[action*="consent"] button', { timeout: 1000 }); } catch (e) {}
            }
            attempts++;
        }
        
        // Final validation: If still a google link or exactly the same as input, it failed to decode
        if (finalUrl.includes('news.google.com') || finalUrl.includes('consent.google.com') || finalUrl === url) {
            if (retryCount < 1) {
                return await resolveUrl(page, url, retryCount + 1);
            }
            return null;
        }

        return finalUrl;
    } catch (e) {
        if (retryCount < 1) return await resolveUrl(page, url, retryCount + 1);
        return null;
    }
}

(async () => {
    try {
        const response = await axios.get(rssUrl);
        const parser = new xml2js.Parser();
        const result = await parser.parseStringPromise(response.data);
        
        if (!result.rss?.channel?.[0]?.item) return;

        const items = result.rss.channel[0].item;

        // 1. Initial Filtering & Counting
        const tasks = [];
        let totalFound = 0;
        let alreadyExcluded = 0;

        for (const item of items) {
            const guid = item.guid?.[0]?._ || item.guid?.[0];
            const mainLink = item.link?.[0];
            totalFound++;

            if (excluded.has(guid) || excluded.has(mainLink)) {
                alreadyExcluded++;
                continue; 
            }
            tasks.push(item);
        }

        console.log(JSON.stringify({ 
            event: 'status', 
            message: `RSS check complete: ${totalFound} clusters found, ${alreadyExcluded} already decoded, ${tasks.length} new clusters to process.` 
        }));

        if (tasks.length === 0) {
            process.exit(0);
        }

        const browser = await chromium.launch({ headless: true });

        const processItem = async (item) => {
            const context = await browser.newContext(); 
            try {
                const guid = item.guid?.[0]?._ || item.guid?.[0];
                const mainTitle = item.title?.[0];
                const mainLink = item.link?.[0];
                const description = item.description?.[0];
                const pubDate = item.pubDate?.[0];
                const xmlSource = item.source?.[0]?._;
                const xmlSourceUrl = item.source?.[0]?.$.url;

                const packet = {
                    guid: guid,
                    pubDate: pubDate,
                    main: {
                        title: mainTitle,
                        original_url: mainLink,
                        decoded_url: null,
                        source: xmlSource,
                        source_url: xmlSourceUrl,
                        skipped: false
                    },
                    related: []
                };

                // 2. Parse Description for Related & Main Source
                if (description) {
                    const $ = cheerio.load(description);
                    $('li').each((j, el) => {
                        const link = $(el).find('a');
                        const url = link.attr('href');
                        const title = link.text();
                        const source = $(el).find('font').text();

                        if (url === mainLink) {
                            if (!packet.main.source && source) {
                                packet.main.source = source;
                            }
                        } else if (url) {
                            packet.related.push({
                                title: title,
                                original_url: url,
                                decoded_url: null,
                                source: source,
                                skipped: false
                            });
                        }
                    });
                }

                // 3. Resolve URLs
                const page = await context.newPage();
                packet.main.decoded_url = await resolveUrl(page, packet.main.original_url);

                // Resolve Related
                for (let r of packet.related) {
                    r.decoded_url = await resolveUrl(page, r.original_url);
                }

                await page.close();
                console.log(JSON.stringify(packet));
            } catch (err) {
                // Silently skip failed items
            } finally {
                await context.close();
            }
        };

        // 2. Worker Pool Pattern (Reduced Concurrency for stability)
        const CONCURRENCY = 10;
        const queue = [...tasks];
        const workers = Array(Math.min(CONCURRENCY, queue.length)).fill(0).map(async () => {
            while (queue.length > 0) {
                const item = queue.shift();
                if (item) await processItem(item);
            }
        });

        await Promise.all(workers);
        await browser.close();

    } catch (e) {
        console.error(e);
        process.exit(1);
    }
})();