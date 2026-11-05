// Run against a local read-only server: node tests/Browser/center-application.mjs
// Every POST and remote API call is mocked; no applications or emails are sent.
import { chromium } from '@playwright/test';
import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';

const origin = process.env.CENTER_TEST_ORIGIN || 'http://127.0.0.1:8000';
const browser = await chromium.launch();
async function dependency(route, file) {
    try {
        return await route.fulfill({ contentType: 'application/javascript', body: await readFile(file, 'utf8') });
    } catch {
        return route.continue();
    }
}
try {
    for (const width of [320, 390, 768, 1440]) {
        const page = await browser.newPage({ viewport: { width, height: 950 }, reducedMotion: 'reduce' });
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        let sends = 0, submissions = 0, addressOffline = false;
        await page.route('**/*', async route => {
            const request = route.request(), url = new URL(request.url());
            if (url.hostname === 'cdn.tailwindcss.com') return dependency(route, '.tools/mobile-tailwind.js');
            if (url.hostname === 'cdn.jsdelivr.net' && url.pathname.includes('alpinejs')) return dependency(route, '.tools/mobile-alpine.js');
            if (request.method() !== 'GET') {
                const body = request.postData() || '';
                if (url.pathname.endsWith('/verification/send')) {
                    sends++;
                    return route.fulfill({ json: { message: 'Verification code sent.' } });
                }
                if (url.pathname.endsWith('/verification/verify')) {
                    const data = JSON.parse(body);
                    return route.fulfill(data.code === '123456' ? { json: { message: 'Email verified.', email: data.email } } : { status: 422, json: { message: 'The verification code is incorrect.' } });
                }
                if (url.pathname.endsWith('/apply')) {
                    submissions++;
                    for (const field of ['business_name', 'region', 'postal_code', 'documents[valid_id]', 'documents[business_registration]', 'terms']) assert.ok(body.includes(`name="${field}"`), field + ' included');
                }
                return route.fulfill({ contentType: 'text/html', body: '<h1>Test submission received</h1>' });
            }
            if (url.hostname === 'psgc.gitlab.io') {
                if (addressOffline) return route.fulfill({ status: 503, body: 'Offline address provider' });
                const data = url.pathname.endsWith('/regions/') ? []
                    : url.pathname.endsWith('/provinces') ? [{ name: 'Test Province', code: '030100000' }]
                    : url.pathname.endsWith('/cities-municipalities') ? [{ name: 'Test Municipality', code: '030101000' }]
                    : url.pathname.endsWith('/barangays') ? [{ name: 'Test Barangay', code: '030101001' }] : [];
                return route.fulfill({ json: data });
            }
            if (url.pathname === '/api/logistics/address/provinces/7/municipalities') return route.fulfill({ json: { municipalities: [{ id: 8, code: '030101000', name: 'Local Municipality' }] } });
            if (url.pathname === '/api/logistics/address/municipalities/8/barangays') return route.fulfill({ json: { barangays: [{ id: 9, code: '030101001', name: 'Local Barangay' }] } });
            if (url.origin !== origin) return route.fulfill({ status: 404, body: '' });
            return route.continue();
        });
        await page.goto(origin + '/logistics/logistics-center/apply');
        const next = page.locator('#wizard-next');
        await next.click();
        await page.getByText('The center name must be at least 2 characters.', { exact: true }).waitFor();
        for (const [id, value] of Object.entries({ business_name: 'Test Parcel Hub', owner_first_name: 'Maria', owner_last_name: 'Santos', sec_number: 'AB123456789', tin: '123-456-789-000', phone: '09171234567', email: 'center@example.test' })) await page.locator('#' + id).fill(value);
        await page.getByRole('button', { name: 'Send verification code', exact: true }).click();
        const resend = page.getByRole('button', { name: /Resend code in/ });
        await resend.waitFor();
        assert.equal(await resend.isDisabled(), true);
        // Editing an unverified address must offer a fresh send for the new email.
        await page.locator('#email').fill('changed@example.test');
        await page.getByRole('button', { name: 'Send verification code', exact: true }).click();
        const code = page.getByRole('textbox', { name: 'Verification code', exact: true });
        await code.fill('999999');
        await page.getByRole('button', { name: 'Verify', exact: true }).click();
        await page.getByText('The verification code is incorrect.', { exact: true }).waitFor();
        await code.fill('123456');
        await page.getByRole('button', { name: 'Verify', exact: true }).click();
        await page.getByText('Email verified', { exact: false }).waitFor();
        assert.equal(sends, 2);
        await next.click();
        await next.click();
        await page.getByText('Select a region.', { exact: true }).waitFor();
        await page.locator('#region').selectOption('030000000');
        const province = page.locator('#province');
        await province.locator('option[value="Test Province"]').waitFor({ state: 'attached' });
        await province.selectOption('Test Province');
        await page.locator('#municipality option[value="Test Municipality"]').waitFor({ state: 'attached' });
        await page.locator('#municipality').selectOption('Test Municipality');
        await page.locator('#barangay option[value="Test Barangay"]').waitFor({ state: 'attached' });
        await page.locator('#barangay').selectOption('Test Barangay');
        await page.locator('#postal_code').fill('1100');
        await next.click();
        await next.click();
        await page.getByText('This document is required.', { exact: true }).first().waitFor();
        await page.locator('#documents_valid_id').setInputFiles({ name: 'script.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('invalid') });
        await page.getByText('Choose a JPG, PNG, WebP, PDF, DOC or DOCX file.', { exact: true }).waitFor();
        await page.locator('#documents_valid_id').setInputFiles({ name: 'large.pdf', mimeType: 'application/pdf', buffer: Buffer.alloc(5 * 1024 * 1024 + 1) });
        await page.getByText('The selected file exceeds 5 MB. Please choose a smaller file.', { exact: true }).waitFor();
        for (const id of ['valid_id', 'business_registration']) await page.locator('#documents_' + id).setInputFiles({ name: id + '.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4 test') });
        await next.click();
        await page.getByRole('heading', { name: 'Review your application.' }).waitFor();
        await page.getByText('Test Parcel Hub', { exact: true }).waitFor();
        assert.equal(await page.locator('#wizard-submit').isDisabled(), true);
        await page.getByRole('button', { name: 'Edit', exact: true }).first().click();
        await page.locator('#business_name').fill('Updated Parcel Hub');
        await next.click(); await next.click(); await next.click();
        await page.getByText('Updated Parcel Hub', { exact: true }).waitFor();
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false);
        await page.locator('[name=terms]').check();
        await page.locator('#wizard-submit').click();
        await page.getByRole('heading', { name: 'Test submission received' }).waitFor();
        assert.equal(submissions, 1);
        await page.goto(origin + '/logistics/logistics-center/application-status');
        await page.locator('#email').fill('changed@example.test');
        await page.locator('[data-send-code]').click();
        await page.getByText('Code sent. Check your inbox, then enter the code below.', { exact: true }).waitFor();
        await page.locator('#status-code').fill('999999');
        await page.getByRole('button', { name: 'Check Status', exact: true }).click();
        await page.getByText('The verification code is incorrect.', { exact: true }).waitFor();
        await page.locator('#status-code').fill('123456');
        await page.getByRole('button', { name: 'Check Status', exact: true }).click();
        await page.getByRole('heading', { name: 'Test submission received' }).waitFor();
        if (width === 1440) {
            await page.goto(origin + '/logistics/logistics-center/apply');
            await page.locator('#email').waitFor();
            addressOffline = true;
            await page.evaluate(() => {
                const province = document.querySelector('#province');
                province.innerHTML = '<option value="">Select a province</option><option value="Local Province" data-id="7" data-region="030000000">Local Province</option>';
                Alpine.$data(document.querySelector('form[x-data]')).setStep(1);
            });
            await page.locator('#region').selectOption('030000000');
            await page.locator('#province option[value="Local Province"]').waitFor({ state: 'attached' });
            await page.locator('#province').selectOption('Local Province');
            await page.locator('#municipality option[value="Local Municipality"]').waitFor({ state: 'attached' });
            await page.locator('#municipality').selectOption('Local Municipality');
            await page.locator('#barangay option[value="Local Barangay"]').waitFor({ state: 'attached' });
            await page.locator('#barangay').selectOption('Local Barangay');
            console.log('PASS local address cascade when external provider fails');
        }
        assert.deepEqual(errors, []);
        console.log(`PASS ${width}px: verification, email change, invalid code, address cascade, validation, uploads, review/edit, consent, submission and status lookup`);
        await page.close();
    }
} finally {
    await browser.close();
}
