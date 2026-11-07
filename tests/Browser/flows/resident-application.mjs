// New-resident applications with real photos of 1.5, 5 and 9.8 MB, the 10 MB limit in the browser and on the server,
// private storage of the optimised photo, and the "file too large" page for a form PHP refuses.
import fs from 'node:fs';
import path from 'node:path';
import { messages } from '../lib/session.mjs';

export const name = 'New-resident application and photo upload';

/** Width and height of a JPEG, read from its frame header. */
function jpegSize(file) {
    const bytes = fs.readFileSync(file);
    for (let at = 2; at < bytes.length - 9;) {
        if (bytes[at] !== 0xff) { at++; continue; }
        const marker = bytes[at + 1];
        const length = bytes.readUInt16BE(at + 2);
        if (marker >= 0xc0 && marker <= 0xc3) return { height: bytes.readUInt16BE(at + 5), width: bytes.readUInt16BE(at + 7) };
        at += 2 + length;
    }
    return null;
}

let attempt = 0;
async function apply({ open, env, photo, bypassBrowserCheck = false }) {
    attempt++;
    const page = await open();
    await page.goto('/portal/register/new-resident');
    const surname = 'Applicant' + 'abcdefghijklmnop'[attempt];
    await page.evaluate(`(() => {
        const dialog = document.querySelector('[data-privacy-notice]');
        const body = dialog.querySelector('[data-privacy-notice-body]');
        body.scrollTop = body.scrollHeight; body.dispatchEvent(new Event('scroll'));
        const box = dialog.querySelector('[data-privacy-notice-checkbox]'); box.checked = true; box.dispatchEvent(new Event('change', { bubbles: true }));
        dialog.querySelector('[data-privacy-notice-continue]').click();
        const set = (id, value) => { const el = document.getElementById(id); el.value = value; el.dispatchEvent(new Event('change', { bubbles: true })); };
        set('application-first_name', 'Testa'); set('application-last_name', ${JSON.stringify(surname)}); set('application-birth', '1990-04-12');
        set('application-gender', 'Female'); set('application-civil_status', 'Single'); set('application-residency_type', 'Renter');
        document.getElementById('application-purok').selectedIndex = 1;
        set('application-address', '12 Mabini St., Anabu I-G'); set('application-email', ${JSON.stringify(surname.toLowerCase() + '@test.invalid')}); set('application-contact_number', '09171234567');
        ${bypassBrowserCheck ? "document.getElementById('application-photo').removeAttribute('data-resident-image-only');" : ''}
    })()`);
    await page.setFile('#application-photo', path.join(env.images, photo));
    const picked = await page.evaluate(`(() => { const input = document.getElementById('application-photo'); const error = document.getElementById('application-photo-client-error'); const preview = document.getElementById('application-photo-preview'); return { files: input.files.length, error: error.hidden ? null : error.textContent.trim(), invalid: input.getAttribute('aria-invalid'), preview: preview.hidden ? 0 : preview.querySelector('img').naturalWidth }; })()`);
    let after = null;
    if (picked.files) {
        await page.submit(`document.querySelector('form.new-resident-form').requestSubmit()`);
        after = await page.evaluate(`({ path: location.pathname, reference: document.querySelector('.new-resident-reference')?.textContent.trim() ?? null })`);
        after.messages = await messages(page);
    }
    await page.close();
    return { picked, after };
}

export async function run({ env, open, check }) {
    for (const [photo, megabytes] of [['photo-1_5mb.jpg', 1.5], ['photo-5mb.jpg', 5], ['photo-9_8mb.jpg', 9.8]]) {
        const { picked, after } = await apply({ open, env, photo });
        check(`${megabytes} MB photo: previewed with no error`, picked.files === 1 && picked.preview > 0 && picked.error === null, JSON.stringify(picked));
        const stored = after?.reference ? env.query('select photo_path from resident_registration_applications where reference = ?', after.reference)[0]?.photo_path : null;
        check(`${megabytes} MB photo: application submitted for review`, /^REG-/.test(after?.reference ?? ''), JSON.stringify(after));
        if (stored) {
            const file = path.join(env.storage, 'app', 'private', stored);
            const size = jpegSize(file);
            check(`${megabytes} MB photo: stored privately as a JPEG of at most 2400 px`, fs.existsSync(file) && !fs.existsSync(path.join(env.storage, 'app', 'public', stored)) && size && Math.max(size.width, size.height) <= 2400 && fs.statSync(file).size < megabytes * 1048576, `${stored} ${JSON.stringify(size)}`);
        }
    }
    const tooLarge = await apply({ open, env, photo: 'photo-11mb.jpg' });
    check('11 MB photo: refused in the browser with a message naming the 10 MB limit', tooLarge.picked.files === 0 && tooLarge.picked.invalid === 'true' && /10 MB/.test(tooLarge.picked.error ?? ''), JSON.stringify(tooLarge.picked));
    const bypass = await apply({ open, env, photo: 'photo-11mb.jpg', bypassBrowserCheck: true });
    check('11 MB photo sent anyway: refused by the server, the form kept', bypass.after?.path === '/portal/register/new-resident' && /10 MB or smaller/.test(bypass.after.messages), JSON.stringify(bypass.after));

    // A form larger than PHP accepts (16 MB here), answered in the chosen language without server details.
    const page = await open();
    await page.goto('/portal/register/new-resident');
    await page.evaluate(`switchPortalLocale('fil')`);
    await page.sleep(500);
    const refused = await page.evaluate(`(async () => { const data = new FormData(); data.set('photo', new Blob([new Uint8Array(17 * 1024 * 1024)], { type: 'image/jpeg' }), 'huge.jpg'); const response = await fetch('/portal/register/new-resident', { method: 'POST', body: data, credentials: 'same-origin' }); return { status: response.status, text: await response.text() }; })()`);
    check('a form over the server limit gets the "file too large" page in Filipino', refused.status === 413 && /Masyadong malaki ang file na ipinadala/.test(refused.text) && /lang="fil"/.test(refused.text), String(refused.status));
    check('that page shows no server details', !/post_max_size|PostTooLargeException|vendor[\\/]laravel/.test(refused.text));
    await page.close();
}
