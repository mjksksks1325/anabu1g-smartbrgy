// Two residents on the same network address (every browser here is 127.0.0.1) keep their own request quota.
import { residentSignIn } from '../lib/session.mjs';

export const name = 'Per-resident request limits on a shared network';

const submit = page => page.evaluate(`(async () => {
    const data = new FormData(); data.set('document_type', 'Barangay Clearance'); data.set('purpose', 'Employment requirement');
    const response = await fetch('/portal/request', { method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
    return { status: response.status, message: (await response.json().catch(() => ({}))).message ?? '' };
})()`);

export async function run({ env, open, check }) {
    const first = await open();
    await residentSignIn(first, 'rate-a@test.invalid', env.seed.residentPassword);
    await first.goto('/portal/request');
    const statuses = [];
    for (let attempt = 0; attempt < 10; attempt++) statuses.push((await submit(first)).status);
    const refused = await submit(first);
    check('ten requests a minute are accepted for one resident', statuses.every(status => status === 200), statuses.join(','));
    check('the eleventh is refused with a message saying how long to wait', refused.status === 429 && /Please wait \d+ seconds/.test(refused.message), JSON.stringify(refused));
    await first.close();

    const second = await open();
    await residentSignIn(second, 'rate-b@test.invalid', env.seed.residentPassword);
    await second.goto('/portal/request');
    const other = await submit(second);
    check('another resident on the same address is not affected', other.status === 200, JSON.stringify(other));
    await second.close();

    const again = await open();
    await residentSignIn(again, 'rate-a@test.invalid', env.seed.residentPassword);
    await again.goto('/portal/request');
    check('signing in again from another browser does not reset the quota', (await submit(again)).status === 429);
    await again.close();
}
