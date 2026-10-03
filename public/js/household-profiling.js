let profilingPreview = null;
let profilingBusy = false;
let profilingValidated = false;
let profilingMappingChanged = false;

function openHouseholdProfilingImport() {
  if (profilingBusy) return showToast('Please wait for the current import request to finish.', 'red');
  profilingPreview = null;
  profilingValidated = false;
  profilingMappingChanged = false;
  document.getElementById('profiling-file').value = '';
  document.getElementById('profiling-preview').hidden = true;
  document.getElementById('profiling-errors').textContent = '';
  document.getElementById('profiling-status').textContent = '';
  document.getElementById('profiling-review').disabled = true;
  document.getElementById('profiling-save').disabled = true;
  const purok = document.getElementById('profiling-purok');
  purok.replaceChildren(new Option('Use purok from file', ''));
  PUROK_DATA.forEach(item => purok.add(new Option(item.label, item.key)));
  purok.onchange = invalidateProfilingPreview;
  openModal('modal-household-import');
}

function invalidateProfilingPreview() {
  profilingValidated = false;
  document.getElementById('profiling-save').disabled = true;
}

function clearProfilingUpload() {
  profilingPreview = null;
  invalidateProfilingPreview();
  document.getElementById('profiling-preview').hidden = true;
  setProfilingBusy(false);
}

async function selectHouseholdProfilingFile() {
  if (profilingBusy) return;
  clearProfilingUpload();
  if (document.getElementById('profiling-file').files.length) await uploadHouseholdProfiling();
}

function changeProfilingMapping() {
  profilingMappingChanged = true;
  invalidateProfilingPreview();
}

async function profilingRequest(url, body) {
  const form = body instanceof FormData;
  const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', ...csrfRequestHeaders(), ...(!form ? { 'Content-Type': 'application/json' } : {}) }, body: form ? body : JSON.stringify(body) });
  let result;
  try {
    result = await response.json();
  } catch {
    throw new Error(response.status === 413 ? 'The file is too large for the server. Upload a smaller CSV file.' : response.redirected || response.status === 401 || response.status === 419 ? 'Your session expired. Refresh the page and sign in again before uploading.' : 'The server could not prepare the preview. Please retry the upload.');
  }
  if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join('\n') : result.message || 'Unable to process the import.');
  return result;
}

function setProfilingBusy(busy) {
  profilingBusy = busy;
  document.querySelectorAll('#profiling-preview input, #profiling-preview select, #profiling-preview button, #profiling-file, #profiling-purok').forEach(control => { control.disabled = busy; });
  document.getElementById('profiling-upload').disabled = busy;
  document.getElementById('profiling-upload').textContent = busy ? 'Processing...' : 'Upload and preview';
  document.getElementById('profiling-review').disabled = busy || !profilingPreview;
  document.getElementById('profiling-save').disabled = busy || !profilingValidated || !profilingPreview || profilingPreview.summary.invalid > 0 || profilingPreview.summary.ready === 0;
}

async function uploadHouseholdProfiling() {
  if (profilingBusy) return;
  const file = document.getElementById('profiling-file').files[0];
  if (!file) return showToast('Select a CSV or Excel .xlsx profiling file.', 'red');
  const form = new FormData();
  form.append('file', file);
  form.append('purok', document.getElementById('profiling-purok').value);
  profilingPreview = null;
  invalidateProfilingPreview();
  document.getElementById('profiling-preview').hidden = true;
  setProfilingBusy(true);
  document.getElementById('profiling-errors').textContent = '';
  document.getElementById('profiling-status').textContent = 'Uploading file and preparing preview...';
  try {
    profilingPreview = await profilingRequest('/admin/resident-profiling-imports/preview', form);
    profilingValidated = true;
    profilingMappingChanged = false;
    renderProfilingMapping();
    renderProfilingPreview();
    document.getElementById('profiling-status').textContent = 'Preview ready. Review the groups and highlighted rows before saving.';
  } catch (error) { document.getElementById('profiling-errors').textContent = error.message; }
  finally {
    if (!profilingPreview) document.getElementById('profiling-status').textContent = '';
    setProfilingBusy(false);
  }
}

function renderProfilingMapping() {
  document.getElementById('profiling-mapping').innerHTML = profilingPreview.fields.map(field => `<div class="form-group"><label class="form-label" for="profiling-map-${field}">${escapeText(field.replaceAll('_', ' '))}</label><select class="form-input" id="profiling-map-${field}" onchange="changeProfilingMapping()"><option value="">Not in file</option>${profilingPreview.headers.map((header, index) => `<option value="${index}" ${profilingPreview.mapping[field] === index ? 'selected' : ''}>${escapeText(header)}</option>`).join('')}</select></div>`).join('');
}

function updateProfilingRow(id, field, value) {
  const row = profilingPreview?.rows.find(item => item.id === id);
  if (!row) return;
  if (field === 'action' || field === 'resident_id' || field === 'is_head' || field === 'confirm_duplicate') row[field] = value;
  else row.fields[field] = value;
  invalidateProfilingPreview();
}

