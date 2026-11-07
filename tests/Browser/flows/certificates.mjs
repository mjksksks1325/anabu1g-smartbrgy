// Staff preview, issue and print a certificate; its QR code verifies it publicly. QR files go to the suite's storage.
import fs from 'node:fs';
import path from 'node:path';
import { staffSignInWithTwoFactor } from '../lib/session.mjs';

export const name = 'Certificate preview, issuance, printing and QR verification';

export async function run({ env, open, check, artifacts }) {
    const page = await open();
    await staffSignInWithTwoFactor(page, env, 'admin@test.invalid');
    const { id, code } = env.seed.readyRequest;

    const preview = await page.evaluate(`fetch('/staff/document-requests/${id}/certificate-preview', { credentials: 'same-origin' }).then(async r => ({ status: r.status, text: await r.text() }))`);
    check('the preview opens, marked as not issued and not printable', preview.status === 200 && /PREVIEW/.test(preview.text) && /data-printable="false"/.test(preview.text), String(preview.status));
    check('a preview issues nothing', env.query('select count(*) as n from issued_certificates')[0].n === 0);

    await page.goto('/staff/document-requests');
    await page.waitFor(`typeof CERT_REQUESTS !== 'undefined' && CERT_REQUESTS.some(r => r.code === ${JSON.stringify(code)})`, 20000);
    await page.evaluate(`printCert(${JSON.stringify(code)})`);
    await page.sleep(1500);
    check('Print & Release shows the request ready', (await page.evaluate(`document.getElementById('print-release-readiness')?.dataset.state`)) === 'ready');
    const printUrl = await page.evaluate(`(async () => { const tab = { location: {}, close() {} }; const open = window.open; window.open = () => tab; await confirmPrintRelease(); window.open = open; return tab.location.href || null; })()`);
    check('issuing opens the print page', /issued-certificates\/\d+\/print$/.test(printUrl ?? ''), printUrl);
    if (!printUrl) return page.close();

    await page.goto(new URL(printUrl).pathname);
    await page.sleep(1200);
    const layout = await page.evaluate(`(() => {
        const mm = px => Math.round(px * 25.4 / 96 * 10) / 10;
        const sheet = document.querySelector('.sheet').getBoundingClientRect();
        const qr = document.querySelector('.sheet .verification');
        const footer = document.querySelector('.sheet .imus-footer').getBoundingClientRect();
        const box = qr?.getBoundingClientRect();
        return { printable: document.body.dataset.printable, qrLeft: box ? mm(box.left - sheet.left) : null, qrTop: box ? mm(box.top - sheet.top) : null, qrImage: !!qr?.querySelector('img')?.naturalWidth, footerLeft: mm(footer.left - sheet.left) };
    })()`);
    check('the certificate is printable with its QR code in the bottom-left corner and the City of Imus mark in place', layout.printable === 'true' && layout.qrImage && layout.qrLeft === 25.4 && layout.qrTop === 270.9 && layout.footerLeft === 169.9, JSON.stringify(layout));
    const pages = await page.pdf(path.join(artifacts, 'barangay-clearance.pdf'));
    check('it prints on one A4 page', pages === 1, `${pages} page(s)`);

    const issued = env.query('select certificate_number, verification_code, qr_code_path from issued_certificates')[0];
    check('the QR file is written to the suite storage only', Boolean(issued?.qr_code_path) && fs.existsSync(path.join(env.storage, 'app', 'public', issued.qr_code_path.replace(/^\/?storage\//, ''))), issued?.qr_code_path);
    await page.goto(`/verify-certificate/${issued.verification_code}`);
    check('the QR verification page reports the certificate as authentic', (await page.evaluate(`document.querySelector('[data-result]')?.dataset.result`)) === 'authentic');
    await page.goto('/verify-certificate/NOTAREALCODE0000');
    check('a made-up code is not verified', (await page.evaluate(`document.querySelector('[data-result]')?.dataset.result ?? 'none'`)) !== 'authentic');
    await page.close();
}
