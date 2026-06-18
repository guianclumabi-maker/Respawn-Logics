const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    console.log('Launching browser...');
    const browser = await puppeteer.launch({ 
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
        defaultViewport: { width: 1440, height: 900 }
    });
    const page = await browser.newPage();
    
    console.log('Navigating to register.php...');
    await page.goto('https://respawn-logics-production.up.railway.app/register.php', { waitUntil: 'networkidle2' });
    
    console.log('Filling out registration form...');
    // Generate a random email to ensure we don't hit duplicate email errors
    const randomEmail = `mockup_${Date.now()}@test.com`;
    await page.type('input[name="company_name"]', 'Mockup Corp');
    await page.type('input[name="full_name"]', 'John Doe');
    await page.type('input[name="email"]', randomEmail);
    await page.type('input[name="password"]', 'password123');
    
    console.log('Submitting registration...');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle0' }),
        page.click('button[type="submit"]')
    ]);
    
    console.log('Successfully registered. Navigating to leave.php...');
    await page.goto('https://respawn-logics-production.up.railway.app/pages/leave.php', { waitUntil: 'networkidle0' });
    
    // Wait for a second to ensure any animations finish
    await new Promise(r => setTimeout(r, 2000));
    
    console.log('Taking screenshot...');
    await page.screenshot({ path: '../assets/images/leave_management_mockup_raw.png' });
    
    await browser.close();
    console.log('Done!');
})();
