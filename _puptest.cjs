const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        headless: 'new',
        ignoreHTTPSErrors: true,
        args: ['--ignore-certificate-errors', '--no-sandbox'],
    });
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push('PAGEERROR: ' + e.message));
    page.on('console', (m) => {
        if (m.type() === 'error') errors.push('CONSOLE.ERROR: ' + m.text());
    });
    page.on('requestfailed', (r) =>
        errors.push('REQFAIL: ' + r.url() + ' ' + (r.failure() && r.failure().errorText))
    );
    try {
        await page.goto('https://olm.test', { waitUntil: 'networkidle2', timeout: 30000 });
    } catch (e) {
        errors.push('GOTO_ERR: ' + e.message);
    }
    await new Promise((r) => setTimeout(r, 1500));
    const i18nErr = errors.filter((e) => /init_runtime_dom_esm_bundler|__VUE_I18N|is not defined/.test(e));
    console.log('=== TOTAL ERRORS: ' + errors.length + ' ===');
    console.log(errors.join('\n') || '(none)');
    console.log('=== I18N-RELATED: ' + i18nErr.length + ' ===');
    console.log(i18nErr.join('\n') || '(none)');
    await browser.close();
    process.exit(0);
})();
