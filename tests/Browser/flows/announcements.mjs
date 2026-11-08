import fs from 'node:fs';
import path from 'node:path';
import { staffSignInWithTwoFactor } from '../lib/session.mjs';

export const name = 'Multiple announcement images';

export async function run({ env, open, check, artifacts }) {
    const page = await open();
    await staffSignInWithTwoFactor(page, env, 'admin@test.invalid');
    await page.goto('/staff/announcements');
    await page.click('[data-announcement-create-open]');
    await page.waitFor('document.querySelector("[data-announcement-create-dialog] dialog[open]")');
    const files = ['notice.jpg', 'map.jpeg'].map(name => {
        const target = path.join(env.runDir, name);
        fs.copyFileSync(path.resolve('public/images/anabu-logo.jpg'), target);
        return target;
    });
    const rejectDocuments = async (selector) => {
        for (const name of ['notice.pdf', 'notice.docx', 'notice.xlsx', 'notice.txt']) {
            const invalidFile = path.join(env.runDir, name);
            fs.writeFileSync(invalidFile, '%PDF-1.7 This is not an image.');
            const root = await page.send('DOM.getDocument');
            const input = await page.send('DOM.querySelector', { nodeId: root.root.nodeId, selector });
            await page.send('DOM.setFileInputFiles', { nodeId: input.nodeId, files: [files[0], invalidFile] });
            await page.waitFor(`document.querySelector(${JSON.stringify(selector)}).files.length === 0`);
            check(`${selector.includes('create') ? 'create' : 'edit'} rejects ${name} immediately`, await page.evaluate(`(() => {
                const input = document.querySelector(${JSON.stringify(selector)});
                const error = input.form.querySelector('[data-announcement-image-error]');
                return input.getAttribute('aria-invalid') === 'true' && !error.hidden && error.textContent.includes('image files only');
            })()`));
        }
    };
    await rejectDocuments('[data-announcement-create-dialog] input[type=file]');
    const root = await page.send('DOM.getDocument');
    const input = await page.send('DOM.querySelector', { nodeId: root.root.nodeId, selector: '[data-announcement-create-dialog] input[type=file]' });
    await page.send('DOM.setFileInputFiles', { nodeId: input.nodeId, files });
    check('valid selection clears the image error', await page.evaluate('document.querySelector("[data-announcement-create-dialog] [data-announcement-image-error]").hidden'));
    check('the file picker accepts multiple images', await page.evaluate('document.querySelector("[data-announcement-create-dialog] input[type=file]").files.length === 2'));
    await page.submit(`(() => {
        const form = document.querySelector('[data-announcement-create-dialog] form');
        form.querySelector('[name=title]').value = 'Multiple image browser check';
        form.querySelector('[name=body]').value = 'Two images in one announcement.';
        form.querySelector('[name=status]').value = 'published';
        form.requestSubmit();
    })()`);
    check('both images appear in the staff list', await page.evaluate('document.querySelectorAll("[data-announcement-record] img").length === 2'));
    await page.click('[data-announcement-edit-open]');
    await page.waitFor('document.querySelector("[data-announcement-edit-dialog] dialog[open]")');
    await rejectDocuments('[data-announcement-edit-dialog] input[type=file]');
    check('editing offers a separate removal choice for each image', await page.evaluate(`document.querySelectorAll('input[name="remove_images[]"]').length === 2`));
    await page.screenshot(path.join(artifacts, 'announcement-edit-images.png'));
    await page.goto('/portal/announcements');
    await page.waitFor('[...document.querySelectorAll(".portal-announcement-gallery img")].filter(img => img.complete && img.naturalWidth > 0).length === 2');
    await page.screenshot(path.join(artifacts, 'announcement-gallery-desktop.png'));
    await page.viewport(320, 700, true);
    check('the resident gallery fits a small phone', await page.evaluate('document.documentElement.scrollWidth <= innerWidth && document.querySelectorAll(".portal-announcement-gallery img").length === 2'));
    await page.screenshot(path.join(artifacts, 'announcement-gallery-phone.png'));
    await page.close();
}
