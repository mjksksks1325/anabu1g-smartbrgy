// A confirmed resident requests a Barangay Clearance, sees the confirmation with its reference number, and tracks it.
import { residentSignIn } from '../lib/session.mjs';

export const name = 'Document request, confirmation and status tracking';

export async function run({ env, open, check }) {
    const page = await open();
    await residentSignIn(page, 'resident@test.invalid', env.seed.residentPassword);
    await page.goto('/portal/request');
    await page.evaluate(`(() => { const terms = document.getElementById('tnc-agree'); terms.disabled = false; terms.checked = true; terms.dispatchEvent(new Event('change', { bubbles: true })); proceedFromTerms(); })()`);
    await page.sleep(300);
    await page.evaluate(`(() => { selectDoc('BC', document.querySelector('[onclick*="selectDoc(\\'BC\\'"]')); proceedFromDoc(); })()`);
    await page.sleep(300);
    await page.evaluate(`(() => { const purpose = document.getElementById('f-purpose'); purpose.value = 'Employment requirement'; purpose.dispatchEvent(new Event('input', { bubbles: true })); submitRequest(); })()`);
    await page.waitFor(`document.getElementById('screen-confirm').classList.contains('active') && document.getElementById('conf-code').textContent.trim() !== ''`, 20000);
    const confirmation = await page.evaluate(`(() => { const code = document.getElementById('conf-code'); return { code: code.textContent.trim(), size: parseFloat(getComputedStyle(code).fontSize), copy: !!document.querySelector('#screen-confirm .copy-code-button') }; })()`);
    check('the request is submitted and its reference number is the most prominent text', /^REQ-\d{4}-/.test(confirmation.code) && confirmation.size >= 26 && confirmation.copy, JSON.stringify(confirmation));
    const stored = env.query('select status, resident_id from document_requests where reference_code = ?', confirmation.code)[0];
    check('the request is stored as pending for this resident', stored?.status === 'pending' && Number(stored.resident_id) === env.seed.complete.residentId, JSON.stringify(stored));

    await page.goto('/portal/account');
    check('My requests lists it', await page.evaluate(`document.body.innerText.includes(${JSON.stringify(confirmation.code)})`));
    const status = await page.evaluate(`fetch('/portal/request/' + ${JSON.stringify(confirmation.code)}, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(async r => ({ status: r.status, body: await r.json() }))`);
    check('its status can be tracked by its owner', status.status === 200 && JSON.stringify(status.body).includes('pending'), JSON.stringify(status).slice(0, 200));
    const other = await page.evaluate(`fetch('/portal/request/REQ-BROWSER-READY-NOT-MINE', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(r => r.status)`);
    check('an unknown reference reveals nothing', other === 404, String(other));
    await page.close();
}
