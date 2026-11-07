// Forgot Password for a resident (portal pages, in Filipino) and for a staff member, through emails captured locally.
import { messages, residentSignIn, staffSignIn } from '../lib/session.mjs';

export const name = 'Resident and staff Forgot Password';

async function resetThroughEmail({ page, env, mail, check, email, newPassword, label }) {
    const since = Date.now();
    await page.submit(`(() => { const input = document.querySelector('input[name="email"]'); input.value = ${JSON.stringify(email)}; input.form.requestSubmit(); })()`);
    const answer = await messages(page);
    await page.sleep(600);
    const captured = mail.to(email, since);
    const link = captured[0] ? mail.links(captured[0], /reset-password\//)[0] : null;
    check(`${label}: one reset email with a link was captured`, captured.length === 1 && Boolean(link), `${captured.length} message(s)`);
    if (!link) return { answer };
    await page.goto(new URL(link).pathname + new URL(link).search);
    await page.submit(`(() => { const form = document.querySelector('form[action$="/reset-password"]'); form.querySelector('input[name="password"]').value = ${JSON.stringify(newPassword)}; form.querySelector('input[name="password_confirmation"]').value = ${JSON.stringify(newPassword)}; form.requestSubmit(); })()`);
    // The same link cannot set a password a second time.
    await page.goto(new URL(link).pathname + new URL(link).search);
    await page.submit(`(() => { const form = document.querySelector('form[action$="/reset-password"]'); form.querySelector('input[name="password"]').value = 'Another-Passw0rd!99'; form.querySelector('input[name="password_confirmation"]').value = 'Another-Passw0rd!99'; form.requestSubmit(); })()`);
    check(`${label}: a used reset link is refused`, (await messages(page)).length > 0 && /reset-password/.test(await page.evaluate('location.pathname')), await messages(page));
    return { answer, link };
}

export async function run({ env, mail, open, check }) {
    // Resident, in Filipino.
    const resident = await open();
    await resident.goto('/portal');
    await resident.evaluate(`switchPortalLocale('fil')`);
    await resident.sleep(600);
    await resident.goto('/forgot-password?from=portal');
    check('the resident Forgot Password page follows the chosen language', (await resident.evaluate('document.documentElement.lang')) === 'fil');
    const unknownSince = Date.now();
    await resident.submit(`(() => { const input = document.querySelector('input[name="email"]'); input.value = 'nobody-here@test.invalid'; input.form.requestSubmit(); })()`);
    const neutral = await messages(resident);
    await resident.sleep(500);
    check('an unknown address gets the same neutral answer and no email', neutral.length > 0 && mail.to('nobody-here@test.invalid', unknownSince).length === 0, neutral);
    await resident.goto('/forgot-password?from=portal');
    const residentReset = await resetThroughEmail({ page: resident, env, mail, check, email: 'reset-resident@test.invalid', newPassword: 'Resident-Changed!2026', label: 'resident' });
    check('the resident answer is in Filipino and identical to the unknown-address answer', residentReset.answer === neutral && !/If an account uses this email/.test(neutral), residentReset.answer);
    check('the resident link opens the portal reset page', /from=portal/.test(residentReset.link ?? ''));
    await resident.close();
    const fresh = await open();
    check('the resident signs in with the new password', !/login/.test(await residentSignIn(fresh, 'reset-resident@test.invalid', 'Resident-Changed!2026')));
    await fresh.close();
    const old = await open();
    check('the old resident password no longer works', /login/.test(await residentSignIn(old, 'reset-resident@test.invalid', env.seed.residentPassword)));
    await old.close();

    // Staff.
    const staff = await open();
    await staff.goto('/forgot-password');
    const staffReset = await resetThroughEmail({ page: staff, env, mail, check, email: 'clerk@test.invalid', newPassword: 'Staff-Changed!2026x', label: 'staff' });
    check('the staff link opens the staff reset page', Boolean(staffReset.link) && !/from=portal/.test(staffReset.link));
    await staff.close();
    const staffAgain = await open();
    check('the staff member signs in with the new password', /^\/(staff|admin)/.test(await staffSignIn(staffAgain, 'clerk@test.invalid', 'Staff-Changed!2026x')));
    await staffAgain.close();
}
