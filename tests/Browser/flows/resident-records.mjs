// Staff edit a resident record from the workspace session; the verification change appears in the Audit Log.
import { staffSignInWithTwoFactor } from '../lib/session.mjs';

export const name = 'Resident Records editing and its audit entry';

export async function run({ env, open, check }) {
    const page = await open();
    await staffSignInWithTwoFactor(page, env, 'admin@test.invalid');
    await page.goto('/staff/residents');
    const residentId = env.seed.complete.residentId;
    const result = await page.evaluate(`(async () => {
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
        const current = await (await fetch('/staff/residents/${residentId}', { headers, credentials: 'same-origin' })).json();
        const resident = current.resident ?? current;
        const payload = {
            first_name: resident.first_name, middle_name: resident.middle_name, last_name: resident.last_name, suffix: resident.suffix,
            date_of_birth: String(resident.date_of_birth).slice(0, 10), gender: resident.gender, civil_status: resident.civil_status,
            purok: resident.purok, address: resident.address, contact_number: '09171234567', residency_type: 'Homeowner', special_groups: [],
            status: 'active', is_in_good_standing: true, nationality: resident.nationality, is_verified_indigent: false,
            expected_updated_at: resident.updated_at,
        };
        const response = await fetch('/staff/residents/${residentId}', { method: 'PATCH', headers, credentials: 'same-origin', body: JSON.stringify(payload) });
        return { status: response.status, body: (await response.text()).slice(0, 300) };
    })()`);
    check('the resident record saves', result.status === 200, JSON.stringify(result));
    check('the change is stored', Number(env.query('select is_verified_indigent from residents where id = ?', residentId)[0]?.is_verified_indigent) === 0);
    const audit = await page.evaluate(`fetch('/admin/audit-log', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(r => r.json())`);
    const entry = (audit.events ?? []).find(event => event.action === 'residents / verification changed');
    check('the Audit Log shows who changed indigency verification, without personal details', Boolean(entry) && /Indigency verified: yes → no/.test(entry.detail) && !/Testa|Rizal/.test(entry.detail), entry?.detail);
    await page.close();
}
