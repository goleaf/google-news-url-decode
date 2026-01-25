const { chromium } = require('playwright');
const axios = require('axios');
const xml2js = require('xml2js');
const fs = require('fs');

(async () => {
    const rssUrl = 'https://news.google.com/rss/topics/CAAqJggKIiBDQkFTRWdvSUwyMHZNRGx1YlY4U0FuSjFHZ0pTVlNnQVAB?hl=ru&gl=RU&ceid=RU%3Aru';
    const csvFile = 'decoded_links.csv';

    console.log('Fetching RSS feed...');
    const response = await axios.get(rssUrl);
    const parser = new xml2js.Parser();
    const result = await parser.parseStringPromise(response.data);
    const items = result.rss.channel[0].item;

    console.log(`Found ${items.length} items. Starting browser...`);

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    // Prepare CSV
    const writeStream = fs.createWriteStream(csvFile);
    writeStream.write('\ufeffDecoded Link,Coded Link\n'); // BOM + Headers

    for (let i = 0; i < items.length; i++) {
        const codedLink = items[i].link[0];
        console.log(`[${i+1}/${items.length}] Resolving: ${codedLink}`);

        try {
            // Set a timeout for the navigation
            await page.goto(codedLink, { waitUntil: 'domcontentloaded', timeout: 45000 });
            
            // Wait for redirect
            let finalUrl = page.url();
            let attempts = 0;
            // Increased attempts for slower connections
            while ((finalUrl.includes('news.google.com') || finalUrl.includes('consent.google.com')) && attempts < 30) {
                await page.waitForTimeout(500);
                finalUrl = page.url();
                
                if (finalUrl.includes('consent.google.com')) {
                    try {
                        // Try multiple selector strategies for the consent button
                        await page.click('button[aria-label="Accept all"]', { timeout: 500 });
                    } catch (e) {
                        try {
                            await page.click('form[action*="consent"] button', { timeout: 500 });
                        } catch (e2) {}
                    }
                }
                attempts++;
            }

            // If we are still on google after timeout, assume failure but save what we have
            writeStream.write(`"${finalUrl}","${codedLink}"\n`);
            console.log(` -> Result: ${finalUrl}`);
        } catch (e) {
            console.log(` -> Error: ${e.message}`);
            // Save the original link as fallback in the decoded column so the row isn't empty
            writeStream.write(`"${codedLink}","${codedLink}"\n`);
        }
    }

    await browser.close();
    writeStream.end();
    console.log(`\nDone! Exported to ${csvFile}`);
})();
