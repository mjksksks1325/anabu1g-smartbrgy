let viewedCaseId = null;
let restrictionListVersion = 0;
let linkedCaseVersion = 0;
const caseResidentVersions = new Map();
const caseResidentTimers = new Map();

function setCaseResident(prefix, id = '', name = '') {
  const select = document.getElementById(`${prefix}-resident`);
  if (!select) return;
  select.innerHTML = `<option value="">${prefix === 'restriction-person' ? 'Select resident' : 'External person / no resident link'}</option>` + (id ? `<option value="${Number(id)}" selected>${escapeText(name || `Selected resident #${Number(id)}`)}</option>` : '');
  const search = document.getElementById(`${prefix}-search`);
  if (search) search.value = '';
  caseResidentVersions.set(prefix, (caseResidentVersions.get(prefix) || 0) + 1);
}

function searchCaseResidents(prefix) {
  clearTimeout(caseResidentTimers.get(prefix));
  const version = (caseResidentVersions.get(prefix) || 0) + 1;
  caseResidentVersions.set(prefix, version);
  caseResidentTimers.set(prefix, setTimeout(() => loadCaseResidents(prefix, version), 250));
}

async function loadCaseResidents(prefix, version = caseResidentVersions.get(prefix)) {
  const select = document.getElementById(`${prefix}-resident`);
  const term = document.getElementById(`${prefix}-search`)?.value.trim();
  if (!select || !term) return;
  try {
    const result = await householdApi(`/admin/residents?${new URLSearchParams({ search: term, per_page: 15 })}`);
    if (version !== caseResidentVersions.get(prefix)) return;
    const selected = select.value;
    const previous = selected ? select.options[select.selectedIndex]?.textContent : '';
    const rows = result.data || [];
    select.innerHTML = `<option value="">${prefix === 'restriction-person' ? 'Select resident' : 'External person / no resident link'}</option>` + rows.map(person => `<option value="${Number(person.id)}" ${String(person.id) === selected ? 'selected' : ''}>${escapeText(person.full_name)} ? ${escapeText(person.resident_number)} ? ${escapeText(person.date_of_birth || '')}</option>`).join('') + (selected && !rows.some(person => String(person.id) === selected) ? `<option value="${Number(selected)}" selected>${escapeText(previous)}</option>` : '');
  } catch (error) { if (version === caseResidentVersions.get(prefix)) showToast(error.message, 'red'); }
}

async function loadCaseStaff() {
  if (!document.getElementById('inc-assigned-to')) return;
  try {
    const result = await householdApi('/admin/incident-options');
    for (const id of ['inc-assigned-to', 'incident-assignee-filter']) {
      const select = document.getElementById(id);
      if (!select) continue;
      const selected = select.value;
      select.innerHTML = `<option value="">${id === 'inc-assigned-to' ? 'Unassigned' : 'All assigned staff'}</option>` + result.staff.map(staff => `<option value="${Number(staff.id)}">${escapeText(staff.name)}</option>`).join('');
      select.value = selected;
    }
  } catch (error) { showToast(error.message, 'red'); }
}

function prepareIncidentLinks(incident = {}) {
  setCaseResident('inc-complainant', incident.complainant_resident_id, incident.complainant_name);
  setCaseResident('inc-respondent', incident.respondent_resident_id, incident.respondent_name);
  const assignee = document.getElementById('inc-assigned-to');
  if (assignee) {
    if (incident.assigned_to && ![...assignee.options].some(option => option.value === String(incident.assigned_to))) assignee.add(new Option(incident.assignee_name || `Staff #${incident.assigned_to}`, String(incident.assigned_to)));
    assignee.value = incident.assigned_to || '';
  }
  const remarks = document.getElementById('inc-remarks');
  if (remarks) remarks.value = incident.remarks || '';
}

async function loadCaseHistory(id) {
  viewedCaseId = Number(id);
  const container = document.getElementById('incident-case-history');
  if (!container) return;
  container.textContent = 'Loading case history...';
  const orders = document.getElementById('protection-orders-list');
  if (orders) orders.textContent = 'Loading restricted records...';
  try {
    const incident = await householdApi(`/admin/incidents/${Number(id)}`);
    if (viewedCaseId !== Number(id)) return;
    container.innerHTML = `<h3>Assignment and case history</h3><p>Assigned staff: ${escapeText(incident.assignee_name || 'Unassigned')}</p><p>Remarks: ${escapeText(incident.remarks || 'None')}</p>` + (incident.history || []).map(event => `<p>${escapeText(event.created_at)} ? ${escapeText(event.actor || 'System')} ? ${escapeText(event.previous_status || 'New')} ? ${escapeText(event.status)}</p>`).join('') + `<h3>Linked document restrictions</h3>` + ((incident.restrictions || []).map(item => `<p>#${Number(item.id)} ? ${escapeText(item.affected_document_type || 'All documents')} ? ${escapeText(item.status)}</p>`).join('') || '<p>No linked restrictions.</p>');
    if (document.getElementById('protection-orders-list')) await loadProtectionOrders();
  } catch (error) {
    if (viewedCaseId === Number(id)) {
      container.textContent = error.message;
      if (orders) orders.textContent = 'Restricted records could not be loaded.';
    }
  }
}

async function loadProtectionOrders(page = 1) {
  const container = document.getElementById('protection-orders-list');
  if (!container || !viewedCaseId) return;
  const caseId = viewedCaseId;
  try {
    const result = await householdApi(`/admin/protection-orders?${new URLSearchParams({ incident_id: caseId, page })}`);
    if (viewedCaseId !== caseId) return;
    container.innerHTML = (result.data || []).map(order => `<details class="resident-account-panel"><summary>BPO record #${Number(order.id)} ? ${escapeText(order.status)}</summary><p>Protected person: ${escapeText(order.protected_person)}</p><p>Respondent: ${escapeText(order.respondent)}</p><p>Issued: ${escapeText(order.issued_on)} / Effective: ${escapeText(order.effective_on || 'Not supplied')} / End: ${escapeText(order.ends_on || 'Not supplied')}</p><p>Authority: ${escapeText(order.issuing_authority)}</p><p>Reference: ${escapeText(order.supporting_document_reference || 'None')}</p><p>${escapeText(order.internal_remarks || '')}</p><button class="btn btn-xs" onclick="editProtectionOrder(${Number(order.id)})">Edit BPO record</button></details>`).join('') || '<p>No BPO records for this case.</p>';
    if (result.last_page > 1) container.innerHTML += `<div class="resident-pagination"><button class="btn btn-xs" ${result.current_page <= 1 ? 'disabled' : ''} onclick="loadProtectionOrders(${result.current_page - 1})">Previous</button><span>Page ${Number(result.current_page)} of ${Number(result.last_page)}</span><button class="btn btn-xs" ${result.current_page >= result.last_page ? 'disabled' : ''} onclick="loadProtectionOrders(${result.current_page + 1})">Next</button></div>`;
  } catch (error) { if (viewedCaseId === caseId) container.textContent = error.message; }
}

function openProtectionOrderForm(order = {}) {
  if (!viewedCaseId || !document.getElementById('modal-protection-order')) return;
  const fields = { 'bpo-id': order.id, 'bpo-incident-id': order.incident_id || viewedCaseId, 'bpo-protected-person': order.protected_person, 'bpo-respondent': order.respondent, 'bpo-issued-on': order.issued_on, 'bpo-effective-on': order.effective_on, 'bpo-ends-on': order.ends_on, 'bpo-status': order.status || 'recorded', 'bpo-authority': order.issuing_authority, 'bpo-reference': order.supporting_document_reference, 'bpo-remarks': order.internal_remarks };
  for (const [id, value] of Object.entries(fields)) document.getElementById(id).value = value || '';
  setCaseResident('bpo-protected', order.protected_resident_id, order.protected_person);
  setCaseResident('bpo-respondent', order.respondent_resident_id, order.respondent);
  openModal('modal-protection-order');
}

async function editProtectionOrder(id) {
  try { openProtectionOrderForm(await householdApi(`/admin/protection-orders/${Number(id)}`)); }
  catch (error) { showToast(error.message, 'red'); }
}

async function saveCaseForm(event, callback) {
  event.preventDefault();
  const button = event.target.querySelector('button[type="submit"]');
  if (button.disabled) return;
  button.disabled = true;
  try { await callback(); } catch (error) { showToast(error.message, 'red'); }
  finally { button.disabled = false; }
}

async function saveProtectionOrder(event) {
  await saveCaseForm(event, async () => {
    const value = id => document.getElementById(id).value || null;
    const id = value('bpo-id');
    const body = { incident_id: value('bpo-incident-id'), protected_resident_id: value('bpo-protected-resident'), respondent_resident_id: value('bpo-respondent-resident'), protected_person: value('bpo-protected-person'), respondent: value('bpo-respondent'), issued_on: value('bpo-issued-on'), effective_on: value('bpo-effective-on'), ends_on: value('bpo-ends-on'), status: value('bpo-status'), issuing_authority: value('bpo-authority'), supporting_document_reference: value('bpo-reference'), internal_remarks: value('bpo-remarks') };
    const result = await householdApi(id ? `/admin/protection-orders/${Number(id)}` : '/admin/protection-orders', id ? 'PATCH' : 'POST', body);
    closeModal('modal-protection-order'); showToast(result.message, 'green'); await loadProtectionOrders();
  });
}

let restrictionCases = [];
async function loadRestrictionCases() {
  const version = ++linkedCaseVersion;
  const resident = document.getElementById('restriction-person-resident').value;
  const select = document.getElementById('restriction-case');
  select.innerHTML = '<option value="">No linked case</option>';
  const summary = document.getElementById('restriction-case-summary');
  summary.textContent = '';
  if (!resident) return;
  try {
    const result = await householdApi(`/admin/incidents?${new URLSearchParams({ resident_id: resident, per_page: 100 })}`);
    if (version !== linkedCaseVersion) return;
    restrictionCases = result.data || [];
    select.innerHTML += restrictionCases.map(item => `<option value="${Number(item.id)}">${escapeText(item.incident_number)} ? ${escapeText(item.incident_type)} ? ${escapeText(item.status_label)}</option>`).join('');
    select.onchange = () => {
      const item = restrictionCases.find(item => String(item.id) === select.value);
      summary.textContent = item ? `${item.incident_number} / ${item.status_label} / ${item.occurred_at_display} / Assigned: ${item.assignee_name || 'Unassigned'}` : '';
    };
    if (result.total > 100) summary.textContent = 'Showing the latest 100 linked cases. Use Incident Reports to search older case history.';
  } catch (error) { if (version === linkedCaseVersion) showToast(error.message, 'red'); }
}

function openRestrictionForm() {
  if (!document.getElementById('modal-request-restriction')) return;
  setCaseResident('restriction-person');
  for (const id of ['restriction-document', 'restriction-category', 'restriction-reason', 'restriction-start', 'restriction-end', 'restriction-next-review']) document.getElementById(id).value = '';
  document.getElementById('restriction-case').innerHTML = '<option value="">No linked case</option>';
  document.getElementById('restriction-case-summary').textContent = '';
  ++linkedCaseVersion;
  openModal('modal-request-restriction');
}

async function saveRestriction(event) {
  await saveCaseForm(event, async () => {
    const value = id => document.getElementById(id).value || null;
    const result = await householdApi('/admin/request-restrictions', 'POST', { resident_id: value('restriction-person-resident'), incident_id: value('restriction-case'), affected_document_type: value('restriction-document'), reason_category: value('restriction-category'), internal_reason: value('restriction-reason'), starts_at: value('restriction-start'), ends_at: value('restriction-end'), next_review_at: value('restriction-next-review') });
    closeModal('modal-request-restriction'); showToast(result.message, 'green'); await loadRestrictions();
  });
}

async function loadRestrictions(page = 1) {
  const container = document.getElementById('restrictions-tbody');
  if (!container) return;
  const version = ++restrictionListVersion;
  container.innerHTML = '<tr><td colspan="5" class="resident-table-message">Loading reviewed restrictions...</td></tr>';
  document.getElementById('restrictions-pagination').innerHTML = '';
  try {
    const result = await householdApi(`/admin/request-restrictions?${new URLSearchParams({ page, search: document.getElementById('restriction-search')?.value.trim() || '' })}`);
    if (version !== restrictionListVersion) return;
    container.innerHTML = (result.data || []).map(item => `<tr><td>${escapeText(item.resident?.full_name || 'Resident')}<br><small>${escapeText(item.resident?.resident_number)}</small></td><td>${escapeText(item.affected_document_type || 'All document requests')}</td><td><span class="badge ${item.status === 'active' ? 'badge-red' : 'badge-gray'}">${escapeText(item.status)}</span></td><td><details><summary>${escapeText(item.reason_category)}</summary><p>Start: ${escapeText(item.starts_at)} / End: ${escapeText(item.ends_at || 'Not set')}</p><p>Reviewed by staff #${Number(item.reviewed_by) || '?'} at ${escapeText(item.reviewed_at || 'Not reviewed')}</p><p>Next review: ${escapeText(item.next_review_at || 'Not set')}</p><p>${escapeText(item.internal_reason)}</p>${item.lifted_at ? `<p>Lifted by staff #${Number(item.lifted_by)} at ${escapeText(item.lifted_at)}: ${escapeText(item.lift_reason)}</p>` : ''}</details></td><td>${window.AUTHENTICATED_USER?.role === 'admin' ? `${item.status === 'pending_review' ? `<button class="btn btn-xs btn-primary" onclick="reviewRestriction(${Number(item.id)},this)">Review &amp; Activate</button>` : ''}${['active', 'pending_review'].includes(item.status) ? `<button class="btn btn-xs" onclick="openLiftRestriction(${Number(item.id)})">Lift</button>` : ''}` : 'Review by admin'}</td></tr>`).join('') || '<tr><td colspan="5" class="resident-table-message">No restrictions found.</td></tr>';
    document.getElementById('restrictions-pagination').innerHTML = `<span>${Number(result.total)} restrictions</span><button class="btn btn-xs" ${result.current_page <= 1 ? 'disabled' : ''} onclick="loadRestrictions(${result.current_page - 1})">Previous</button><span>Page ${Number(result.current_page)} of ${Number(result.last_page)}</span><button class="btn btn-xs" ${result.current_page >= result.last_page ? 'disabled' : ''} onclick="loadRestrictions(${result.current_page + 1})">Next</button>`;
  } catch (error) { if (version === restrictionListVersion) container.textContent = error.message; }
}

async function reviewRestriction(id, button) {
  if (button.disabled || !confirm('Confirm that you reviewed this decision and authorize its document restriction?')) return;
  button.disabled = true;
  try { const result = await householdApi(`/admin/request-restrictions/${Number(id)}/review`, 'POST', {}); showToast(result.message, 'green'); await loadRestrictions(); }
  catch (error) { showToast(error.message, 'red'); }
  finally { button.disabled = false; }
}

function openLiftRestriction(id) {
  document.getElementById('lift-restriction-id').value = Number(id);
  document.getElementById('lift-restriction-reason').value = '';
  openModal('modal-lift-restriction');
}
async function liftRestriction(event) {
  await saveCaseForm(event, async () => {
    const id = document.getElementById('lift-restriction-id').value;
    const result = await householdApi(`/admin/request-restrictions/${Number(id)}/lift`, 'POST', { lift_reason: document.getElementById('lift-restriction-reason').value });
    closeModal('modal-lift-restriction'); showToast(result.message, 'green'); await loadRestrictions();
  });
}

void loadCaseStaff();
if (window.ADMIN_ACTIVE_SCREEN === 'request-records') void loadRestrictions();
