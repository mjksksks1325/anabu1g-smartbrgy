import fs from 'node:fs';
import path from 'node:path';
import { staffSignIn, staffSignInWithTwoFactor, residentSignIn } from '../lib/session.mjs';

export const name = 'Close and cancel controls across staff and resident portals';

export async function run({ env, open, check, artifacts }) {
    const page = await open();
    await staffSignInWithTwoFactor(page, env, 'admin@test.invalid');
    const inventory = [];
    const checkedDesktop = new Set();

    async function classicDialogs(label, mobile = false, onlyNew = false) {
        const modals = await page.evaluate(`Array.from(document.querySelectorAll('.modal-overlay[id]')).filter(el => el.querySelector('.modal-close')).map(el => ({ id: el.id, buttons: Array.from(el.querySelectorAll('button')).filter(button => /closeModal\\(/.test(button.getAttribute('onclick') || '') && !/openModal\\(/.test(button.getAttribute('onclick') || '')).map((button, index) => { button.dataset.closeAudit = el.id + '-' + index; return { selector: '[data-close-audit="' + button.dataset.closeAudit + '"]', label: button.textContent.trim() || button.getAttribute('aria-label') }; }) }))`);
        for (const modal of modals) {
            if (onlyNew && checkedDesktop.has(modal.id) && !(label === '/admin/audit' && modal.id === 'modal-audit-detail')) continue;
            if (!mobile) checkedDesktop.add(modal.id);
            for (const button of modal.buttons) {
                const test = `${label}${mobile ? ' phone' : ''}: ${modal.id} ${button.label}`;
                try {
                    await page.evaluate(`openModal(${JSON.stringify(modal.id)})`);
                    await page.click(button.selector);
                    const state = await page.evaluate(`({ closed: !document.getElementById(${JSON.stringify(modal.id)}).classList.contains('show'), unlocked: !document.getElementById('app')?.inert && !document.body.classList.contains('modal-open') })`);
                    check(test, state.closed && state.unlocked, JSON.stringify(state));
                    inventory.push({ test, ...state });
                    if (!state.closed || !state.unlocked) await page.screenshot(path.join(artifacts, `${modal.id}-${mobile ? 'phone' : 'desktop'}-failed.png`));
                } catch (error) {
                    check(test, false, error.message);
                } finally {
                    await page.evaluate(`closeModal(${JSON.stringify(modal.id)})`);
                }
            }
        }
        return modals.length;
    }

    async function fluxDialogs(label) {
        const dialogs = await page.evaluate(`Array.from(document.querySelectorAll('ui-modal > dialog[data-modal]')).filter(el => el.querySelector('[data-flux-modal-close] button')).map(el => ({ name: el.dataset.modal, buttons: Array.from(el.querySelectorAll('[data-flux-modal-close] button, [data-announcement-create-close], [data-announcement-archive-cancel], [data-folder-archive-cancel]')).map((button, index) => { button.dataset.closeAudit = el.dataset.modal + '-' + index; return { selector: '[data-close-audit="' + button.dataset.closeAudit + '"]', label: button.getAttribute('aria-label') || button.textContent.trim() }; }) }))`);
        for (const dialog of dialogs) {
            for (const button of dialog.buttons) {
                const test = `${label}: ${dialog.name} ${button.label}`;
                try {
                    await page.evaluate(`document.querySelector('dialog[data-modal="${dialog.name}"]').showModal()`);
                    await page.sleep(180);
                    await page.click(button.selector);
                    await page.sleep(250);
                    const closed = await page.evaluate(`!document.querySelector('dialog[data-modal="${dialog.name}"]').open`);
                    check(test, closed);
                    inventory.push({ test, closed });
                    if (!closed) await page.screenshot(path.join(artifacts, `${dialog.name}-failed.png`));
                } catch (error) { check(test, false, error.message); }
                finally { await page.evaluate(`document.querySelector('dialog[data-modal="${dialog.name}"]')?.close()`); }
            }
        }
        return dialogs.length;
    }

    async function nativeStaffDialogs(label) {
        for (const selector of ['#logout-dialog button.personnel-logout-close', '#logout-dialog form [data-logout-cancel]', '#personnel-password-confirmation [data-confirm-cancel]']) {
            if (!await page.evaluate(`!!document.querySelector(${JSON.stringify(selector)})`)) continue;
            const id = selector.split(' ')[0];
            await page.evaluate(`document.querySelector(${JSON.stringify(id)}).showModal()`);
            await page.click(selector);
            const closed = await page.evaluate(`!document.querySelector(${JSON.stringify(id)}).open`);
            check(`${label}: ${selector}`, closed);
            if (!closed) await page.evaluate(`document.querySelector(${JSON.stringify(id)}).close()`);
        }
    }

    await page.goto('/staff');
    await page.waitFor('typeof openModal === "function" && !!document.querySelector(".modal-close")');
    check('the dashboard exposes close controls to audit', (await classicDialogs('Super Admin')) > 0);
    await page.viewport(320, 700, true);
    await classicDialogs('Super Admin', true);
    await page.viewport(1280, 900);
    await nativeStaffDialogs('Workspace');
    await page.evaluate('toggleNotifPanel(true)');
    await page.click('.notif-panel-close');
    check('the notifications X closes the panel and updates its toggle', await page.evaluate('!document.querySelector("#notif-panel").classList.contains("open") && document.querySelector("#notif-chip").getAttribute("aria-expanded") === "false"'));

    // Closing a confirmation over another dialog keeps the parent usable; the final X restores the workspace.
    await page.evaluate(`openModal('modal-resident'); openModal('modal-confirm-action')`);
    await page.click('#modal-confirm-action .modal-close');
    check('the confirmation X preserves the parent dialog', await page.evaluate('document.querySelector("#modal-resident").classList.contains("show") && !document.querySelector("#modal-resident").inert && document.getElementById("app").inert'));
    await page.click('#modal-resident .modal-close');
    check('closing the remaining dialog restores the workspace', await page.evaluate('!document.getElementById("app").inert && !document.body.classList.contains("modal-open")'));

    for (const route of ['/admin/settings', '/staff/residents', '/staff/incidents', '/staff/voters', '/staff/document-requests', '/staff/request-eligibility', '/admin/users', '/admin/audit', '/staff/demographics']) {
        await page.goto(route);
        if (await page.evaluate('!!document.getElementById("app")')) await classicDialogs(route, false, true);
    }

    for (const route of ['/staff/announcements', '/staff/resident-applications', '/admin/employee-cabinet-access', '/admin/smart-cabinet', '/staff/rfid-file-tracking']) {
        await page.goto(route);
        check(`${route}: page loads without an error`, await page.evaluate('!document.title.match(/(not found|server error)/i) && !!document.querySelector(".workspace")'));
        await fluxDialogs(route);
        await nativeStaffDialogs(route);
        await page.viewport(320, 700, true);
        await fluxDialogs(`${route} phone`);
        await page.viewport(1280, 900);
    }

    // An X must still work after the server returns a validation error and reopens a form.
    await page.goto('/staff/announcements');
    await page.submit(`(() => {
        const form = document.querySelector('[data-announcement-create-dialog] form');
        form.querySelector('[name=title]').value = '';
        form.querySelector('[name=body]').value = 'Invalid draft';
        HTMLFormElement.prototype.submit.call(form);
    })()`);
    await page.waitFor('document.querySelector("[data-announcement-create-dialog] dialog[open] [aria-invalid=true]")');
    await fluxDialogs('Announcement after validation error');

    // Exercise a real sidebar page swap as well as full loads: close handlers must survive navigation.
    for (const destination of ['/admin', '/staff/announcements']) {
        const linkFound = await page.evaluate(`(() => {
            const link = [...document.querySelectorAll('a.nav-item[href]')].find(link => new URL(link.href).pathname === ${JSON.stringify(destination)});
            if (link) link.dataset.closeAuditNavigation = 'true';
            return !!link;
        })()`);
        if (!linkFound) throw new Error(`No sidebar link for ${destination}`);
        await page.click('[data-close-audit-navigation]');
        await page.waitFor(`location.pathname === ${JSON.stringify(destination)} && !document.querySelector('[data-personnel-navigation-cover]')`, 20000);
        await page.sleep(500);
        if (destination === '/admin') {
            await page.evaluate(`openModal('modal-audit-detail')`);
            await page.click('#modal-audit-detail .modal-close');
            check('Audit details X works after a sidebar page swap', await page.evaluate('!document.querySelector("#modal-audit-detail").classList.contains("show") && !document.getElementById("app").inert'));
        } else {
            await fluxDialogs('Announcement after sidebar page swap');
        }
    }

    await page.close();

    const optionalSecurity = await open();
    await staffSignIn(optionalSecurity, 'clerk@test.invalid', env.seed.staffPassword);
    await optionalSecurity.goto('/staff');
    await optionalSecurity.evaluate(`fetch('/user/confirm-password', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ password: ${JSON.stringify(env.seed.staffPassword)} }) })`);
    await optionalSecurity.goto('/staff/settings/security');
    await optionalSecurity.click('button[wire\\:click="enable"]');
    await optionalSecurity.waitFor('document.querySelector("dialog[open]")');
    await optionalSecurity.click('dialog[open] [data-flux-modal-close] button');
    await optionalSecurity.waitFor('!document.querySelector("dialog[open]")');
    check('optional 2FA setup X closes the dialog without confirming enrollment', await optionalSecurity.evaluate('!document.querySelector("dialog[open]")'));
    await optionalSecurity.close();
    if (process.env.CLOSE_BUTTON_AUDIT === '1') {
        for (const email of ['captain@test.invalid', 'secretary@test.invalid']) {
            const role = await open();
            await staffSignInWithTwoFactor(role, env, email);
            await role.goto('/staff');
            const buttons = await role.evaluate(`Array.from(document.querySelectorAll('.modal-close')).map((el, i) => { el.dataset.roleClose = i; return { id: el.closest('.modal-overlay').id, selector: '[data-role-close="' + i + '"]' }; })`);
            for (const button of buttons) {
                await role.evaluate(`openModal(${JSON.stringify(button.id)})`);
                await role.click(button.selector);
                const closed = await role.evaluate(`!document.getElementById(${JSON.stringify(button.id)}).classList.contains('show') && !document.getElementById('app').inert`);
                check(`${email}: ${button.id} X`, closed);
                if (!closed) await role.evaluate(`closeModal(${JSON.stringify(button.id)})`);
            }
            await role.close();
        }
    }

    const resident = await open();
    await residentSignIn(resident, 'resident@test.invalid', env.seed.residentPassword);
    for (const route of ['/portal/dashboard', '/portal/profile', '/portal/request', '/portal/my-account', '/portal/account', '/portal/account/requests/REQ-BROWSER-READY']) {
        await resident.goto(route);
        if (!await resident.evaluate('!!document.querySelector("#resident-logout-dialog")')) {
            check(`${route}: logout confirmation is available`, false);
            continue;
        }
        await resident.evaluate('document.querySelector("#resident-logout-dialog").showModal()');
        await resident.click('[data-resident-cancel]');
        check(`${route}: Cancel closes resident logout`, await resident.evaluate('!document.querySelector("#resident-logout-dialog").open'));
    }
    await resident.click('[data-account-menu-button]');
    await resident.key('Escape');
    check('Escape closes the resident account menu', await resident.evaluate('document.querySelector("[data-account-menu-button]").getAttribute("aria-expanded") === "false"'));
    await resident.close();

    const publicPage = await open();
    await publicPage.goto('/portal/register/new-resident');
    await publicPage.waitFor('document.querySelector("#privacy-notice").matches(":modal")');
    await publicPage.submit('document.querySelector("[data-privacy-notice-cancel]").click()');
    check('privacy notice Cancel leaves the application without accepting consent', await publicPage.evaluate('location.pathname === "/portal/register" && !document.querySelector("#privacy-notice")'));
    await publicPage.viewport(320, 700, true);
    await publicPage.click('[data-menu-toggle]');
    await publicPage.click('[data-menu-toggle]');
    check('the resident phone menu closes with its Close toggle', await publicPage.evaluate('document.querySelector("[data-menu-toggle]").getAttribute("aria-expanded") === "false"'));
    await publicPage.close();
    fs.writeFileSync(path.join(artifacts, 'close-control-inventory.json'), JSON.stringify(inventory, null, 2));
}
