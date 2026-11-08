// Privileged staff must set up two-factor authentication before using the workspace; set-up staff sign in with a code.
import { staffSignIn, staffSignInWithTwoFactor } from '../lib/session.mjs';
import path from 'node:path';

export const name = 'Privileged staff two-factor authentication';

export async function run({ env, open, check, artifacts }) {
    const unenrolled = await open();
    await staffSignIn(unenrolled, 'records@test.invalid', env.seed.staffPassword);
    await unenrolled.goto('/staff/residents');
    const page = await unenrolled.evaluate(`({ path: location.pathname, required: !!document.querySelector('[data-two-factor-required]') })`);
    check('a privileged account without two-factor is sent to its security settings', /settings\/security|confirm-password/.test(page.path), JSON.stringify(page));
    const api = await unenrolled.evaluate(`fetch('/staff/residents', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(async r => ({ status: r.status, body: await r.text() }))`);
    check('its workspace data requests are refused until two-factor is set up', api.status === 403 && /two_factor_required/.test(api.body), String(api.status));
    await unenrolled.goto('/user/confirm-password');
    check('Confirm password is shown before the QR setup and without workspace content', await unenrolled.evaluate('!!document.querySelector(\'input[name="password"]\') && !document.querySelector("[data-two-factor-enrollment-shell], .civic-settings-sidebar")'));
    await unenrolled.screenshot(path.join(artifacts, 'required-two-factor-password-first.png'));
    const confirmed = await unenrolled.evaluate(`(async () => {
        const response = await fetch('/user/confirm-password', { method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ password: ${JSON.stringify(env.seed.staffPassword)} }) });
        return response.ok;
    })()`);
    check('the staff password is confirmed before generating enrollment credentials', confirmed);
    await unenrolled.goto('/staff/settings/security');
    await unenrolled.waitFor('document.querySelector("[data-two-factor-required]")');
    const setup = await unenrolled.evaluate(`({ shell: !!document.querySelector('[data-two-factor-enrollment-shell]'),
        sidebar: !!document.querySelector('.sidebar, .side-nav, .civic-settings-sidebar'),
        passwordForm: !!document.querySelector('[data-test="update-password-button"]') })`);
    check('required setup opens in the sign-in card without any workspace navigation or password form', setup.shell && !setup.sidebar && !setup.passwordForm, JSON.stringify(setup));
    const placement = await unenrolled.evaluate(`(() => {
        const aside = document.querySelector('.civic-auth-aside').getBoundingClientRect();
        const form = document.querySelector('.civic-auth-form').getBoundingClientRect();
        const setup = document.querySelector('[data-two-factor-required]').getBoundingClientRect();
        return { beside: aside.right <= form.left, inside: setup.left >= form.left && setup.right <= form.right, modal: !!document.querySelector('dialog[open]') };
    })()`);
    check('branding stays on the left and enrollment replaces the sign-in form on the right', placement.beside && placement.inside && !placement.modal, JSON.stringify(placement));
    await unenrolled.screenshot(path.join(artifacts, 'required-two-factor-desktop.png'));
    await unenrolled.viewport(320, 700, true);
    const bounds = await unenrolled.evaluate(`(() => { const r = document.querySelector('[data-two-factor-required]').getBoundingClientRect(); return { left: r.left, right: r.right, width: innerWidth }; })()`);
    check('the required setup card fits a 320px phone', bounds.left >= 0 && bounds.right <= bounds.width, JSON.stringify(bounds));
    await unenrolled.screenshot(path.join(artifacts, 'required-two-factor-phone.png'));
    const secret = await unenrolled.evaluate('document.querySelector("[data-two-factor-required] input[readonly]").value');
    await unenrolled.evaluate("localStorage.setItem('smartbrgy_theme', 'dark'); localStorage.setItem('flux.appearance', 'dark');");
    await unenrolled.goto('/staff/settings/security');
    await unenrolled.waitFor('document.querySelector("[data-two-factor-required]")');
    check('a dark-mode reload keeps the same enrollment key', await unenrolled.evaluate('document.querySelector("[data-two-factor-required] input[readonly]").value') === secret);
    const contrast = await unenrolled.evaluate(`(() => {
        const dialog = document.querySelector('[data-two-factor-required]');
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        const luminance = color => {
            context.fillStyle = color; context.fillRect(0, 0, 1, 1);
            const channels = [...context.getImageData(0, 0, 1, 1).data].slice(0, 3).map(value => { const n = value / 255; return n <= .04045 ? n / 12.92 : ((n + .055) / 1.055) ** 2.4; });
            return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
        };
        const background = luminance(getComputedStyle(document.querySelector('.civic-auth-form')).backgroundColor);
        return [...dialog.querySelectorAll('[data-flux-heading], [data-flux-text]')].map(element => {
            const foreground = luminance(getComputedStyle(element).color);
            return (Math.max(foreground, background) + .05) / (Math.min(foreground, background) + .05);
        });
    })()`);
    check('setup headings and instructions stay readable in dark mode', contrast.length > 0 && contrast.every(ratio => ratio >= 4.5), JSON.stringify(contrast));
    await unenrolled.screenshot(path.join(artifacts, 'required-two-factor-dark.png'));
    await unenrolled.click('button[wire\\:click="showVerificationIfNecessary"]');
    await unenrolled.waitFor('document.querySelector("ui-otp input")');
    await unenrolled.click('ui-otp input');
    for (const digit of env.totp(secret)) await unenrolled.send('Input.insertText', { text: digit });
    await unenrolled.click('button[wire\\:click="confirmTwoFactor"]');
    await unenrolled.waitFor('document.querySelector(\'button[wire\\\\:click="finishEnrollment"]\')');
    check('a valid authenticator code shows recovery codes before entering the workspace', await unenrolled.evaluate('!!document.querySelector("#recovery-codes-section") && !document.querySelector(".civic-settings-sidebar")'));
    await unenrolled.screenshot(path.join(artifacts, 'required-two-factor-complete.png'));
    await unenrolled.click('button[wire\\:click="finishEnrollment"]');
    await unenrolled.waitFor('!document.querySelector("[data-two-factor-enrollment-shell]")');
    await unenrolled.waitFor('location.pathname === "/staff" && document.querySelector(".sidebar")');
    check('Continue opens the permitted workspace after enrollment', await unenrolled.evaluate('location.pathname === "/staff"'), await unenrolled.evaluate('location.pathname'));
    await unenrolled.close();

    const admin = await open();
    await staffSignIn(admin, 'admin@test.invalid', env.seed.staffPassword);
    await admin.goto('/admin');
    check('a password alone does not open the workspace for a two-factor account', /two-factor-challenge|login/.test(await admin.evaluate('location.pathname')));
    await admin.close();
    const signedIn = await open();
    check('the authenticator code completes the sign-in', /^\/admin/.test(await staffSignInWithTwoFactor(signedIn, env, 'admin@test.invalid')));
    await signedIn.close();
}
