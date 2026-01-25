const { chromium } = require('playwright');
const fs = require('fs');

const args = process.argv.slice(2);
const inputJsonFile = args[0];

if (!inputJsonFile || !fs.existsSync(inputJsonFile)) {
    console.error("Input JSON file not found.");
    process.exit(1);
}

const items = JSON.parse(fs.readFileSync(inputJsonFile, 'utf8'));

async function resolveUrl(page, url, retryCount = 0) {
    try {
        await page.goto(url, { waitUntil: retryCount === 0 ? 'domcontentloaded' : 'networkidle', timeout: 25000 });
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
        
        if ((finalUrl.includes('news.google.com') || finalUrl.includes('consent.google.com') || finalUrl === url) && retryCount < 1) {
            return await resolveUrl(page, url, retryCount + 1);
        }

        if (finalUrl.includes('news.google.com') || finalUrl.includes('consent.google.com') || finalUrl === url) {
            return null;
        }

        return finalUrl;
    } catch (e) {
        if (retryCount < 1) return await resolveUrl(page, url, retryCount + 1);
        return null;
    }
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();

    const processItem = async (item) => {
        const page = await context.newPage();
        try {
            const decodedUrl = await resolveUrl(page, item.url);
            console.log(JSON.stringify({
                id: item.id,
                original_url: item.url,
                decoded_url: decodedUrl
            }));
        } catch (err) {
            // Error handling
        } finally {
            await page.close();
        }
    };

    const CONCURRENCY = 10;
    const queue = [...items];
    const workers = Array(Math.min(CONCURRENCY, queue.length)).fill(0).map(async () => {
        while (queue.length > 0) {
            const item = queue.shift();
            if (item) await processItem(item);
        }
    });

    await Promise.all(workers);
    await browser.close();
})();