function renderProfilingPreview() {
  document.getElementById('profiling-preview').hidden = false;
  document.getElementById('profiling-errors').textContent = '';
  const summary = profilingPreview.summary;
  document.getElementById('profiling-summary').textContent = `${summary.households} households · ${summary.residents} residents · ${summary.heads} heads · ${summary.duplicates} possible/exact duplicates · ${summary.invalid} invalid/unresolved rows · ${summary.ready} ready`;
  const groups = new Map();
  profilingPreview.rows.forEach(row => {
    const group = row.fields.household_group || 'Unassigned group — review required';
    if (!groups.has(group)) groups.set(group, []);
    groups.get(group).push(row);
  });
  document.getElementById('profiling-households').innerHTML = [...groups].map(([group, rows]) => {
    const head = rows.find(row => row.is_head && row.action !== 'skip');
    const ready = rows.every(row => row.errors.length === 0);
    return `<details class="resident-account-panel"><summary><strong>${escapeText(group)}</strong> — Head: ${escapeText(head ? `${head.fields.first_name} ${head.fields.last_name}` : 'Select head')} — Purok: ${escapeText(head?.fields.purok || rows[0].fields.purok || 'Missing')} — ${rows.length} residents — ${ready ? 'Ready' : 'Needs review'}</summary>${rows.map(renderProfilingRow).join('')}</details>`;
  }).join('');
}

function renderProfilingRow(row) {
  const createDisabled = row.matches.some(match => match.exact);
  return `<details class="resident-account-panel"><summary>Row ${Number(row.source_row)}: ${escapeText(`${row.fields.first_name} ${row.fields.last_name}`)} — ${row.is_head ? 'Head' : escapeText(row.fields.relationship_to_household_head || 'Relationship unspecified')} — ${escapeText(row.action)}</summary>
    <div class="resident-table-error">${row.errors.map(escapeText).join('<br>')}</div>
    <div class="form-group"><label class="form-label" for="profiling-action-${row.id}">Duplicate resolution / row action</label><select class="form-input" id="profiling-action-${row.id}" onchange="updateProfilingRow(${row.id},'action',this.value)">
      <option value="resolve" ${row.action === 'resolve' ? 'selected' : ''}>Choose an action</option><option value="create" ${createDisabled ? 'disabled' : ''} ${row.action === 'create' ? 'selected' : ''}>Create as a new resident</option><option value="link" ${!row.matches.length ? 'disabled' : ''} ${row.action === 'link' ? 'selected' : ''}>Link existing resident</option><option value="skip" ${row.action === 'skip' ? 'selected' : ''}>Skip</option></select></div>
    <div class="form-group"><label class="form-label" for="profiling-link-${row.id}">Matching existing residents</label><select class="form-input" id="profiling-link-${row.id}" onchange="updateProfilingRow(${row.id},'resident_id',Number(this.value)||null)"><option value="">Select a record to link</option>${row.matches.map(match => `<option value="${match.id}" ${match.archived ? 'disabled' : ''} ${match.id === row.resident_id ? 'selected' : ''}>${escapeText(match.name)} — ${escapeText(match.resident_number)} — ${match.exact ? 'Exact' : 'Possible'}${match.archived ? ' (archived: restore first)' : ''}</option>`).join('')}</select></div>
    <label class="resident-standing"><input type="checkbox" ${row.confirm_duplicate ? 'checked' : ''} onchange="updateProfilingRow(${row.id},'confirm_duplicate',this.checked)"/> I reviewed the possible duplicate and confirm this is a separate resident.</label>
    <label class="resident-standing"><input type="checkbox" ${row.is_head ? 'checked' : ''} onchange="updateProfilingRow(${row.id},'is_head',this.checked)"/> Household head</label>
    <div class="resident-detail-grid">${profilingPreview.fields.filter(field => field !== 'is_household_head').map(field => `<div class="form-group"><label class="form-label" for="profiling-row-${row.id}-${field}">${escapeText(field.replaceAll('_', ' '))}</label><input class="form-input" id="profiling-row-${row.id}-${field}" maxlength="1000" value="${escapeText(row.fields[field] || '')}" oninput="updateProfilingRow(${row.id},'${field}',this.value)"/></div>`).join('')}</div></details>`;
}

function profilingReviewPayload(resetRows = false) {
  const mapping = {};
  profilingPreview.fields.forEach(field => {
    const value = document.getElementById(`profiling-map-${field}`).value;
    mapping[field] = value === '' ? null : Number(value);
  });
  return { token: profilingPreview.token, mapping, purok: document.getElementById('profiling-purok').value || null, rows: resetRows ? [] : profilingPreview.rows.map(row => ({ id: row.id, fields: row.fields, is_head: row.is_head, action: row.action, resident_id: row.resident_id, confirm_duplicate: row.confirm_duplicate })) };
}

