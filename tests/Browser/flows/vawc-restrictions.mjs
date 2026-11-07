// VAWC cases stay hidden from staff without VAWC access: not in their incident list, not by direct address.
import { staffSignIn } from '../lib/session.mjs';

export const name = 'VAWC confidentiality';

export async function run({ env, open, check }) {
    const page = await open();
    await staffSignIn(page, 'desk@test.invalid', env.seed.staffPassword);
    await page.goto('/staff/incidents');
    const fetchJson = url => page.evaluate(`fetch(${JSON.stringify(url)}, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(async r => ({ status: r.status, text: await r.text() }))`);
    const list = await fetchJson('/staff/incidents');
    check('the incident desk sees ordinary incidents', list.status === 200 && list.text.includes(`"id":${env.seed.incidents.ordinary}`), String(list.status));
    check('the VAWC case is not in its list', !list.text.includes(`"id":${env.seed.incidents.vawc}`) && !list.text.includes('Confidential Complainant'));
    const direct = await fetchJson(`/staff/incidents/${env.seed.incidents.vawc}`);
    check('the VAWC case cannot be opened by its address', [403, 404].includes(direct.status) && !direct.text.includes('Confidential Complainant'), String(direct.status));
    const pageLoad = await page.evaluate(`fetch('/staff/protection-orders', { credentials: 'same-origin' }).then(r => r.status)`);
    check('protection orders are closed to it', pageLoad === 403, String(pageLoad));
    await page.close();
}
