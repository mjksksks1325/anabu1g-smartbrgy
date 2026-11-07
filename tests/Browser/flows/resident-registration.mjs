// A resident in the barangay records activates their portal account with the staff-issued code, then confirms their
// email through the link captured from the local mail server.
import { messages, residentSignIn } from '../lib/session.mjs';

export const name = 'Resident account registration and email confirmation';

export async function run({ env, mail, open, check }) {
    const email = 'new-resident@test.invalid';
    const page = await open();
    await page.goto('/portal/register?step=verify');
    await page.submit(`(() => { document.getElementById('resident-number').value = ${JSON.stringify(env.seed.activation.residentNumber)}; document.getElementById('activation-code').value = ${JSON.stringify(env.seed.activation.code)}; document.getElementById('resident-number').form.requestSubmit(); })()`);
    check('the staff-issued activation code opens account setup', await page.evaluate(`!!document.getElementById('registration-email')`), await messages(page));

    const since = Date.now();
    await page.submit(`(() => { document.getElementById('registration-email').value = ${JSON.stringify(email)}; document.getElementById('registration-password').value = ${JSON.stringify(env.seed.residentPassword)}; document.getElementById('registration-confirmation').value = ${JSON.stringify(env.seed.residentPassword)}; document.getElementById('registration-email').form.requestSubmit(); })()`);
    const registered = await messages(page);
    check('registration leads to sign-in and says the confirmation link was sent', (await page.evaluate('location.pathname')) === '/portal/login' && /confirmation link we sent/i.test(registered), registered);
    await page.sleep(600);
    const captured = mail.to(email, since);
    const link = captured[0] ? mail.links(captured[0], /\/portal\/email\/verify\//)[0] : null;
    check('one confirmation email reached the local mail capture', captured.length === 1, `${captured.length} message(s)`);
    check('its link is signed, expires and points at this server', Boolean(link) && link.startsWith(env.base) && /signature=/.test(link) && /expires=/.test(link));

    await residentSignIn(page, email, env.seed.residentPassword);
    await page.goto('/portal/request');
    check('document requests stay closed until the email is confirmed', await page.evaluate(`!!document.querySelector('form[action$="/portal/email/verification-notification"]') && !document.getElementById('resident-details-form')`));

    if (link) {
        const url = new URL(link);
        url.searchParams.set('signature', url.searchParams.get('signature').replace(/^./, c => (c === 'a' ? 'b' : 'a')));
        await page.goto(url.pathname + url.search);
        check('an altered link is refused with the neutral message', /invalid or has expired/.test(await messages(page)));
        await page.goto(new URL(link).pathname + new URL(link).search);
        check('the genuine link confirms the email and opens the request form', /email address is confirmed/.test(await messages(page)) && (await page.evaluate(`!!document.getElementById('resident-details-form')`)));
    }
    check('the account is marked as confirmed', env.query('select email_verified_at from users where email = ?', email)[0]?.email_verified_at !== null);
    await page.close();
}