async function reviewHouseholdProfiling(resetRows = false) {
  if (!profilingPreview || profilingBusy) return;
  resetRows = resetRows || profilingMappingChanged;
  if (resetRows && !confirm('Rebuild the preview from the selected columns? Current row corrections will be reset.')) return;
  invalidateProfilingPreview();
  setProfilingBusy(true);
  try {
    const result = await profilingRequest('/admin/resident-profiling-imports/review', profilingReviewPayload(resetRows));
    profilingPreview = { ...profilingPreview, ...result };
    profilingValidated = true;
    profilingMappingChanged = false;
    renderProfilingPreview();
  } catch (error) { invalidateProfilingPreview(); document.getElementById('profiling-errors').textContent = error.message; }
  finally { setProfilingBusy(false); }
}

async function saveHouseholdProfiling() {
  if (!profilingPreview || profilingBusy || document.getElementById('profiling-save').disabled) return;
  setProfilingBusy(true);
  try {
    const result = await profilingRequest('/admin/resident-profiling-imports', profilingReviewPayload());
    profilingPreview = null;
    closeModal('modal-household-import');
    showToast(`${result.message} Created: ${result.result.created}; linked: ${result.result.linked}; skipped: ${result.result.skipped}.`, 'green');
    await loadResidents(1);
    await loadPuroks();
    await populateManualResidentDropdown();
  } catch (error) { invalidateProfilingPreview(); document.getElementById('profiling-errors').textContent = error.message; }
  finally { setProfilingBusy(false); }
}

let selectedHouseholdPurok = null;
let purokHouseholdListVersion = 0;
let purokHouseholdSearch = '';

async function searchPurokHouseholds() {
  if (selectedHouseholdPurok === null) return;
  purokHouseholdSearch = document.getElementById('purok-households-search').value.trim();
  await loadPurokHouseholds(selectedHouseholdPurok, 1);
}

async function clearPurokHouseholdSearch() {
  document.getElementById('purok-households-search').value = '';
  await searchPurokHouseholds();
}

async function openPurokHouseholds(purokId) {
  const purok = PUROK_DATA.find(item => Number(item.databaseId) === Number(purokId));
  if (!purok) return;
  selectedHouseholdPurok = Number(purokId);
  purokHouseholdSearch = '';
  document.getElementById('purok-households-search').value = '';
  document.getElementById('purok-households-title').textContent = `${purok.label} - Registered Households`;
  closeModal('modal-household-readonly');
  openModal('modal-purok-households');
  await loadPurokHouseholds(purokId);
}

async function loadPurokHouseholds(purokId, page = 1) {
  const version = ++purokHouseholdListVersion;
  const container = document.getElementById('purok-households-tbody');
  const pagination = document.getElementById('purok-households-pagination');
  const status = document.getElementById('purok-households-results');
  const search = purokHouseholdSearch;
  status.textContent = 'Loading households...';
  container.innerHTML = '<tr><td colspan="4" class="resident-table-message">Loading households...</td></tr>';
  pagination.innerHTML = '';
  try {
    const query = new URLSearchParams({ purok_id: purokId, page, per_page: 15 });
    if (search) query.set('search', search);
    const result = await householdApi(`/admin/households?${query}`);
    if (version !== purokHouseholdListVersion || selectedHouseholdPurok !== Number(purokId)) return;
    status.textContent = `${Number(result.total ?? result.data.length)} ${search ? 'matching households' : 'registered households'}`;
    container.innerHTML = result.data.map(household => `<tr onclick="viewDemographicHousehold(${Number(household.id)})"><td><button type="button" class="purok-household-link">${escapeText(household.household_name || household.household_number)}</button>${household.household_name ? `<small class="purok-household-number">${escapeText(household.household_number)}</small>` : ''}</td><td>${escapeText(household.head?.full_name || 'No household head assigned')}</td><td>${escapeText(household.address)}</td><td><span class="purok-household-members">${Number(household.household_size)}</span></td></tr>`).join('') || `<tr><td colspan="4" class="resident-table-message">${search ? 'No households match your search in this purok. Try another family name or clear the search.' : 'No households registered in this purok.'}</td></tr>`;
    if (result.last_page > 1) pagination.innerHTML = `<button class="btn btn-xs" ${result.current_page <= 1 ? 'disabled' : ''} onclick="loadPurokHouseholds(${Number(purokId)},${result.current_page - 1})">Previous</button><span>Page ${Number(result.current_page)} of ${Number(result.last_page)}</span><button class="btn btn-xs" ${result.current_page >= result.last_page ? 'disabled' : ''} onclick="loadPurokHouseholds(${Number(purokId)},${result.current_page + 1})">Next</button>`;
  } catch (error) {
    if (version === purokHouseholdListVersion) {
      status.textContent = 'Unable to load households.';
      container.innerHTML = `<tr><td colspan="4" class="resident-table-message">${escapeText(error.message)}</td></tr>`;
    }
  }
}
