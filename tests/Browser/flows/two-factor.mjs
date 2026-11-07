// Privileged staff must set up two-factor authentication before using the workspace; set-up staff sign in with a code.
import { staffSignIn, staffSignInWithTwoFactor } from '../lib/session.mjs';

export const name = 'Privileged staff two-factor authentication';

export async function run({ env, open, check }) {
    const unenrolled = await open();
    await staffSignIn(unenrolled, 'records@test.invalid', env.seed.staffPassword);
    await unenrolled.goto('/staff/residents');
    const page = await unenrolled.evaluate(`({ path: location.pathname, required: !!document.querySelector('[data-two-factor-required]') })`);
    check('a privileged account without two-factor is sent to its security settings', /settings\/security|confirm-password/.test(page.path), JSON.stringify(page));
    const api = await unenrolled.evaluate(`fetch('/staff/residents', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(async r => ({ status: r.status, body: await r.text() }))`);
    check('its workspace data requests are refused until two-factor is set up', api.status === 403 && /two_factor_required/.test(api.body), String(api.status));
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
