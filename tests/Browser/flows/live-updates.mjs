import { staffSignInWithTwoFactor, residentSignIn } from '../lib/session.mjs';
import path from 'node:path';

export const name = 'New data appears across browser sessions without reloading';

async function post(page, url, body, method = 'POST') {
    return page.evaluate(`(async () => {
        const response = await fetch(${JSON.stringify(url)}, { method: ${JSON.stringify(method)}, credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(${JSON.stringify(body)}) });
        return { status: response.status, body: (await response.text()).slice(0, 250) };
    })()`);
}

export async function run({ env, open, check, artifacts }) {
    const writer = await open();
    await staffSignInWithTwoFactor(writer, env, 'admin@test.invalid');
    await writer.goto('/staff/announcements');
    const publicReader = await open();
    await publicReader.goto('/portal/announcements');
    await publicReader.evaluate('window.liveTestSentinel = "same-page"');
    const created = await post(writer, '/staff/announcements', { title: 'Live announcement from another session', body: 'Published without asking readers to reload.', status: 'published' });
    check('another session publishes an announcement', created.status === 200, JSON.stringify(created));
    await publicReader.waitFor('document.body.textContent.includes("Live announcement from another session")', 20000);
    check('the resident announcement page updates automatically without a page reload', await publicReader.evaluate('window.liveTestSentinel === "same-page"'));

    // Use the authenticated writer tab as the staff reader while a resident submits a new request in another session.
    await writer.goto('/staff/residents');
    await writer.waitFor('!!document.querySelector("#records-tbody [data-resident-action]")');
    await writer.evaluate('window.liveTestSentinel = "resident-list"; document.querySelector("#residents-search").value = "Testa"; filterResidents();');
    const current = await writer.evaluate(`fetch('/staff/residents/${env.seed.complete.residentId}', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(r => r.json())`);
    const person = current.resident ?? current;
    const body = { first_name: 'Testa Live', middle_name: person.middle_name, last_name: person.last_name, suffix: person.suffix,
        date_of_birth: String(person.date_of_birth).slice(0, 10), gender: person.gender, civil_status: person.civil_status, purok: person.purok,
        address: person.address, contact_number: person.contact_number, residency_type: person.residency_type || 'Homeowner', status: 'active', nationality: person.nationality };
    const saved = await post(writer, `/staff/residents/${env.seed.complete.residentId}`, body, 'PATCH');
    check('an API update saves the resident record', saved.status === 200, JSON.stringify(saved));
    await writer.waitFor('document.querySelector("#records-tbody").textContent.includes("Testa Live")', 20000);
    check('the resident list updates while preserving the search and page', await writer.evaluate('window.liveTestSentinel === "resident-list" && document.querySelector("#residents-search").value === "Testa"'));

    const resident = await open();
    await residentSignIn(resident, 'resident@test.invalid', env.seed.residentPassword);
    await resident.goto('/portal/account/requests/REQ-BROWSER-READY');
    await resident.evaluate('window.liveTestSentinel = "request-status"');
    const status = await post(writer, `/staff/document-requests/${env.seed.readyRequest.id}/status`, { status: 'processing', remarks: 'Live processing note from staff' }, 'PATCH');
    check('staff updates a resident request', status.status === 200, JSON.stringify(status));
    await resident.waitFor('!!document.querySelector("[data-status=processing]") && document.body.textContent.includes("Live processing note from staff")', 20000);
    check('the resident sees the new status and remarks without reloading', await resident.evaluate('window.liveTestSentinel === "request-status"'));

    await resident.goto('/portal/request');
    await resident.evaluate(`document.getElementById('f-purpose').value = 'Unfinished request purpose'; document.getElementById('f-purpose').dispatchEvent(new Event('input', { bubbles: true })); window.liveTestSentinel = 'request-form';`);
    const incident = await post(writer, '/staff/incidents', { incident_type: 'Noise Complaint', occurred_date: new Date().toISOString().slice(0, 10), location: 'Synthetic test location', severity: 'low', details: 'Private synthetic complaint narrative.', respondent_resident_id: env.seed.complete.residentId });
    check('staff files an incident linked to the resident', incident.status >= 200 && incident.status < 300, JSON.stringify(incident));
    await resident.waitFor('document.querySelector(\'[data-live-region="request-notices"]\').textContent.includes("pending incident report")', 20000);
    check('a new complaint notice appears without resetting the document request form', await resident.evaluate('window.liveTestSentinel === "request-form" && document.getElementById("f-purpose").value === "Unfinished request purpose" && !document.body.textContent.includes("Private synthetic complaint narrative.")'));

    await writer.goto('/staff/resident-applications');
    await writer.evaluate(`(() => { const input = document.querySelector('input[type=search]'); input.value = 'unfinished search'; input.focus(); input.setSelectionRange(3, 7); input.dispatchEvent(new Event('input', { bubbles: true })); })()`);
    await writer.sleep(1400);
    await writer.evaluate('window.SiteLiveUpdates.refresh()');
    check('a live table refresh keeps the search value, focus and selection', await writer.evaluate(`(() => { const input = document.querySelector('input[type=search]'); return input.value === 'unfinished search' && document.activeElement === input && input.selectionStart === 3 && input.selectionEnd === 7; })()`));
    await writer.evaluate('document.activeElement.blur(); window.sameLiveRegion = document.querySelector("[data-live-region]")');
    await writer.evaluate('window.SiteLiveUpdates.refresh()');
    check('unchanged server data does not rebuild the page region', await writer.evaluate('window.sameLiveRegion === document.querySelector("[data-live-region]")'));
    await writer.evaluate('Object.defineProperty(document, "hidden", { configurable: true, get: () => true })');
    check('automatic updates pause in a hidden tab', !(await writer.evaluate('window.SiteLiveUpdates.refresh()')));
    await writer.evaluate('delete document.hidden');

    await writer.goto('/staff/announcements');
    await writer.click('[data-announcement-create-open]');
    await writer.waitFor('!!document.querySelector("dialog[open]")');
    await writer.evaluate(`(() => { const field = document.querySelector('[data-announcement-create-dialog] [name=title]'); field.value = 'Keep my unfinished draft'; field.dispatchEvent(new Event('input', { bubbles: true })); })()`);
    check('live refresh pauses while a form dialog is open', !(await writer.evaluate('window.SiteLiveUpdates.refresh()')));
    await writer.click('[data-announcement-create-close]');
    await writer.sleep(1400);
    await post(writer, '/staff/announcements', { title: 'Second live announcement', body: 'The unfinished draft must survive this change.', status: 'published' });
    await writer.evaluate('window.SiteLiveUpdates.refresh()');
    await writer.waitFor('document.querySelector("[data-live-region]").textContent.includes("Second live announcement")');
    await writer.click('[data-announcement-create-open]');
    check('a background update preserves the unfinished announcement draft', await writer.evaluate('document.querySelector(\'[data-announcement-create-dialog] [name=title]\').value === "Keep my unfinished draft"'));
    await writer.click('[data-announcement-create-close]');
    await writer.sleep(1400);
    await writer.evaluate(`window.originalLiveFetch = window.fetch; window.fetch = () => Promise.reject(new TypeError('Synthetic offline test'));`);
    const failed = await writer.evaluate('window.SiteLiveUpdates.refresh()');
    check('a network failure retains the current data and URL', failed === false && await writer.evaluate('location.pathname === "/staff/announcements" && document.body.textContent.includes("Second live announcement")'));
    await writer.evaluate('window.fetch = window.originalLiveFetch');
    await writer.click('[data-announcement-edit-open]');
    await writer.waitFor('!!document.querySelector("dialog[open]")');
    await writer.click('dialog[open] [data-flux-modal-close] button[aria-label]');
    check('modal close controls continue working after live region replacements', await writer.evaluate('!document.querySelector("dialog[open]")'));
    await publicReader.screenshot(path.join(artifacts, 'announcements-updated-in-place.png'));
    await resident.screenshot(path.join(artifacts, 'resident-notice-updated-in-place.png'));
    await writer.close();
    await resident.close();
    await publicReader.close();
}
