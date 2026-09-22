'use strict';

// ── API Configuration ──
const API = '';

function readCookie(name) {
  const prefix = `${encodeURIComponent(name)}=`;
  const cookie = document.cookie
    .split('; ')
    .find(value => value.startsWith(prefix));

  return cookie
    ? decodeURIComponent(cookie.slice(prefix.length))
    : null;
}

function csrfRequestHeaders() {
  const xsrfToken = readCookie('XSRF-TOKEN');

  if (xsrfToken) {
    return { 'X-XSRF-TOKEN': xsrfToken };
  }

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  return csrfToken
    ? { 'X-CSRF-TOKEN': csrfToken }
    : {};
}
// ═══════════════════════════════════════
// SAMPLE DATA — DOB-based (no RFID in resident record, age computed)
// ═══════════════════════════════════════
const RESIDENTS = [];
let RESIDENT_TOTAL = 0;

const BASE_POPULATION = 0;
const BASE_RESIDENT_SAMPLE_COUNT = RESIDENTS.length;
const SPECIAL_GROUP_META = {
  'Senior Citizen': { icon: '👴', label: 'Senior Citizens', color: 'var(--senior-color)', bg: 'rgba(245,158,11,0.08)', border: 'rgba(245,158,11,0.2)', sub: '(60+)' },
  'PWD': { icon: '♿', label: 'Persons w/ Disability', color: '#A78BFA', bg: 'rgba(139,92,246,0.08)', border: 'rgba(139,92,246,0.2)', sub: 'PWD registered' },
  'Solo Parent': { icon: '👪', label: 'Solo Parents', color: 'var(--blue-400)', bg: 'rgba(42,126,211,0.08)', border: 'rgba(42,126,211,0.2)', sub: 'With solo parent ID' },
  'Indigenous People': { icon: '🌿', label: 'Indigenous People', color: 'var(--green-500)', bg: 'rgba(0,255,106,0.06)', border: 'rgba(0,255,106,0.15)', sub: 'Registered IP' },
  '4Ps Beneficiary': { icon: '💰', label: '4Ps Beneficiaries', color: '#EF4444', bg: 'rgba(239,68,68,0.07)', border: 'rgba(239,68,68,0.18)', sub: 'DSWD-registered' },
  'Teenage Mother': { icon: '👶', label: 'Teenage Mothers', color: '#F472B6', bg: 'rgba(244,114,182,0.07)', border: 'rgba(244,114,182,0.2)', sub: 'Ages 13-19' },
  'Out-of-School Youth': { icon: '🎓', label: 'Out-of-School Youth', color: '#34D399', bg: 'rgba(52,211,153,0.07)', border: 'rgba(52,211,153,0.18)', sub: 'Ages 15-30' },
  'Unemployed Adult': { icon: '💼', label: 'Unemployed Adults', color: '#FB923C', bg: 'rgba(251,146,60,0.07)', border: 'rgba(251,146,60,0.18)', sub: 'Ages 18-60' },
  'Malnourished Child': { icon: '🏥', label: 'Malnourished Children', color: '#F87171', bg: 'rgba(248,113,113,0.07)', border: 'rgba(248,113,113,0.2)', sub: 'Under 13 years old' },
};

function totalPopulation() {
  return BASE_POPULATION + Math.max(0, RESIDENT_TOTAL - BASE_RESIDENT_SAMPLE_COUNT);
}

function getResidentGroups(r) {
  const groups = new Set(r.specialGroups || []);
  if (isSenior(r.dob)) groups.add('Senior Citizen');
  return [...groups];
}

function getCheckedSpecialGroups() {
  return [...document.querySelectorAll('.res-special-group:checked')].map(el => el.value);
}

function setCheckedSpecialGroups(groups = []) {
  document.querySelectorAll('.res-special-group').forEach(el => {
    el.checked = groups.includes(el.value);
  });
}

async function refreshDashboardStats() {
  try {
    const response = await fetch('/admin/dashboard-summary', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Hindi ma-load ang dashboard summary.');
    const summary = payload.summary || {};
    const set = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = Number(value || 0).toLocaleString(); };
    set('dash-stat-residents', summary.active_residents);
    set('dash-stat-issued', summary.issued_certificates);
    set('dash-stat-pending', summary.pending_requests);
    set('dash-stat-incidents', summary.open_incidents);
    document.getElementById('dash-sub-pending').textContent = summary.pending_requests ? `${summary.pending_requests} request na hindi pa tapos` : 'No pending requests';
    document.getElementById('dash-sub-incidents').textContent = summary.open_incidents ? `${summary.open_incidents} open incident report` : 'No open incidents';

    const activity = payload.recent_activity || [];
    const previous = new Map(NOTIFICATIONS.map(item => [item.id, item.read]));
    NOTIFICATIONS.splice(0, NOTIFICATIONS.length, ...activity.map(item => ({
      id: item.detail + item.occurred_at, title: item.title, detail: item.detail, time: item.time,
      read: previous.get(item.detail + item.occurred_at) || false, dot: 'var(--blue-400)',
      screen: item.type === 'incident' ? 'incidents' : 'certificates',
    })));
    renderNotifications(); updateNotifBadge();

    const activityElement = document.getElementById('dash-recent-activity');
    if (activityElement) {
      activityElement.innerHTML = activity.length === 0
        ? '<div style="text-align:center;color:var(--text-muted);font-size:11px;padding:18px 0;">No recent activity.</div>'
        : activity.map(item => `
          <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
            <span style="font-size:16px;flex-shrink:0;">${item.type === 'incident' ? '🚨' : '📋'}</span>
            <div style="flex:1;min-width:0;">
              <div style="font-size:12px;font-weight:600;color:var(--text-primary);">${escapeText(item.title)}</div>
              <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeText(item.detail)}</div>
            </div>
            <div style="font-size:10px;color:var(--text-muted);flex-shrink:0;">${escapeText(item.time)}</div>
          </div>`).join('');
    }
  } catch (error) {
    console.error('Dashboard summary failed:', error);
    showToast(error.message || 'Hindi ma-load ang dashboard.', 'red');
  }
}

function refreshPopulationStats() {
  const total = totalPopulation();
  const households = Math.max(1, Math.ceil(total / 4));
  document.querySelectorAll('[data-target="4812"]').forEach(el => {
    el.dataset.target = total;
    el.textContent = total.toLocaleString();
  });
  document.querySelectorAll('.stat-sub').forEach(el => {
    if (el.textContent.includes('Registered households')) el.textContent = `Registered households: ${households.toLocaleString()}`;
  });
}

function initials(name = '') {
  return name.split(/\s+/).filter(Boolean).map(part => part[0]).join('').slice(0, 2).toUpperCase() || 'ST';
}

// ═══════════════════════════════════════
// AGE UTILITIES
// ═══════════════════════════════════════
function calcAge(dob) {
  const today = new Date();
  const birth = new Date(dob);
  let age = today.getFullYear() - birth.getFullYear();
  const m = today.getMonth() - birth.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
  return age;
}

function isSenior(dob) {
  return calcAge(dob) >= 60;
}

function getAgeGroup(dob) {
  const a = calcAge(dob);
  if (a <= 12)  return '0–12 (Bata)';
  if (a <= 17)  return '13–17 (Kabataan)';
  if (a <= 35)  return '18–35 (Kabataang Adulto)';
  if (a <= 59)  return '36–59 (Gitnang Gulang)';
  return '60+ (Senior Citizens)';
}

// ═══════════════════════════════════════
// PUROK DATA
// ═══════════════════════════════════════
// PUROK_DATA is loaded exclusively from the database via db-connector.js
const PUROK_DATA = [];
let DEMOGRAPHIC_SUMMARY = null;

const PUROK_FALLBACK_COLORS = ['var(--green-500)', 'var(--blue-400)', '#F59E0B', '#A78BFA', '#34D399', '#F472B6'];

function escapeText(value = '') {
  return String(value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function buildPurokKey(number, name) {
  // number param ignored — purok name lang ang ginagamit
  const cleanName = String(name || number || '').trim().replace(/\s+/g, ' ');
  return cleanName;
}

function buildPurokLabel(number, name) {
  // number param ignored — purok name lang ang ginagamit
  const cleanName = String(name || number || '').trim().replace(/\s+/g, ' ');
  return cleanName;
}

function addPurokToState(purok, persistLocal = true) {
  if (!purok?.key || PUROK_DATA.some(p => p.key.toLowerCase() === purok.key.toLowerCase())) return false;
  PUROK_DATA.push({
    key: purok.key,
    label: purok.label || purok.key,
    total: Number(purok.total || 0),
    color: purok.color || PUROK_FALLBACK_COLORS[PUROK_DATA.length % PUROK_FALLBACK_COLORS.length],
    pct: Number(purok.pct || 0),
  });
  if (persistLocal) persistCustomPuroks();
  syncPurokSelects();
  return true;
}

// persistCustomPuroks and loadCustomPuroks disabled — purok data comes from DB only
function persistCustomPuroks() { /* disabled — DB is source of truth */ }
function loadCustomPuroks() {
  // Clear any leftover localStorage puroks from old version
  try { localStorage.removeItem('smartbrgy_custom_puroks'); } catch(e) {}
}

function syncPurokSelects(selectedValue = '') {
  document.querySelectorAll('#res-purok').forEach(select => {
    const current = selectedValue || select.value;
    select.innerHTML = '';
    if (PUROK_DATA.length === 0) {
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = '— Walang purok. Mag-add muna sa Demographics. —';
      placeholder.disabled = true;
      select.appendChild(placeholder);
      return;
    }
    PUROK_DATA.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p.key;
      opt.textContent = p.label || p.key;
      select.appendChild(opt);
    });
    if (current && PUROK_DATA.some(p => p.key === current)) select.value = current;
  });
}

function openAddPurok() {
  const modal = document.getElementById('modal-purok');
  if (modal) delete modal.dataset.editKey;
  const nameEl = document.getElementById('purok-name');
  const labelEl = document.getElementById('purok-label');
  const colorEl = document.getElementById('purok-color');
  if (nameEl) { nameEl.value = ''; nameEl.disabled = false; }
  if (labelEl) { labelEl.value = ''; labelEl.disabled = false; }
  if (colorEl) colorEl.value = '#22C55E';
  const title = modal?.querySelector('.modal-title');
  if (title) title.textContent = '🏘️ Add Purok';
  const saveBtn = modal?.querySelector('button.btn-green');
  if (saveBtn) saveBtn.textContent = '💾 Save Purok';
  openModal('modal-purok');
}

async function savePurok() {
  const name = document.getElementById('purok-label')?.value.trim()
    || document.getElementById('purok-name')?.value.trim();
  const color = document.getElementById('purok-color')?.value || '#22C55E';

  if (!name) {
    showToast('Enter a purok display name.', 'red');
    return;
  }

  try {
    const response = await fetch('/admin/puroks', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...csrfRequestHeaders() },
      body: JSON.stringify({ name, color })
    });
    const payload = await response.json();
    if (!response.ok) {
      const message = payload?.errors ? Object.values(payload.errors).flat()[0] : payload?.message;
      throw new Error(message || 'Unable to add the purok.');
    }
    closeModal('modal-purok');
    showToast(payload.message, 'green');
    await loadPuroks();
  } catch (error) {
    showToast(error.message, 'red');
  }
}

async function loadPuroks() {
  try {
    const response = await fetch('/admin/puroks', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Unable to load demographics.');

    PUROK_DATA.splice(0, PUROK_DATA.length, ...payload.data.map(purok => ({
      key: purok.name,
      label: purok.name,
      color: purok.color,
      residentsCount: Number(purok.residents_count || 0),
      seniorCount: Number(purok.senior_count || 0),
      pwdCount: Number(purok.pwd_count || 0),
      fourPsCount: Number(purok.four_ps_count || 0)
    })));
    DEMOGRAPHIC_SUMMARY = payload.demographics;
    syncPurokSelects();
    renderDemographics();
    renderDashPurokBreakdown();
    return true;
  } catch (error) {
    console.error('Purok loading failed:', error);
    showToast(error.message || 'Unable to load demographics.', 'red');
    return false;
  }
}

async function refreshDemographics() {
  const refreshed = await loadPuroks();

  if (refreshed) {
    showToast('Demographics data refreshed.', 'green');
  }
}

loadCustomPuroks();

// ═══════════════════════════════════════
// CERTIFICATE TYPES
// ═══════════════════════════════════════
const CERTIFICATE_TYPES = [
  { id: 'BC',   label: 'Barangay Clearance',        icon: '📄', fee: 'PHP 50.00',  days: '1 day' },
  { id: 'CR',   label: 'Certificate of Residency',  icon: '🏠', fee: 'PHP 50.00',  days: '1 day' },
  { id: 'CI',   label: 'Certificate of Indigency',  icon: '📋', fee: 'Free',       days: '1 day' },
  { id: 'BID',  label: 'Barangay ID',               icon: '🪪', fee: 'PHP 100.00', days: '3-5 days' },
  { id: 'CTFJ', label: 'First Time Jobseeker',      icon: '💼', fee: 'Free',       days: '1 day' },
  { id: 'BBC',  label: 'Business Clearance',        icon: '🏪', fee: 'PHP 200.00+',days: '3-5 days' },
];

// ═══════════════════════════════════════
// RFID TAGS — DOCUMENT/FOLDER TRACKING (no rfid in resident record)
// ═══════════════════════════════════════
const RFID_TAGS = [];

const CABINET_FOLDERS = [];

const CABINET_DRAWERS = [
  { id: 'CA1', label: 'Row A - Drawer 1', category: 'Resident Files A–E', rfid: 'RF-CA1', locked: false, icon: '🗂️' },
  { id: 'CA2', label: 'Row A - Drawer 2', category: 'Resident Files F–L', rfid: 'RF-CA2', locked: false, icon: '🗂️' },
  { id: 'CB1', label: 'Row B - Drawer 1', category: 'Resident Files M–R', rfid: 'RF-CB1', locked: true,  icon: '🗂️' },
  { id: 'CB2', label: 'Row B - Drawer 2', category: 'Resident Files S–Z', rfid: 'RF-CB2', locked: true,  icon: '🗂️' },
  { id: 'CC1', label: 'Row C - Drawer 1', category: 'Clearances & Certificates', rfid: 'RF-CC1', locked: false, icon: '📋' },
  { id: 'CC2', label: 'Row C - Drawer 2', category: 'Incident Reports', rfid: 'RF-CC2', locked: true,  icon: '🚨' },
  { id: 'CD1', label: 'Row D - Drawer 1', category: 'Business Clearances', rfid: 'RF-CD1', locked: true, icon: '🏪' },
  { id: 'CD2', label: 'Row D - Drawer 2', category: 'Sensitive Records',   rfid: 'RF-CD2', locked: true, icon: '🔒' },
];

const INCIDENTS = [];
let incidentCurrentPage = 1;
let incidentLastPage = 1;
let incidentSearchTimer = null;

const CERT_REQUESTS = [];

const AUDIT_LOGS = [];

const USERS = [];

// ═══════════════════════════════════════
// RESIDENT STATUS
// ═══════════════════════════════════════
const RESIDENT_STATUS = {};

const REQUEST_RECORDS = [];

const ELIGIBILITY_RULES = {
  'BC':   { label: 'Barangay Clearance',       needsGoodStanding: true,  oneTimeOnly: false, requiresActive: true },
  'CR':   { label: 'Certificate of Residency', needsGoodStanding: false, oneTimeOnly: false, requiresActive: true },
  'CI':   { label: 'Certificate of Indigency', needsGoodStanding: false, oneTimeOnly: false, requiresActive: true },
  'BID':  { label: 'Barangay ID',              needsGoodStanding: false, oneTimeOnly: false, requiresActive: true },
  'CTFJ': { label: 'First Time Jobseeker',     needsGoodStanding: false, oneTimeOnly: true,  requiresActive: true },
  'BBC':  { label: 'Business Clearance',       needsGoodStanding: true,  oneTimeOnly: false, requiresActive: true },
};

// ═══════════════════════════════════════
// THEME TOGGLE (Light / Dark Mode)
// ═══════════════════════════════════════
let isLightMode = true;

function toggleTheme() {
  isLightMode = !isLightMode;
  document.body.classList.toggle('light-mode', isLightMode);
  const icon = document.getElementById('theme-icon');
  const label = document.getElementById('theme-label');
  if (icon)  icon.textContent  = isLightMode ? '🌙' : '☀️';
  if (label) label.textContent = isLightMode ? 'Dark Mode' : 'Light Mode';
  try { localStorage.setItem('smartbrgy_theme', isLightMode ? 'light' : 'dark'); } catch (_) {}
  showToast(isLightMode ? '☀️ Light Mode na!' : '🌙 Dark Mode na!', 'green');
}

function changeFontSize(dir) {
  const current = parseFloat(getComputedStyle(document.body).fontSize);
  if (dir === 0) { document.body.style.fontSize = '13.5px'; showToast('Font size reset.', ''); return; }
  const newSize = Math.min(Math.max(current + dir * 1.5, 11), 18);
  document.body.style.fontSize = newSize + 'px';
  showToast(`Font size: ${newSize.toFixed(0)}px`, '');
}

// ── Current logged-in user access ──
let currentUserAccess = 'Full';
let currentUserRole   = 'Super Administrator';
let currentUserName   = '';

// ═══════════════════════════════════════
// NAVIGATION
// ═══════════════════════════════════════
function findNavItem(screenId) {
  return [...document.querySelectorAll('.nav-item')]
    .find(item => item.getAttribute('onclick')?.includes(`showScreen('${screenId}'`)) || null;
}

function showScreen(id, el) {
  // Role-based guard
  const screenPermMap = {
    'dashboard': 'Dashboard', 'demographics': 'Records',
    'records': 'Records', 'voters': 'Records', 'certificates': 'Certificates',
    'request-records': 'Requests', 'incidents': 'Incidents',
    'rfid': 'RFID', 'cabinet': 'Cabinet', 'qr': 'QR',
    'face': 'Face', 'audit': 'Audit', 'users': 'Users', 'settings': 'Settings'
  };
  const needed = screenPermMap[id];
  const allowed = ACCESS_PERMS[currentUserAccess] || ACCESS_PERMS['View Only'];
  if (needed && !allowed.includes(needed)) {
    showToast(`🚫 Walang access sa "${needed}". Makipag-ugnayan sa Admin.`, 'red');
    return;
  }
  document.querySelectorAll('.content').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const screen = document.getElementById('screen-' + id);
  if (screen) screen.classList.add('active');
  if (el) el.classList.add('active');
  try { localStorage.setItem('smartbrgy_active_screen', id); } catch (_) {}
  if (screen) { screen.setAttribute('tabindex', '-1'); screen.focus({ preventScroll: true }); }
  document.querySelectorAll('.nav-item').forEach(item => item.setAttribute('aria-current', item === el ? 'page' : 'false'));
  showLoadingBar();
  if (id === 'dashboard') refreshDashboardStats();
  if (id === 'audit') {
    if (typeof reloadAuditLog === 'function') reloadAuditLog();
    else renderAuditLog();
  }
  if (id === 'users') void reloadUsers();
  toggleNavigation(false);
  if (id === 'request-records') void loadRequestRecords(1);
  if (id === 'certificates') {
    renderCertKanban();
    const badge = document.getElementById('cert-nav-badge');
    if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }
  }
  if (id === 'demographics') void loadPuroks();
  if (id === 'voters') loadVoterRegistry(voterCurrentPage);
  if (id === 'incidents') void loadIncidents(incidentCurrentPage);
}

function applyAccessControl(access) {
  currentUserAccess = access || 'Full';
  const allowed = ACCESS_PERMS[currentUserAccess] || ACCESS_PERMS['View Only'];
  document.querySelectorAll('.nav-item[data-perm]').forEach(item => {
    const perm = item.getAttribute('data-perm');
    if (perm && !allowed.includes(perm)) {
      item.style.display = 'none';
    } else {
      item.style.display = '';
    }
  });
}

// ═══════════════════════════════════════
// ═══════════════════════════════════════
// LOGIN — REAL-TIME CLOCK, PARTICLES, FACE SCAN
// ═══════════════════════════════════════

// ── Real-time login clock ──
function startLoginClock() {
  const tick = () => {
    const now = new Date();
    const c = document.getElementById('login-clock');
    const d = document.getElementById('login-date');
    if (c) c.textContent = now.toLocaleTimeString('en-PH', { hour12: false });
    if (d) d.textContent = now.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  };
  tick();
  setInterval(tick, 1000);
}

// ── Stat ticker ──
const TICKER_LINES = [
  'SmartBrgy System: <strong style="color:rgba(0,255,106,0.85);">Online</strong>&ensp;|&ensp;RFID Cabinet: 2FA Active&ensp;|&ensp;All systems normal',
  'Online Portal: <strong style="color:rgba(0,255,106,0.85);">Active</strong>&ensp;|&ensp;5 Active Puroks',
];
let tickerIdx = 0;
function startTicker() {
  const el = document.getElementById('login-ticker-text');
  if (!el) return;
  el.innerHTML = TICKER_LINES[0];
  setInterval(() => {
    tickerIdx = (tickerIdx + 1) % TICKER_LINES.length;
    el.style.opacity = '0';
    el.style.transform = 'translateY(5px)';
    setTimeout(() => {
      el.innerHTML = TICKER_LINES[tickerIdx];
      el.style.opacity = '1';
      el.style.transform = 'translateY(0)';
    }, 360);
  }, 3800);
}

// ── Floating particles ──
function initLoginParticles() {
  const c = document.getElementById('login-particles');
  if (!c) return;
  if (!document.getElementById('particle-kf')) {
    const s = document.createElement('style');
    s.id = 'particle-kf';
    s.textContent = '@keyframes floatUp{0%{transform:translateY(0) scale(1);opacity:0}8%{opacity:1}85%{opacity:.4}100%{transform:translateY(-100vh) scale(.3);opacity:0}}';
    document.head.appendChild(s);
  }
  for (let i = 0; i < 20; i++) {
    const p = document.createElement('div');
    const sz = Math.random() * 2.5 + 1;
    const op = 0.1 + Math.random() * 0.18;
    p.style.cssText = `position:absolute;width:${sz}px;height:${sz}px;border-radius:50%;left:${Math.random()*100}%;bottom:-8px;background:rgba(0,255,106,${op});box-shadow:0 0 ${sz*4}px rgba(0,255,106,.25);animation:floatUp ${12+Math.random()*14}s ${Math.random()*10}s infinite linear;pointer-events:none;`;
    c.appendChild(p);
  }
}


const VALID_CREDENTIALS = [];

function isStrongPassword(pw) {
  // min 8 chars, at least one uppercase, lowercase, digit, special char
  return pw.length >= 8
    && /[A-Z]/.test(pw)
    && /[a-z]/.test(pw)
    && /[0-9]/.test(pw)
    && /[^A-Za-z0-9]/.test(pw);
}

function fillSample() {
  showToast('Use your authorized Laravel staff account.', '');
}

async function doLoginCreds() {
  const empId = (document.getElementById('login-empid') || {}).value?.trim() || '';
  const uname = (document.getElementById('login-user') || {}).value?.trim() || '';
  const pw    = (document.getElementById('login-pass') || {}).value || '';

  if (!empId) { showToast('Please enter your Employee ID.', 'red'); return; }
  if (!uname) { showToast('Please enter your username.', 'red'); return; }
  if (!pw)    { showToast('Please enter your password.', 'red'); return; }

  // 1. Check hardcoded credentials first
  const match = VALID_CREDENTIALS.find(c =>
    c.empId === empId && c.username === uname && c.password === pw
  );
  if (match) { _doLoginSuccess(match.name, match.role); return; }

  // 2. Check database (para sa mga bagong nai-add na users)
  showToast('Invalid Employee ID, username, or password. Please check your credentials.', 'red');
}

function _doLoginSuccess(name, role) {
  const btn = document.getElementById('lp-login-btn');
  if (btn) { btn.textContent = 'AUTHENTICATING...'; btn.disabled = true; }
  setTimeout(() => {
    if (btn) { btn.textContent = 'SECURE LOGIN'; btn.disabled = false; }
    launchApp(name, role);
  }, 1000);
}

// Init on page load
window.addEventListener('DOMContentLoaded', () => {
  if (window.AUTHENTICATED_USER) return;

  startLoginClock();
  startTicker();
  initLoginParticles();
});


function legacyLaunchApp(name, role) {
  currentUserName = name || 'Staff';
  currentUserRole = role || 'Staff';
  // Map role → access level
  const user = USERS.find(u => u.name === name) || VALID_CREDENTIALS.find(c => c.name === name);
  const dbUser = USERS.find(u => u.name === name);
  currentUserAccess = dbUser?.access || (() => {
    const roleAccessMap = {
      'Super Administrator': 'Full Access',
      'Barangay Captain':    'Full Access',
      'Barangay Secretary':  'Full Access',
      'Records Officer':     'Records & Certificates',
      'Barangay Clerk':      'Certificates Only',
      'Tanod Captain':       'Incidents Only',
      'Data Encoder':        'View Only',
    };
    return roleAccessMap[role] || 'View Only';
  })();

  document.getElementById('login-screen').style.display = 'none';
  const app = document.getElementById('app');
  app.classList.add('visible');

// Apply nav access control
applyAccessControl(currentUserAccess);

// Update user display in sidebar if element exists
const userNameEl = document.getElementById('sidebar-user-name');
const userRoleEl = document.getElementById('sidebar-user-role');

if (userNameEl) userNameEl.textContent = name || 'Staff';
if (userRoleEl) userRoleEl.textContent = role || '';

startClock();

showScreen('dashboard');

}
let logoutPending = false;

function doLogout() {
  const dialog = document.getElementById('logout-dialog');
  if (!dialog || dialog.open) return;
  document.getElementById('logout-error').hidden = true;
  dialog.showModal();
}

function cancelLogout() {
  if (!logoutPending) document.getElementById('logout-dialog').close();
}

async function confirmLogout() {
  const dialog = document.getElementById('logout-dialog');
  if (!dialog?.open || logoutPending) return;
  const error = document.getElementById('logout-error');
  const confirmButton = document.getElementById('logout-confirm');
  logoutPending = true;
  error.hidden = true;
  dialog.setAttribute('aria-busy', 'true');
  dialog.querySelectorAll('button').forEach(button => button.disabled = true);
  confirmButton.textContent = 'Logging out...';

  try {
    const response = await fetch(dialog.dataset.logoutUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', ...csrfRequestHeaders() }
    });
    if (!response.ok) {
      throw new Error(response.status === 419
        ? 'Your session has expired. Refresh this page and try again.'
        : 'Unable to log out. Please try again.');
    }
    try { localStorage.removeItem('smartbrgy_active_screen'); } catch (_) {}
    window.location.href = dialog.dataset.loginUrl;
  } catch (failure) {
    error.textContent = failure instanceof TypeError
      ? 'Unable to connect. Check your connection and try again.'
      : failure.message || 'Unable to log out. Please try again.';
    error.hidden = false;
  } finally {
    logoutPending = false;
    dialog.removeAttribute('aria-busy');
    dialog.querySelectorAll('button').forEach(button => button.disabled = false);
    confirmButton.textContent = 'Log out';
  }
}

// ═══════════════════════════════════════
// CLOCK
// ═══════════════════════════════════════
let clockTimer = null;

function startClock() {
  function tick() {
    const el = document.getElementById('clock-display');
    if (el) el.textContent = new Date().toLocaleTimeString('en-PH', { hour12: false });
  }
  tick();
  if (clockTimer === null) clockTimer = setInterval(tick, 1000);
}

// ═══════════════════════════════════════
// COUNTERS
// ═══════════════════════════════════════
function runCounters() {
  document.querySelectorAll('.counter').forEach(el => {
    const target = parseInt(el.dataset.target);
    let cur = 0;
    const step = Math.ceil(target / 60);
    const iv = setInterval(() => {
      cur = Math.min(cur + step, target);
      el.textContent = cur.toLocaleString();
      if (cur >= target) clearInterval(iv);
    }, 18);
  });
}

// ═══════════════════════════════════════
// CHARTS — real-time donut chart
// ═══════════════════════════════════════
function buildCharts() {
  const container = document.getElementById('dash-chart');
  if (!container) return;

  const palette = ['#22C55E','#3B82F6','#F59E0B','#A78BFA','#FB923C','#EF4444','#60A5FA','#34D399'];
  const reqs = (typeof CERT_REQUESTS !== 'undefined') ? CERT_REQUESTS : [];
  const counts = {};
  reqs.forEach(r => {
    const key = r.type || 'Iba pa';
    counts[key] = (counts[key] || 0) + 1;
  });

  const labels = Object.keys(counts);
  const values = labels.map(k => counts[k]);
  const total  = values.reduce((a, b) => a + b, 0);

  container.innerHTML = '';
  container.style.cssText = 'display:flex;align-items:center;gap:16px;height:auto;padding:4px 0;';

  if (total === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:12px;width:100%;padding:20px 0;">Walang certificate request pa.</div>';
    return;
  }

  // Canvas donut
  const canvas = document.createElement('canvas');
  canvas.width  = 120;
  canvas.height = 120;
  canvas.style.flexShrink = '0';
  container.appendChild(canvas);

  const ctx = canvas.getContext('2d');
  let angle = -Math.PI / 2;
  values.forEach((v, i) => {
    const slice = (v / total) * 2 * Math.PI;
    ctx.beginPath();
    ctx.moveTo(60, 60);
    ctx.arc(60, 60, 52, angle, angle + slice);
    ctx.closePath();
    ctx.fillStyle = palette[i % palette.length];
    ctx.fill();
    angle += slice;
  });

  // Hole
  const bg = getComputedStyle(document.body).getPropertyValue('--bg-panel').trim() || '#ffffff';
  ctx.beginPath();
  ctx.arc(60, 60, 30, 0, 2 * Math.PI);
  ctx.fillStyle = bg || '#1a1f2e';
  ctx.fill();

  // Center total
  ctx.fillStyle = getComputedStyle(document.body).getPropertyValue('--text-primary').trim() || '#fff';
  ctx.font = 'bold 18px sans-serif';
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillText(total, 60, 57);
  ctx.font = '9px sans-serif';
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-muted').trim() || '#aaa';
  ctx.fillText('total', 60, 71);

  // Legend
  const legend = document.createElement('div');
  legend.style.cssText = 'display:flex;flex-direction:column;gap:5px;flex:1;min-width:0;';
  labels.forEach((label, i) => {
    const pct = Math.round(values[i] / total * 100);
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:6px;font-size:11px;';
    row.innerHTML = `
      <div style="width:9px;height:9px;border-radius:2px;background:${palette[i % palette.length]};flex-shrink:0;"></div>
      <span style="color:var(--text-secondary);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${label}</span>
      <span style="color:var(--text-primary);font-weight:600;flex-shrink:0;">${values[i]} (${pct}%)</span>`;
    legend.appendChild(row);
  });
  container.appendChild(legend);
}

// ═══════════════════════════════════════
// DASHBOARD PUROK BREAKDOWN
// ═══════════════════════════════════════
function renderDashPurokBreakdown() {
  const container = document.getElementById('dash-purok-breakdown');
  if (!container) return;
  syncPurokSelects();
  container.innerHTML = '';
  const countByPurok = {};
  RESIDENTS.forEach(r => { countByPurok[r.purok] = (countByPurok[r.purok] || 0) + 1; });
  const total = Number(DEMOGRAPHIC_SUMMARY?.total ?? RESIDENTS.length);
  PUROK_DATA.forEach(p => {
    const count = Number(p.residentsCount ?? countByPurok[p.key] ?? 0);
    const demographicTotal = Number(DEMOGRAPHIC_SUMMARY?.total ?? total);
    const pct = demographicTotal > 0 ? Math.round(count / demographicTotal * 100) : 0;
    container.innerHTML += `
      <div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
          <span style="color:var(--text-secondary)">${escapeText(p.label)}</span>
          <span style="color:var(--text-primary);font-weight:600">${count.toLocaleString()}</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:${p.color}"></div></div>
      </div>`;
  });
}

// ═══════════════════════════════════════
// DEMOGRAPHICS
// ═══════════════════════════════════════
function legacyRenderDemographics() {
  renderPurokCards();
  renderAgeDistribution();
  renderSeniorList();
}

function legacyRenderPurokCards() {
  const grid = document.getElementById('demo-purok-grid');
  if (!grid) return;
  syncPurokSelects();
  grid.innerHTML = '';
  const countByPurok = {};
  const seniorByPurok = {};
  RESIDENTS.forEach(r => {
    countByPurok[r.purok] = (countByPurok[r.purok] || 0) + 1;
    if (isSenior(r.dob)) seniorByPurok[r.purok] = (seniorByPurok[r.purok] || 0) + 1;
  });
  const total = RESIDENTS.length;
  PUROK_DATA.forEach(p => {
    const count = countByPurok[p.key] || 0;
    const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0';
    const barPct = total > 0 ? (count / total * 100) : 0;
    const seniorCount = seniorByPurok[p.key] || 0;
    grid.innerHTML += `
      <div class="demo-purok-card">
        <div class="demo-purok-name">📍 ${escapeText(p.label)}</div>
        <div class="demo-purok-pop">${count.toLocaleString()}</div>
        <div class="demo-purok-pct">${pct}% ng total population</div>
        ${seniorCount > 0 ? `<div class="demo-purok-senior">👴 ${seniorCount} Senior Citizen${seniorCount > 1 ? 's' : ''}</div>` : ''}
        <div class="demo-purok-bar">
          <div class="progress-bar"><div class="progress-fill" style="width:${barPct}%;background:${p.color}"></div></div>
        </div>
      </div>`;
  });
}

function legacyRenderAgeDistribution() {
  const list = document.getElementById('age-distribution-list');
  if (!list) return;
  const groups = [
    { label: '0–12 (Bata)',             color: '#60A5FA',             min: 0,  max: 12  },
    { label: '13–17 (Kabataan)',         color: 'var(--green-500)',    min: 13, max: 17  },
    { label: '18–35 (Kabataang Adulto)', color: 'var(--green-500)',    min: 18, max: 35  },
    { label: '36–59 (Gitnang Gulang)',   color: 'var(--blue-400)',     min: 36, max: 59  },
    { label: '60+ (Senior Citizens)',    color: 'var(--senior-color)', min: 60, max: 999 },
  ];
  const counts = groups.map(g => ({ ...g, count: 0 }));
  RESIDENTS.forEach(r => {
    const age = calcAge(r.dob);
    const g = counts.find(x => age >= x.min && age <= x.max);
    if (g) g.count++;
  });
  const total = RESIDENTS.length;
  list.innerHTML = '';
  counts.forEach(g => {
    const pct = total > 0 ? ((g.count / total) * 100).toFixed(1) : '0.0';
    const barPct = total > 0 ? (g.count / total * 100 * 2.8) : 0;
    list.innerHTML += `
      <div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
          <span style="color:var(--text-secondary)">${g.label}</span>
          <span style="color:var(--text-primary);font-weight:600">${g.count.toLocaleString()} (${pct}%)</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:${barPct}%;background:${g.color}"></div></div>
      </div>`;
  });
}

function legacyRenderSeniorList() {
  const container = document.getElementById('senior-citizens-list');
  if (!container) return;
  const seniors = RESIDENTS.filter(r => isSenior(r.dob));
  // Update count
  const countEl = document.getElementById('demo-senior-count');
  if (countEl) countEl.textContent = seniors.length;

  if (seniors.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:20px;">Walang senior citizen sa sample data.</div>';
    return;
  }
  container.innerHTML = `
    <table class="tbl">
      <thead><tr><th>Resident ID</th><th>Pangalan</th><th>Edad</th><th>Kaarawan</th><th>Purok</th><th>Status</th><th>Senior Badge</th></tr></thead>
      <tbody>${seniors.map(r => {
        const age = calcAge(r.dob);
        return `<tr>
          <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--blue-400);">${r.id}</span></td>
          <td><strong style="color:var(--text-primary);">${r.name}</strong></td>
          <td><span style="font-weight:700;color:var(--senior-color);font-size:14px;">${age}</span></td>
          <td style="font-size:11.5px;">${r.dob}</td>
          <td>${r.purok}</td>
          <td><span class="badge ${r.status === 'Active' ? 'badge-green' : 'badge-red'}">${r.status}</span></td>
          <td><span class="badge badge-senior">👴 Senior Citizen</span></td>
        </tr>`;
      }).join('')}</tbody>
    </table>`;
}

// ═══════════════════════════════════════
// RESIDENTS TABLE
// ═══════════════════════════════════════
function legacyRenderResidentsTable(filter = '', statusFilter = '') {
  const tbody = document.getElementById('records-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  RESIDENTS
    .filter(r => {
      const matchText = !filter || r.name.toLowerCase().includes(filter.toLowerCase()) || r.id.toLowerCase().includes(filter.toLowerCase()) || r.purok.toLowerCase().includes(filter.toLowerCase());
      const senior = isSenior(r.dob);
      const matchStatus = !statusFilter
        || (statusFilter === 'Senior' && senior)
        || (statusFilter !== 'Senior' && r.status === statusFilter);
      return matchText && matchStatus;
    })
    .forEach(r => {
      const age = calcAge(r.dob);
      const senior = age >= 60;
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--blue-400);">${r.id}</span></td>
        <td>
          <strong style="color:var(--text-primary);">${r.name}</strong>
          ${senior ? '<span class="badge badge-senior" style="margin-left:5px;font-size:9px;">👴 Senior</span>' : ''}
        </td>
        <td><span style="font-weight:700;color:${senior ? 'var(--senior-color)' : 'var(--text-primary)'};">${age}</span></td>
        <td>${r.purok}</td>
        <td>${r.gender}</td>
        <td>${r.civil}</td>
        <td><span class="badge ${r.status === 'Active' ? 'badge-green' : 'badge-red'}">${r.status}</span></td>
        <td>
          <button class="btn btn-xs btn-primary" onclick="openViewResident('${r.id}')">👁 View</button>
          <button class="btn btn-xs" onclick="openEditResident('${r.id}')">✏️ Edit</button>
          <button class="btn btn-xs btn-danger" style="margin-left:8px;" onclick="deleteResident('${r.id}')">🗑 Delete</button>
        </td>`;
      tbody.appendChild(tr);
    });
}

function legacyBrowserFilterResidents() {
  const q = document.getElementById('residents-search')?.value || '';
  renderResidentsTable(q);
}

function legacyDeleteResident(id) {
  if (!confirm('Sigurado ka bang tanggalin ang resident record na ito? Hindi na ito mababawi.')) return;
  const idx = RESIDENTS.findIndex(r => r.id === id);
  if (idx === -1) return;
  const name = RESIDENTS[idx].name;
  RESIDENTS.splice(idx, 1);
  delete RESIDENT_STATUS[id];
  for (let i = REQUEST_RECORDS.length - 1; i >= 0; i--) {
    if (REQUEST_RECORDS[i].residentId === id) REQUEST_RECORDS.splice(i, 1);
  }
  addLiveAuditEntry('🗑️', 'record', 'Resident Record Deleted', `${id} — ${name}`, currentUserName || 'Staff');
  showToast(`Resident record ni ${name} ay natanggal.`, '');
  renderResidentsTable();
  renderDashPurokBreakdown();
  refreshPopulationStats();
}

function legacyFilterResidentStatus(val, el) {
  document.querySelectorAll('#screen-records .status-pill').forEach(p => p.classList.remove('active'));
  if (el) el.classList.add('active');
  renderResidentsTable('', val);
}

function legacyOpenEditResident(id) {
  const r = RESIDENTS.find(x => x.id === id);
  if (!r) { showToast('Resident not found.', 'red'); return; }
  syncPurokSelects(r.purok);

  // Set modal title
  const titleEl = document.getElementById('modal-resident-title');
  if (titleEl) titleEl.innerHTML = '✏️ <span>I-edit ang Resident Record</span>';

  // Populate fields
  const nameParts = r.name.split(' ');
  const lastName = nameParts.length > 1 ? nameParts[nameParts.length - 1] : '';
  const firstName = nameParts.length > 1 ? nameParts.slice(0, -1).join(' ') : r.name;

  const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
  setVal('res-lastname', lastName);
  setVal('res-name', firstName);
  setVal('res-dob', r.dob);
  setVal('res-contact', r.contact);
  setVal('res-address', r.address || '');
  setVal('res-edit-id', r.id);

  // Dropdowns
  const setSelect = (id, val) => {
    const el = document.getElementById(id);
    if (!el) return;
    for (let opt of el.options) { if (opt.value === val || opt.text === val) { el.value = opt.value; break; } }
  };
  setSelect('res-gender', r.gender);
  setSelect('res-civil', r.civil);
  setSelect('res-purok', r.purok);
  setSelect('res-type', r.type);
  setCheckedSpecialGroups(r.specialGroups || []);

  openModal('modal-resident');
}

function legacyBrowserOpenAddResident() {
  syncPurokSelects();
  // Reset title and fields for adding a new resident
  const titleEl = document.getElementById('modal-resident-title');
  if (titleEl) titleEl.innerHTML = '➕ <span>I-register ang Bagong Resident</span>';
  ['res-lastname','res-name','res-dob','res-contact','res-address','res-edit-id'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  setCheckedSpecialGroups([]);
  openModal('modal-resident');
}

function legacySaveResident() {
  const name = document.getElementById('res-name')?.value?.trim();
  const lastName = document.getElementById('res-lastname')?.value?.trim();
  const dob = document.getElementById('res-dob')?.value;
  const editId = document.getElementById('res-edit-id')?.value;
  if (!name) { showToast('Please fill in the required fields.', 'red'); return; }

  const fullName = (name + (lastName ? ' ' + lastName : '')).trim();
  const contact = document.getElementById('res-contact')?.value || '';
  const gender = document.getElementById('res-gender')?.value || 'Male';
  const civil = document.getElementById('res-civil')?.value || 'Single';
  const purok = document.getElementById('res-purok')?.value || 'Purok 1 - Sampaguita';
  const type = document.getElementById('res-type')?.value || 'Homeowner';

  if (editId) {
    // EDIT existing
    const idx = RESIDENTS.findIndex(x => x.id === editId);
    if (idx >= 0) {
      RESIDENTS[idx].name = fullName;
      if (dob) RESIDENTS[idx].dob = dob;
      RESIDENTS[idx].contact = contact;
      RESIDENTS[idx].gender = gender;
      RESIDENTS[idx].civil = civil;
      RESIDENTS[idx].purok = purok;
      RESIDENTS[idx].type = type;
      showToast('✅ Record updated successfully: ' + fullName, 'green');
      renderResidentsTable();
    }
  } else {
    // ADD new
    const newId = 'ANB-' + String(RESIDENTS.length + 1).padStart(4, '0');
    RESIDENTS.push({ id: newId, name: fullName, purok, dob: dob || '2000-01-01', gender, civil, contact, status: 'Active', household: 'HH-NEW', type });
    RESIDENT_STATUS[newId] = { blotter: false, blotterDetails: [], goodStanding: true, notes: '' };
    showToast('✅ Resident registered: ' + fullName + ' (' + newId + ')', 'green');
    renderResidentsTable();
  }
  closeModal('modal-resident');
}

// ═══════════════════════════════════════
// VIEW RESIDENT
// ═══════════════════════════════════════
let currentViewResidentId = null;
function legacyOpenViewResident(id) {
  currentViewResidentId = id;
  const r = RESIDENTS.find(x => x.id === id);
  if (!r) return;
  const rs = RESIDENT_STATUS[id];
  const age = calcAge(r.dob);
  const senior = age >= 60;
  const blotterHtml = rs?.blotter
    ? `<div style="grid-column:1/-1;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);padding:10px 12px;font-size:12px;"><strong style="color:#FCA5A5;">⚠️ Blotter Record:</strong><div style="color:var(--text-muted);margin-top:4px;">${rs.blotterDetails.join('<br>')}</div></div>`
    : `<div style="grid-column:1/-1;background:var(--green-dim);border:1px solid var(--border-green);border-radius:var(--radius-sm);padding:8px 12px;font-size:12px;color:var(--green-500);">✅ Walang blotter — Good Standing</div>`;
  document.getElementById('view-resident-content').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group"><div class="form-label">Resident ID</div><div style="font-family:var(--font-mono);color:var(--blue-400);">${r.id}</div></div>
      <div class="form-group"><div class="form-label">Status</div>
        <span class="badge ${r.status === 'Active' ? 'badge-green' : 'badge-red'}">${r.status}</span>
        ${senior ? '<span class="badge badge-senior" style="margin-left:5px;">👴 Senior Citizen</span>' : ''}
      </div>
      <div class="form-group"><div class="form-label">Full Name</div><div style="color:var(--text-primary);font-weight:600;">${r.name}</div></div>
      <div class="form-group"><div class="form-label">Date of Birth</div><div>${r.dob}</div></div>
      <div class="form-group"><div class="form-label">Edad</div><div style="font-size:18px;font-weight:800;color:${senior ? 'var(--senior-color)' : 'var(--text-primary)'};">${age} taong gulang</div></div>
      <div class="form-group"><div class="form-label">Kasarian</div><div>${r.gender}</div></div>
      <div class="form-group"><div class="form-label">Civil Status</div><div>${r.civil}</div></div>
      <div class="form-group"><div class="form-label">Purok</div><div>${r.purok}</div></div>
      <div class="form-group"><div class="form-label">Household</div><div>${r.household}</div></div>
      <div class="form-group"><div class="form-label">Contact</div><div>${r.contact}</div></div>
      <div class="form-group"><div class="form-label">Uri ng Paninirahan</div><div>${r.type}</div></div>
      ${blotterHtml}
    </div>`;
  openModal('modal-view-resident');
}

// ═══════════════════════════════════════
// CERTIFICATES
// ═══════════════════════════════════════
function legacyRenderCertRequests(filter = '') {
  const tbody = document.getElementById('cert-requests-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  CERT_REQUESTS
    .filter(r => !filter || r.name.toLowerCase().includes(filter.toLowerCase()) || r.code.toLowerCase().includes(filter.toLowerCase()))
    .forEach(r => {
      const badgeClass = r.status === 'Ready to Print' ? 'badge-green' : r.status === 'Processing' ? 'badge-amber' : 'badge-blue';
      const viaClass = r.via === 'Online' ? 'badge-purple' : 'badge-gray';
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--green-500);">${r.code}</span></td>
        <td><strong style="color:var(--text-primary);">${r.name}</strong></td>
        <td>${r.type}</td>
        <td style="font-size:11px;">${r.requested}</td>
        <td><span class="badge ${viaClass}">${r.via}</span></td>
        <td><span class="badge ${badgeClass}">${r.status}</span></td>
        <td>
          ${r.status === 'Ready to Print' ? `<button class="btn btn-xs btn-green" onclick="printCert('${r.code}')">🖨️ Print</button>` : ''}
          <button class="btn btn-xs btn-primary" onclick="viewCertificateRequest('${r.code}')">🔍 View</button>
        </td>`;
      tbody.appendChild(tr);
    });
}

function printCert(code) {
  const req = CERT_REQUESTS.find(r => r.code === code);
  if (!req) return;

  const priceMap = {
    'barangay clearance': 50,
    'certificate of residency': 50,
    'certificate of indigency': 0,
    'barangay id': 100,
    'first time jobseeker': 0,
    'business clearance': 200
  };

  const normalizeType = (text) =>
    (text || '')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .replace('job seeker', 'jobseeker');

  const requestType = normalizeType(req.type);
  const amount = priceMap[requestType] ?? 0;

  document.getElementById('print-document-request-id').value = req.id || '';
  document.getElementById('print-certificate-type').value = req.type || '';
  document.getElementById('print-resident-name').value = req.name || '';
  document.getElementById('print-purpose').value = req.purpose || '';
  document.getElementById('print-amount-paid').value =
    amount === 0 ? 'FREE' : `PHP ${amount.toFixed(2)}`;

  openModal('modal-print-release');
}

const MANUAL_CERTIFICATE_FEES = {
  'Barangay Clearance': 50,
  'Certificate of Residency': 50,
  'Certificate of Indigency': 0,
  'Barangay ID': 100,
  'First Time Jobseeker': 0,
  'Business Clearance': 200
};

function updateManualCertificateFee() {
  const certificateType = document.getElementById('manual-certificate-type')?.value;
  const feeInput = document.getElementById('manual-certificate-fee');
  const amount = MANUAL_CERTIFICATE_FEES[certificateType] ?? 0;

  if (feeInput) {
    feeInput.value = amount === 0 ? 'FREE' : `PHP ${amount.toFixed(2)}`;
  }
}

async function issueManualCertificate(event) {
  event.preventDefault();

  const submitButton = document.getElementById('manual-certificate-submit');
  const printWindow = window.open('about:blank', '_blank');

  if (submitButton) {
    submitButton.disabled = true;
    submitButton.dataset.originalText = submitButton.innerHTML;
    submitButton.innerHTML = '⏳ Issuing...';
  }

  try {
    const response = await fetch('/admin/issued-certificates', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...csrfRequestHeaders()
      },
      body: JSON.stringify({
        certificate_type: document.getElementById('manual-certificate-type')?.value,
        resident_id: Number(document.getElementById('manual-resident-id')?.value) || null,
        resident_name: document.getElementById('manual-resident-name')?.value.trim(),
        address: document.getElementById('manual-resident-address')?.value.trim() || null,
        purpose: document.getElementById('manual-certificate-purpose')?.value.trim() || null
      })
    });
    const data = await response.json();

    if (!response.ok) {
      const validationMessage = data?.errors
        ? Object.values(data.errors).flat()[0]
        : null;

      throw new Error(validationMessage || data?.message || 'Certificate issuance failed.');
    }

    closeModal('modal-cert-issue');
    document.getElementById('manual-certificate-form')?.reset();
    updateManualCertificateFee();
    await refreshIssuedCertificates();
    showToast('✅ Certificate issued successfully.', 'green');

    if (data.print_url) {
      if (printWindow) {
        printWindow.location.href = data.print_url;
      } else {
        window.location.href = data.print_url;
      }
    } else if (printWindow) {
      printWindow.close();
    }
  } catch (error) {
    if (printWindow) printWindow.close();
    showToast(error.message || 'Certificate issuance failed.', 'red');
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.innerHTML = submitButton.dataset.originalText || '🖨️ Issue & Print';
    }
  }
}

async function refreshIssuedCertificates() {
  const tbody = document.getElementById('issued-certificates-tbody');
  if (!tbody) return;

  try {
    const response = await fetch('/admin/issued-certificates', {
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
      throw new Error('Unable to load issued certificates.');
    }

    renderIssuedCertificates(await response.json());
  } catch (error) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#EF4444;">Unable to load issued certificates.</td></tr>';
  }
}

function renderIssuedCertificates(certificates) {
  const tbody = document.getElementById('issued-certificates-tbody');
  if (!tbody) return;

  tbody.innerHTML = '';

  if (!Array.isArray(certificates) || certificates.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);">No certificates have been issued yet.</td></tr>';
    return;
  }

  certificates.forEach(certificate => {
    const issuedDate = certificate.issued_at
      ? new Date(certificate.issued_at).toLocaleString('en-PH')
      : '—';
    const fee = Number(certificate.amount_paid) > 0
      ? `PHP ${Number(certificate.amount_paid).toFixed(2)}`
      : 'FREE';
    const source = String(certificate.source || 'onsite').toLowerCase();
    const sourceBadgeClass = source === 'online' ? 'badge-purple' : 'badge-gray';
    const row = document.createElement('tr');

    row.innerHTML = `
      <td><span style="font-family:var(--font-mono);color:var(--green-500);">${escapeText(certificate.certificate_number)}</span></td>
      <td><strong style="color:var(--text-primary);">${escapeText(certificate.resident_name)}</strong></td>
      <td>${escapeText(certificate.certificate_type)}</td>
      <td><span class="badge ${sourceBadgeClass}">${escapeText(source.toUpperCase())}</span></td>
      <td>${escapeText(fee)}</td>
      <td>${escapeText(issuedDate)}</td>
      <td>${escapeText(certificate.issued_by || 'Barangay Staff')}</td>
      <td><div style="display:flex;gap:5px;"><button class="btn btn-xs btn-green issued-reprint">🖨️ Reprint</button><button class="btn btn-xs issued-verify">🔍 Verify</button></div></td>`;

    row.querySelector('.issued-reprint')?.addEventListener('click', () => {
      window.open(certificate.print_url, '_blank');
    });
    row.querySelector('.issued-verify')?.addEventListener('click', () => {
      window.open(certificate.verification_url, '_blank');
    });
    tbody.appendChild(row);
  });
}

function viewCertificateRequest(code) {
  const req = CERT_REQUESTS.find(r => r.code === code);
  if (!req) { showToast('Request not found.', 'red'); return; }
  document.getElementById('qr-verify-doc-title').textContent = req.type;
  document.getElementById('qr-verify-name').textContent = req.name;
  document.getElementById('qr-verify-code').textContent = req.code;
  document.getElementById('qr-verify-date').textContent = req.requested;
  document.getElementById('qr-verify-status').textContent = req.status;
  openModal('modal-qr-verify');
}

// ═══════════════════════════════════════
// RFID
// ═══════════════════════════════════════
function renderRFIDTags() {
  const container = document.getElementById('rfid-tags-list');
  if (!container) return;
  container.innerHTML = '';
  RFID_TAGS.forEach(tag => {
    const div = document.createElement('div');
    div.className = 'rfid-tag';
    div.id = 'tag-' + tag.id;
    div.innerHTML = `
      <div class="rfid-tag-icon">📡</div>
      <div style="flex:1;">
        <div class="rfid-tag-id">${tag.id}</div>
        <div class="rfid-tag-name">${tag.name}</div>
        <div class="rfid-tag-doc">${tag.type} — ${tag.loc}</div>
      </div>
      <span class="badge ${tag.status === 'In Cabinet' ? 'badge-green' : 'badge-amber'}">${tag.status}</span>`;
    div.onclick = () => simulateRFIDTag(tag);
    container.appendChild(div);
  });
  const tbody = document.getElementById('rfid-log-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);font-size:12px;padding:18px 0;">IoT hardware hindi pa available. Walang scan log.</td></tr>`;
}

function simulateRFIDScan() {
  const area = document.getElementById('rfid-scan-area');
  const lbl = document.getElementById('rfid-scan-label');
  area.classList.add('active-scan');
  lbl.textContent = '🔄 Reading RFID signal...';
  setTimeout(() => {
    area.classList.remove('active-scan');
    const tag = RFID_TAGS[Math.floor(Math.random() * RFID_TAGS.length)];
    lbl.textContent = `✅ Tag detected: ${tag.id}`;
    simulateRFIDTag(tag);
  }, 1500);
}

function simulateRFIDTag(tag) {
  const el = document.getElementById('tag-' + tag.id);
  if (el) el.classList.add('scanned');
  addRFIDLogRow(tag);
  showToast(`RFID scanned: ${tag.name} — ${tag.type}`, 'green');
  document.getElementById('rfid-scan-label').textContent = `✅ Last scan: ${tag.id} — ${tag.name}`;
}

function addRFIDLogRow(tag) {
  const tbody = document.getElementById('rfid-log-tbody');
  if (!tbody) return;
  const dirs = ['↑ Entry', '↓ Exit'];
  const dir = dirs[Math.floor(Math.random() * 2)];
  const tr = document.createElement('tr');
  tr.style.background = 'rgba(0,138,56,0.05)';
  tr.innerHTML = `<td style="font-family:var(--font-mono);font-size:11px;">${new Date().toLocaleTimeString('en-PH', { hour12: false })}</td><td style="font-family:var(--font-mono);color:var(--green-500);">${tag.id}</td><td style="font-weight:600;color:var(--text-primary);">${tag.name}</td><td>${tag.loc}</td><td><span class="badge ${dir.includes('Entry') ? 'badge-green' : 'badge-blue'}">${dir}</span></td><td><span class="badge badge-green">✓ Verified</span></td>`;
  tbody.insertBefore(tr, tbody.firstChild);
  setTimeout(() => tr.style.background = '', 2000);
}

// ═══════════════════════════════════════
// SMART CABINET — 2FA
// ═══════════════════════════════════════
let cabFaceDone = false;
let cabRFIDDone = false;

function simulateCabFaceScan() {
  if (cabFaceDone) { showToast('Face scan na tapos. I-tap na ang RFID card.', ''); return; }
  const step = document.getElementById('cab-step-1');
  const status = document.getElementById('cab-auth-status');
  step.innerHTML = '<div class="cab-step-icon">🔄</div><div class="cab-step-label">Scanning...</div>';
  setTimeout(() => {
    cabFaceDone = true;
    step.classList.remove('active');
    step.classList.add('done');
    step.innerHTML = '<div class="cab-step-icon">✅</div><div class="cab-step-label">Face Verified</div>';
    document.getElementById('cab-step-2').classList.add('active');
    status.style.display = 'block';
    status.style.background = 'var(--green-dim)';
    status.style.border = '1px solid var(--border-green)';
    status.style.color = 'var(--green-500)';
    status.textContent = '✅ Step 1 done: Face ng Juan dela Cruz na-verify (99.4%). I-tap na ang RFID key card.';
    showToast('Face recognized! I-tap na ang RFID card.', 'green');
    if (cabRFIDDone) unlockCabinet();
  }, 1800);
}

function simulateCabRFID() {
  if (!cabFaceDone) { showToast('Kailangan muna ng Face Scan bago ang RFID!', 'red'); return; }
  if (cabRFIDDone) { showToast('RFID na na-scan. Bukas na ang cabinet.', ''); return; }
  const step = document.getElementById('cab-step-2');
  step.innerHTML = '<div class="cab-step-icon">🔄</div><div class="cab-step-label">Reading card...</div>';
  setTimeout(() => {
    cabRFIDDone = true;
    step.classList.remove('active');
    step.classList.add('done');
    step.innerHTML = '<div class="cab-step-icon">✅</div><div class="cab-step-label">RFID Verified</div>';
    unlockCabinet();
  }, 1200);
}

function unlockCabinet() {
  const step3 = document.getElementById('cab-step-3');
  step3.classList.add('done');
  step3.innerHTML = '<div class="cab-step-icon">🔓</div><div class="cab-step-label">Cabinet Open!</div>';
  const status = document.getElementById('cab-auth-status');
  status.style.display = 'block';
  status.style.background = 'var(--green-dim)';
  status.style.border = '1px solid var(--border-green)';
  status.style.color = 'var(--green-500)';
  status.innerHTML = '🔓 <strong>Cabinet unlocked!</strong> 2FA successful — Face + RFID na verified. Access na naka-log sa Audit Trail.';
  showToast('🔓 Cabinet unlocked! Maaring na-access ang cabinet.', 'green');
  CABINET_DRAWERS.forEach(d => d.locked = false);
  renderCabinet();
}

function resetCabAuth() {
  cabFaceDone = false;
  cabRFIDDone = false;
  ['cab-step-1','cab-step-2','cab-step-3'].forEach((id, i) => {
    const el = document.getElementById(id);
    el.classList.remove('active','done');
    if (i === 0) el.classList.add('active');
  });
  document.getElementById('cab-step-1').innerHTML = '<div class="cab-step-icon">😊</div><div class="cab-step-label">Step 1: Face Scan</div>';
  document.getElementById('cab-step-2').innerHTML = '<div class="cab-step-icon">📡</div><div class="cab-step-label">Step 2: RFID Card</div>';
  document.getElementById('cab-step-3').innerHTML = '<div class="cab-step-icon">🔓</div><div class="cab-step-label">Cabinet Open</div>';
  document.getElementById('cab-auth-status').style.display = 'none';
  CABINET_DRAWERS.forEach((d, i) => d.locked = [false,false,true,true,false,true,true,true][i]);
  renderCabinet();
  showToast('Cabinet auth reset.', '');
}

// ═══════════════════════════════════════
// SMART CABINET — DRAWERS
// ═══════════════════════════════════════
function renderCabinet() {
  const container = document.getElementById('cabinet-drawers');
  if (!container) return;
  container.innerHTML = '';
  CABINET_DRAWERS.forEach(d => {
    const div = document.createElement('div');
    div.className = `cabinet-drawer ${d.locked ? 'locked' : ''}`;
    div.id = 'drawer-' + d.id;
    div.onclick = () => toggleDrawer(d.id, d.locked);
    div.innerHTML = `
      <div>
        <div class="drawer-label">${d.icon} ${d.label}</div>
        <div class="drawer-rfid">${d.category} &nbsp;|&nbsp; RFID: ${d.rfid}</div>
      </div>
      <div style="display:flex;align-items:center;gap:6px;">
        <div style="width:8px;height:8px;border-radius:50%;background:${d.locked ? '#EF4444' : 'var(--green-500)'}"></div>
        <div class="drawer-status">${d.locked ? '🔒 Locked' : '🔓 Unlocked'}</div>
      </div>`;
    container.appendChild(div);
  });
}

function toggleDrawer(id, isLocked) {
  const d = CABINET_DRAWERS.find(x => x.id === id);
  if (!d) return;
  if (isLocked) {
    showToast(`🔒 ${d.label} — I-tap ang RFID key card para mabuksan.`, 'red');
    return;
  }
  const el = document.getElementById('drawer-' + id);
  const isOpen = el.classList.contains('open');
  el.classList.toggle('open');
  showToast(isOpen ? `${d.label} closed.` : `${d.label} opened — ${d.category}`, 'green');
  addCabinetLog(d, isOpen ? 'Closed' : 'Opened');
}

function addCabinetLog(d, action) {
  const tbody = document.getElementById('cabinet-log-tbody');
  if (!tbody) return;
  const tr = document.createElement('tr');
  tr.style.background = 'rgba(0,138,56,0.05)';
  tr.innerHTML = `<td style="font-family:var(--font-mono);font-size:11px;">${new Date().toLocaleTimeString('en-PH', {hour12:false})}</td><td>${d.label}</td><td><span class="badge ${action === 'Opened' ? 'badge-green' : 'badge-blue'}">${action}</span></td><td>Juan dela Cruz (Admin)</td>`;
  tbody.insertBefore(tr, tbody.firstChild);
  setTimeout(() => tr.style.background = '', 2000);
}

function searchDrawer() {
  const q = document.getElementById('drawer-search')?.value?.toLowerCase() || '';
  CABINET_DRAWERS.forEach(d => {
    const el = document.getElementById('drawer-' + d.id);
    if (!el) return;
    const match = d.label.toLowerCase().includes(q) || d.category.toLowerCase().includes(q);
    el.style.display = match ? 'flex' : 'none';
  });
}

// ═══════════════════════════════════════
// CABINET FOLDERS (RFID-tagged files inside)
// ═══════════════════════════════════════
function renderCabinetFolders() {
  const container = document.getElementById('cabinet-folders-list');
  if (!container) return;
  container.innerHTML = '';
  CABINET_FOLDERS.forEach(f => {
    const div = document.createElement('div');
    div.className = 'folder-item';
    div.innerHTML = `
      <span style="font-size:16px;">${f.status === 'Checked Out' ? '📂' : '📁'}</span>
      <div style="flex:1;">
        <div style="font-weight:600;color:var(--text-primary);font-size:12px;">${f.name}</div>
        <div style="font-size:10.5px;color:var(--text-muted);">${f.drawer}</div>
      </div>
      <span class="badge ${f.status === 'In Cabinet' ? 'badge-green' : 'badge-amber'}" style="font-size:9.5px;">${f.status}</span>
      <span class="folder-rfid-badge">${f.rfid}</span>`;
    div.onclick = () => {
      const newStatus = f.status === 'In Cabinet' ? 'Checked Out' : 'In Cabinet';
      f.status = newStatus;
      showToast(`📁 ${f.name} — ${newStatus}. RFID ${f.rfid} na-log.`, 'green');
      renderCabinetFolders();
    };
    container.appendChild(div);
  });
}

// ═══════════════════════════════════════
// QR VERIFICATION — TWO-PURPOSE SYSTEM
// Purpose 1: Document authenticity (for third parties)
// Purpose 2: Request status (for residents)
// ═══════════════════════════════════════

// Active QR tab
let activeQRTab = 'doc';

function switchQRTab(tab) {
  activeQRTab = tab;
  document.getElementById('qr-panel-doc').style.display = tab === 'doc' ? 'block' : 'none';
  document.getElementById('qr-panel-status').style.display = tab === 'status' ? 'block' : 'none';
  const docTab = document.getElementById('qr-tab-doc');
  const statusTab = document.getElementById('qr-tab-status');
  if (tab === 'doc') {
    docTab.style.borderBottomColor = 'var(--green-500)';
    docTab.style.color = 'var(--green-500)';
    statusTab.style.borderBottomColor = 'transparent';
    statusTab.style.color = 'var(--text-muted)';
  } else {
    statusTab.style.borderBottomColor = 'var(--blue-400)';
    statusTab.style.color = 'var(--blue-400)';
    docTab.style.borderBottomColor = 'transparent';
    docTab.style.color = 'var(--text-muted)';
  }
}

// PURPOSE 1 — Document Authenticity Scan
function simulateQRScan(mode) {
  if (mode === 'status') { document.getElementById('status-code')?.focus(); }
  else { document.getElementById('manual-code')?.focus(); }
  showToast('Enter the code printed on the document, or scan it with your phone camera.', '');
}

// Show document authenticity result (Purpose 1)
function showDocVerificationResult(code, isAuthentic) {
  const r = CERT_REQUESTS.find(x => x.code === code);
  const resultDiv = document.getElementById('qr-doc-result');
  const cardDiv = document.getElementById('qr-doc-result-card');
  if (!resultDiv || !cardDiv) return;

  if (!r || !isAuthentic) {
    cardDiv.innerHTML = `
      <div class="qr-doc-failed">
        <div class="qr-doc-failed-header">
          <div class="qr-doc-failed-seal">❌</div>
          <div>
            <div style="font-size:16px;font-weight:800;color:#EF4444;">DOCUMENT NOT VERIFIED</div>
            <div style="font-size:11.5px;color:var(--text-secondary);margin-top:3px;">This document could not be authenticated. It may be fake, altered, or expired.</div>
          </div>
        </div>
        <div style="padding:14px 18px;background:rgba(239,68,68,.05);border-top:1px solid rgba(239,68,68,.2);font-size:12px;color:var(--text-secondary);">
          ⚠️ If you received this document from someone, do not accept it. Contact Barangay Anabu I-G directly for verification.
        </div>
      </div>`;
    resultDiv.style.display = 'block';
    showToast('⚠️ Document verification failed — may be fake.', 'red');
    return;
  }

  const isExpired = false; // In real system, check validity period
  const resident = RESIDENTS.find(res => res.name === r.name);
  const issuedDate = r.requested;
  const validUntil = 'Apr 15, 2026';
  const issuedBy = 'Maria R. Lim — Records Officer';

  cardDiv.innerHTML = `
    <div class="qr-doc-verified">
      <div class="qr-doc-verified-header">
        <div class="qr-doc-verified-seal">✅</div>
        <div>
          <div class="qr-doc-verified-title">AUTHENTIC DOCUMENT</div>
          <div class="qr-doc-verified-sub">This is a valid, official Barangay Anabu I-G document.</div>
        </div>
      </div>
      <div class="qr-doc-fields">
        <div class="qr-doc-field"><span class="qr-doc-field-label">Document Type</span><span class="qr-doc-field-val">${r.type}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Issued To</span><span class="qr-doc-field-val" style="color:var(--green-500);">${r.name}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Confirmation Code</span><span class="qr-doc-field-val" style="font-family:var(--font-mono);color:var(--green-500);">${r.code}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Date Requested</span><span class="qr-doc-field-val">${issuedDate}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Valid Until</span><span class="qr-doc-field-val">${validUntil}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Processed By</span><span class="qr-doc-field-val">${issuedBy}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Document Status</span><span class="qr-doc-field-val"><span class="badge badge-green">✅ Authentic</span></span></div>
      </div>
      <div class="qr-doc-footer">
        🏛️ Barangay Anabu I-G, Imus City, Cavite &nbsp;•&nbsp; Verified ${new Date().toLocaleTimeString('en-PH', {hour12:false})}
        <button class="btn btn-xs btn-green" style="margin-left:auto;" onclick="showToast('Verification logged.','green')">📋 Log Verification</button>
      </div>
    </div>`;
  resultDiv.style.display = 'block';
  showToast('✅ Document is authentic and valid!', 'green');
}

// verifyCertCode — called by manual lookup (Purpose 1)
async function verifyCertCode(code) {
  const verificationCode = (code || '').trim().toUpperCase();

  if (!verificationCode) {
    showToast('Please enter a certificate verification code.', 'red');
    return;
  }

  try {
    const response = await fetch(`/verify-certificate/${encodeURIComponent(verificationCode)}`, {
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
      showRealCertificateVerification(null);
      pushQRRecentLog(verificationCode, 'Unknown', 'Unknown certificate', false);
      return;
    }

    const data = await response.json();
    const certificate = data.certificate;

    showRealCertificateVerification(certificate);
    pushQRRecentLog(
      certificate.verification_code,
      certificate.resident_name,
      certificate.certificate_type,
      true
    );
    switchQRTab('doc');
  } catch (error) {
    showToast('Unable to reach the verification service. Please try again.', 'red');
  }
}

function showRealCertificateVerification(certificate) {
  const resultDiv = document.getElementById('qr-doc-result');
  const cardDiv = document.getElementById('qr-doc-result-card');

  if (!resultDiv || !cardDiv) return;

  if (!certificate) {
    cardDiv.innerHTML = `
      <div class="qr-doc-failed">
        <div class="qr-doc-failed-header">
          <div class="qr-doc-failed-seal">❌</div>
          <div>
            <div style="font-size:16px;font-weight:800;color:#EF4444;">DOCUMENT NOT VERIFIED</div>
            <div style="font-size:11.5px;color:var(--text-secondary);margin-top:3px;">No issued certificate matches this verification code.</div>
          </div>
        </div>
      </div>`;
    resultDiv.style.display = 'block';
    showToast('Document verification failed.', 'red');
    return;
  }

  const issuedDate = certificate.issued_at
    ? new Date(certificate.issued_at).toLocaleString('en-PH')
    : 'Not recorded';

  cardDiv.innerHTML = `
    <div class="qr-doc-verified">
      <div class="qr-doc-verified-header">
        <div class="qr-doc-verified-seal">✅</div>
        <div>
          <div class="qr-doc-verified-title">AUTHENTIC DOCUMENT</div>
          <div class="qr-doc-verified-sub">This certificate matches the official Barangay Anabu I-G database.</div>
        </div>
      </div>
      <div class="qr-doc-fields">
        <div class="qr-doc-field"><span class="qr-doc-field-label">Certificate Number</span><span class="qr-doc-field-val">${escapeText(certificate.certificate_number)}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Document Type</span><span class="qr-doc-field-val">${escapeText(certificate.certificate_type)}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Issued To</span><span class="qr-doc-field-val" style="color:var(--green-500);">${escapeText(certificate.resident_name)}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Verification Code</span><span class="qr-doc-field-val" style="font-family:var(--font-mono);color:var(--green-500);">${escapeText(certificate.verification_code)}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Date Issued</span><span class="qr-doc-field-val">${escapeText(issuedDate)}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Issued By</span><span class="qr-doc-field-val">${escapeText(certificate.issued_by || 'Barangay Staff')}</span></div>
        <div class="qr-doc-field"><span class="qr-doc-field-label">Document Status</span><span class="qr-doc-field-val"><span class="badge badge-green">✅ Authentic</span></span></div>
      </div>
      <div class="qr-doc-footer">🏛️ Barangay Anabu I-G, Imus City, Cavite</div>
    </div>`;
  resultDiv.style.display = 'block';
  showToast('✅ Document is authentic.', 'green');
}

// PURPOSE 2 — Request Status Check (for residents)
async function checkRequestStatus(code) {
  if (!code?.trim()) { showToast('Please enter your confirmation code.', 'red'); return; }
  const result = document.getElementById('qr-status-result');
  const card = document.getElementById('qr-status-result-card');
  result.style.display = 'block';
  card.textContent = 'Checking request status...';
  try {
    const request = await adminRequest(`/portal/request/${encodeURIComponent(code.trim().toUpperCase())}`);
    card.innerHTML = `<div class="card-header"><div class="card-title">${escapeText(request.document_type)}</div></div>
      <p><strong>${escapeText(request.reference_code)}</strong></p>
      <p class="badge badge-blue">${escapeText(request.status.replaceAll('_', ' '))}</p>
      <p style="margin-top:12px">${escapeText(request.rejection_reason || request.remarks || 'Present your reference code at the Barangay Hall for assistance.')}</p>`;
  } catch (error) {
    card.textContent = error.message;
    showToast(error.message, 'red');
  }
}

function showRequestStatus(code, reqData) {
  const r = reqData || CERT_REQUESTS.find(x => x.code === code);
  const resultDiv = document.getElementById('qr-status-result');
  const cardDiv = document.getElementById('qr-status-result-card');
  if (!resultDiv || !cardDiv) return;

  if (!r) {
    cardDiv.innerHTML = `
      <div style="border:1.5px solid rgba(239,68,68,.35);border-radius:var(--radius-lg);padding:18px;background:rgba(239,68,68,.05);text-align:center;">
        <div style="font-size:28px;margin-bottom:8px;">❓</div>
        <div style="font-size:14px;font-weight:700;color:#EF4444;margin-bottom:6px;">Request Not Found</div>
        <div style="font-size:12px;color:var(--text-secondary);">No request matches this code. Please check your slip or visit the Barangay Hall.</div>
      </div>`;
    resultDiv.style.display = 'block';
    showToast('Request code not found.', 'red');
    return;
  }

  // Determine step states
  const steps = [
    { label: 'Request Received', meta: r.requested, done: true, current: false },
    { label: 'Under Review / Eligibility Check', meta: 'Staff verifying requirements', done: r.status !== 'Processing', current: r.status === 'Processing' },
    { label: 'Document Processing', meta: 'Being prepared and printed', done: r.status === 'Ready to Print' || r.status === 'Completed', current: false },
    { label: 'Ready for Pick-Up', meta: 'Visit Barangay Hall — bring confirmation code', done: r.status === 'Completed', current: r.status === 'Ready to Print' },
    { label: 'Released', meta: r.status === 'Completed' ? 'Document has been released' : 'Awaiting pick-up', done: r.status === 'Completed', current: false },
  ];

  const statusColor = r.status === 'Ready to Print' ? 'var(--green-500)' : r.status === 'Completed' ? 'var(--blue-400)' : '#F59E0B';
  const statusBadge = r.status === 'Ready to Print' ? '<span class="badge badge-green">🖨️ Ready for Pick-Up</span>'
    : r.status === 'Completed' ? '<span class="badge badge-blue">✅ Released</span>'
    : '<span class="badge badge-amber">⏳ Processing</span>';

  const stepsHtml = steps.map(s => `
    <div class="qr-track-step ${s.done ? 'done' : ''}">
      <div class="qr-track-dot ${s.done ? 'done' : s.current ? 'current' : ''}">
        ${s.done ? '✓' : s.current ? '●' : '○'}
      </div>
      <div>
        <div class="qr-track-label" style="color:${s.done ? 'var(--green-500)' : s.current ? '#F59E0B' : 'var(--text-muted)'};">${s.label}</div>
        <div class="qr-track-meta">${s.meta}</div>
      </div>
    </div>`).join('');

  cardDiv.innerHTML = `
    <div class="qr-status-card" style="border:1.5px solid ${statusColor}33;background:${statusColor}08;">
      <div class="qr-status-header" style="background:${statusColor}10;border-bottom:1px solid ${statusColor}22;">
        <div style="width:48px;height:48px;border-radius:50%;background:${statusColor};display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">📋</div>
        <div style="flex:1;">
          <div style="font-size:15px;font-weight:800;color:var(--text-primary);">${escapeText(r.type)}</div>
          <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">Request for: <strong style="color:var(--text-primary);">${escapeText(r.name)}</strong></div>
        </div>
        ${statusBadge}
      </div>
      <div style="padding:14px 18px;display:flex;gap:16px;border-bottom:1px solid var(--row-sep);">
        <div style="flex:1;"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Confirmation Code</div><div style="font-family:var(--font-mono);font-size:14px;font-weight:700;color:${statusColor};">${escapeText(r.code)}</div></div>
        <div style="flex:1;"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Date Filed</div><div style="font-size:12.5px;font-weight:600;color:var(--text-primary);">${escapeText(r.requested)}</div></div>
        <div style="flex:1;"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Channel</div><div><span class="badge ${r.via === 'Online' ? 'badge-purple' : 'badge-gray'}">${escapeText(r.via)}</span></div></div>
      </div>
      <div class="qr-status-track">${stepsHtml}</div>
      ${r.status === 'Ready to Print' ? `
      <div style="padding:12px 18px;background:var(--green-dim);border-top:1px solid var(--border-green);display:flex;align-items:center;gap:10px;font-size:12px;color:var(--green-500);">
        🏛️ <strong>Your document is ready!</strong> Visit Barangay Anabu I-G Hall and present your confirmation code: <strong style="font-family:var(--font-mono);">${escapeText(r.code)}</strong>
      </div>` : ''}
    </div>`;
  resultDiv.style.display = 'block';
  showToast(`Request ${escapeText(r.code)} found — Status: ${r.status}`, 'green');
}

// Recent verifications log (Purpose 1)
const QR_RECENT_LOG = [];

function pushQRRecentLog(code, name, type, ok) {
  QR_RECENT_LOG.unshift({ code, name, type, time: new Date().toLocaleTimeString('en-PH', {hour12:true}), ok });
  renderQRRecentLog();
}

function renderQRRecentLog() {
  const container = document.getElementById('qr-recent-list');
  if (!container) return;
  container.innerHTML = '';
  QR_RECENT_LOG.slice(0, 5).forEach(l => {
    const div = document.createElement('div');
    div.style.cssText = 'display:flex;align-items:center;gap:10px;padding:9px;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;transition:all .15s;';
    div.onmouseenter = function() { this.style.borderColor = 'var(--border-hover)'; };
    div.onmouseleave = function() { this.style.borderColor = 'var(--border)'; };
    div.innerHTML = `
      <div style="width:34px;height:34px;border-radius:8px;background:${l.ok ? 'var(--green-dim)' : 'rgba(239,68,68,.1)'};border:1px solid ${l.ok ? 'var(--border-green)' : 'rgba(239,68,68,.25)'};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;">${l.ok ? '✅' : '❌'}</div>
      <div style="flex:1;">
        <div style="font-size:12px;font-weight:600;color:var(--text-primary);">${escapeText(l.type)} — <span style="font-family:var(--font-mono);color:var(--green-500);">${escapeText(l.code)}</span></div>
        <div style="font-size:11px;color:var(--text-muted);">${escapeText(l.name)} • ${escapeText(l.time)} • ${l.ok ? '✅ Authentic' : '❌ Invalid'}</div>
      </div>`;
    div.onclick = () => { verifyCertCode(l.code); };
    container.appendChild(div);
  });
}

// ═══════════════════════════════════════
// FACE RECOGNITION
// ═══════════════════════════════════════
function simulateFaceRecognition() {
  const area = document.getElementById('face-live-area');
  const lbl = document.getElementById('face-live-label');
  const res = document.getElementById('face-live-result');
  if (!area) return;
  area.classList.add('active-scan');
  lbl.textContent = '🔄 Analyzing biometrics...';
  res.classList.remove('show');
  let p = 0;
  const iv = setInterval(() => {
    p += 10;
    lbl.textContent = `🔄 Processing facial data... ${p}%`;
    if (p >= 100) {
      clearInterval(iv);
      area.classList.remove('active-scan');
      lbl.textContent = '✅ Recognition Complete';
      res.classList.add('show');
      showToast('Face recognized: Juan dela Cruz — Admin (99.4% confidence)', 'green');
    }
  }, 140);
}

// ═══════════════════════════════════════
// AUDIT LOG
// ═══════════════════════════════════════
function legacyRenderAuditLog() {
  const container = document.getElementById('audit-log-list');
  if (!container) return;
  container.innerHTML = '';
  AUDIT_LOGS.forEach(log => {
    const div = document.createElement('div');
    div.className = 'log-item';
    div.innerHTML = `<div class="log-icon-box">${escapeText(log.icon)}</div><div style="flex:1;"><div class="log-action">${escapeText(log.action)}</div><div class="log-detail">${escapeText(log.detail)}</div><div class="log-time">🕐 ${escapeText(log.time)}</div></div>`;
    container.appendChild(div);
  });
}

// ═══════════════════════════════════════
// USER MANAGEMENT
// ═══════════════════════════════════════
function legacyRenderUsers() {
  const tbody = document.getElementById('users-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  USERS.forEach(u => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--blue-400);">${u.id}</span></td>
      <td><strong style="color:var(--text-primary);">${u.name}</strong></td>
      <td>${u.role}</td>
      <td>${u.access}</td>
      <td>${u.face ? '<span class="badge badge-green">✓ Enrolled</span>' : '<span class="badge badge-gray">Not Enrolled</span>'}</td>
      <td><span class="badge ${u.status === 'Active' ? 'badge-green' : 'badge-red'}">${u.status}</span></td>
      <td style="font-size:11px;">${u.last}</td>
      <td><button class="btn btn-xs btn-primary" onclick="showToast('Managing user ${u.name}', '')">Manage</button></td>`;
    tbody.appendChild(tr);
  });
}

// ═══════════════════════════════════════
// PUBLIC PORTAL
// ═══════════════════════════════════════
let selectedCertType = null;
function showPublicPortal() {
  document.getElementById('public-portal').classList.add('show');
  document.getElementById('portal-confirm').classList.remove('show');
  selectedCertType = null;
  document.querySelectorAll('.cert-type-btn').forEach(b => b.classList.remove('selected'));
  document.getElementById('portal-form').style.display = 'none';
  document.getElementById('portal-cert-grid').style.display = 'grid';
  document.getElementById('portal-btn-next').style.display = 'none';
  const emailInput = document.getElementById('portal-email');
  if (emailInput) emailInput.value = '';
  const attInput = document.getElementById('portal-attachment');
  if (attInput) attInput.value = '';
  const attPreview = document.getElementById('portal-attachment-preview');
  if (attPreview) { attPreview.style.display = 'none'; attPreview.innerHTML = ''; }
}
function hidePublicPortal() { document.getElementById('public-portal').classList.remove('show'); }
function selectCertType(id) {
  selectedCertType = id;
  document.querySelectorAll('.cert-type-btn').forEach(b => b.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('portal-btn-next').style.display = 'block';
}
function portalNext() {
  if (!selectedCertType) { alert('Pumili muna ng uri ng dokumento.'); return; }
  document.getElementById('portal-cert-grid').style.display = 'none';
  document.getElementById('portal-btn-next').style.display = 'none';
  document.getElementById('portal-form').style.display = 'block';
  const ct = CERTIFICATE_TYPES.find(c => c.id === selectedCertType);
  document.getElementById('portal-selected-cert').textContent = ct ? ct.label : '';
}
function submitPortalRequest() {
  const name = document.getElementById('portal-name')?.value?.trim();
  const addr = document.getElementById('portal-address')?.value?.trim();
  const purpose = document.getElementById('portal-purpose')?.value?.trim();
  if (!name || !addr || !purpose) { alert('Kumpletuhin ang lahat ng required fields.'); return; }
  const code = 'REQ-' + (7743 + Math.floor(Math.random() * 100));
  document.getElementById('portal-confirm-code').textContent = code;
  document.getElementById('portal-form').style.display = 'none';
  document.getElementById('portal-confirm').classList.add('show');
  const ct = CERTIFICATE_TYPES.find(c => c.id === selectedCertType);
  CERT_REQUESTS.unshift({ code, name, type: ct?.label || 'Certificate', requested: new Date().toLocaleString('en-PH'), status: 'Processing', via: 'Online' });
  renderCertRequests();
  const badge = document.getElementById('cert-nav-badge');
  if (badge) { const n = (parseInt(badge.textContent) || 0) + 1; badge.textContent = n; badge.style.display = 'flex'; }
  showToast(`Online request ${code} submitted!`, 'green');
}

// ═══════════════════════════════════════
// MODALS
// ═══════════════════════════════════════
let modalTrigger = null;
function openModal(id) {
  const element = document.getElementById(id);
  if (!element) return;
  modalTrigger = document.activeElement;
  element.classList.add('show');
  element.setAttribute('role', 'dialog');
  element.setAttribute('aria-modal', 'true');
  const title = element.querySelector('.modal-title');
  if (title) { if (!title.id) title.id = id + '-title'; element.setAttribute('aria-labelledby', title.id); }
  element.querySelector('input:not([type="hidden"]), select, textarea, button, [tabindex="0"]')?.focus();
}
function closeModal(id) { const el = document.getElementById(id); if (el) el.classList.remove('show'); modalTrigger?.focus(); }
document.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id); });

// ═══════════════════════════════════════
// TOAST
// ═══════════════════════════════════════
function showToast(msg, type) {
  const wrap = document.getElementById('toast-wrap');
  if (!wrap) return;
  const toast = document.createElement('div');
  toast.className = 'toast' + (type ? ' ' + type : '');
  const icons = { green: '✅', red: '❌', '': 'ℹ️' };
  toast.innerHTML = `<span style="font-size:15px;">${icons[type] || 'ℹ️'}</span><span>${escapeText(msg)}</span>`;
  wrap.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = 'all 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// ═══════════════════════════════════════
// LOADING BAR
// ═══════════════════════════════════════
function showLoadingBar() {
  const bar = document.getElementById('loading-bar');
  if (!bar) return;
  bar.style.width = '0%';
  requestAnimationFrame(() => {
    bar.style.transition = 'width 0.3s ease';
    bar.style.width = '70%';
    setTimeout(() => {
      bar.style.width = '100%';
      setTimeout(() => { bar.style.width = '0%'; bar.style.transition = 'none'; }, 300);
    }, 400);
  });
}

// ═══════════════════════════════════════
// TOGGLES
// ═══════════════════════════════════════
document.addEventListener('click', function(e) {
  const toggle = e.target.closest('.toggle');
  if (toggle) toggle.classList.toggle('on');
});

// ═══════════════════════════════════════
// GENERATE REPORT / SETTINGS
// ═══════════════════════════════════════
function generateReport() { window.location.href = '/admin/residents-export'; closeModal('modal-report'); }


// ═══════════════════════════════════════
// REQUEST RECORDS
// ═══════════════════════════════════════
let rrCurrentFilter = '';
let rrCurrentStatusFilter = '';
let rrCurrentPage = 1;
let rrLastPage = 1;
let rrSearchTimer = null;
let requestRecordResidents = [];

function legacyRenderRequestRecords(nameFilter = '', statusFilter = '') {
  const container = document.getElementById('rr-resident-list');
  if (!container) return;
  container.innerHTML = '';
  const allRequests = [...REQUEST_RECORDS, ...CERT_REQUESTS];
  const total = allRequests.length;
  const completed = allRequests.filter(r => r.status === 'Completed').length;
  const blocked = REQUEST_RECORDS.filter(r => !r.eligible || r.status === 'Blocked').length;
  const blotteredFromStatus = new Set(Object.entries(RESIDENT_STATUS).filter(([,s]) => s.blotter).map(([id]) => id));
  const blotteredFromIncidents = new Set(
    (typeof INCIDENTS !== 'undefined' ? INCIDENTS : []).map(inc => {
      if (!inc.complainee) return null;
      const res = RESIDENTS.find(r => r.name.trim().toLowerCase() === inc.complainee.trim().toLowerCase());
      return res ? res.id : null;
    }).filter(Boolean)
  );
  const blotteredResidents = new Set([...blotteredFromStatus, ...blotteredFromIncidents]).size;
  const setEl = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  setEl('rr-total', total); setEl('rr-completed', completed); setEl('rr-blocked', blocked); setEl('rr-blotter', blotteredResidents);

  const groups = RESIDENTS.map(resident => {
    const nameLower = resident.name.trim().toLowerCase();
    // Merge REQUEST_RECORDS (by residentId) + CERT_REQUESTS (by name)
    const fromRR = REQUEST_RECORDS.filter(r => r.residentId === resident.id);
    const fromCR = CERT_REQUESTS.filter(cr =>
      cr.name && cr.name.trim().toLowerCase() === nameLower &&
      !fromRR.some(r => r.code === cr.code)
    ).map(cr => ({
      code: cr.code, type: cr.type, date: cr.requested || '—',
      via: cr.via || 'Online', status: cr.status || 'Processing',
      residentId: resident.id, eligible: true
    }));
    const requests = [...fromRR, ...fromCR];
    const rs = RESIDENT_STATUS[resident.id];
    // Check blotter flag AND incident reports (ine-reklamo)
    const incidentBlotter = INCIDENTS.filter(inc =>
      inc.complainee && inc.complainee.trim().toLowerCase() === nameLower
    );
    const hasAnyBlotter = rs?.blotter || incidentBlotter.length > 0;
    const eligStatus = hasAnyBlotter ? 'Blotter' : 'Eligible';
    return { resident, requests, rs, eligStatus, incidentBlotter };
  });

  const filtered = groups.filter(g => {
    const matchName = !nameFilter || g.resident.name.toLowerCase().includes(nameFilter.toLowerCase()) || g.resident.id.toLowerCase().includes(nameFilter.toLowerCase());
    const matchStatus = !statusFilter || g.eligStatus === statusFilter || (statusFilter === 'Ineligible' && g.requests.some(r => !r.eligible));
    return matchName && matchStatus;
  });

  if (filtered.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px 0;">Walang nahanap na residente.</div>';
    return;
  }

  filtered.forEach(({ resident, requests, rs, eligStatus, incidentBlotter }) => {
    const hasBlotter = rs?.blotter || incidentBlotter.length > 0;
    const hasIneligible = requests.some(r => !r.eligible || r.status === 'Blocked');
    const statusBadge = hasBlotter
      ? '<span class="badge badge-red">⚠️ May Blotter</span>'
      : hasIneligible ? '<span class="badge badge-amber">🚫 May Blocked</span>'
      : '<span class="badge badge-green">✅ Good Standing</span>';
    const age = calcAge(resident.dob);
    const senior = age >= 60;
    const card = document.createElement('div');
    card.className = 'card rr-resident-card';
    card.style.marginBottom = '0';
    const requestRows = requests.length === 0
      ? '<tr><td colspan="6" style="text-align:center;color:var(--text-muted);padding:12px 0;">Wala pang request records.</td></tr>'
      : requests.map(req => {
          const sc = req.status === 'Completed' ? 'badge-green' : req.status === 'Ready to Print' ? 'badge-blue' : req.status === 'Blocked' ? 'badge-red' : 'badge-amber';
          const eligBadge = req.eligible ? '<span class="badge badge-green" style="font-size:10px;">✅ Eligible</span>' : '<span class="badge badge-red" style="font-size:10px;">🚫 Ineligible</span>';
          const nextStatus = req.status === 'Processing' ? 'Ready to Print' : req.status === 'Ready to Print' ? 'Completed' : '';
          const proceedBtn = nextStatus ? `<button class="btn btn-xs btn-green" style="margin-left:6px;" onclick="event.stopPropagation();advanceRequestRecord('${req.code}','${nextStatus}')">Proceed</button>` : '';
          return `<tr>
            <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--green-500);">${req.code}</span></td>
            <td style="font-size:12px;">${req.type}</td>
            <td style="font-size:11px;color:var(--text-muted);">${req.date}</td>
            <td><span class="badge ${req.via === 'Online' ? 'badge-purple' : 'badge-gray'}" style="font-size:10px;">${req.via}</span></td>
            <td><span class="badge ${sc}" style="font-size:10px;">${req.status}</span></td>
            <td>${eligBadge}${proceedBtn}</td>
          </tr>`;
        }).join('');
    card.innerHTML = `
      <div class="rr-resident-header" onclick="toggleRRCard(this)">
        <div style="display:flex;align-items:center;gap:10px;">
          <div class="rr-avatar" style="background:${hasBlotter ? 'rgba(239,68,68,0.18)' : 'var(--green-dim)'};color:${hasBlotter ? '#FCA5A5' : 'var(--green-500)'};">${resident.name.charAt(0)}</div>
          <div>
            <div style="font-weight:700;color:var(--text-primary);font-size:13.5px;">${resident.name}
              ${senior ? '<span class="badge badge-senior" style="font-size:9px;margin-left:5px;">👴 Senior</span>' : ''}
            </div>
            <div style="font-size:11px;color:var(--text-muted);">${resident.id} &nbsp;•&nbsp; ${resident.purok} &nbsp;•&nbsp; ${age} yrs old</div>
          </div>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
          ${statusBadge}
          <span class="badge badge-gray" style="font-size:10px;">${requests.length} req</span>
          <button class="btn btn-xs btn-primary" onclick="event.stopPropagation();openEligibilityForResident('${resident.id}')">🔍 Check</button>
          <span class="rr-chevron" style="color:var(--text-muted);font-size:12px;">▼</span>
        </div>
      </div>
      ${rs?.blotter ? `<div style="background:rgba(239,68,68,0.07);border-left:3px solid #EF4444;padding:8px 12px;font-size:11.5px;color:#FCA5A5;">⚠️ <strong>Blotter:</strong> ${rs.blotterDetails.join('; ')}</div>` : ''}
      ${incidentBlotter.length > 0 ? `<div style="background:rgba(239,68,68,0.07);border-left:3px solid #EF4444;padding:8px 12px;font-size:11.5px;color:#FCA5A5;">🚨 <strong>Incident Reports (Ine-reklamo):</strong> ${incidentBlotter.map(i => `${i.type} — ${i.date} (${i.id})`).join('; ')}</div>` : ''}
      <div class="rr-requests-panel" style="display:none;">
        <div style="overflow-x:auto;"><table class="tbl" style="margin-top:4px;"><thead><tr><th>Code</th><th>Dokumento</th><th>Petsa</th><th>Channel</th><th>Status</th><th>Eligibility</th></tr></thead><tbody>${requestRows}</tbody></table></div>
      </div>`;
    container.appendChild(card);
  });
}

function toggleRRCard(header) {
  const card = header.closest('.rr-resident-card');
  const panelEl = card.querySelector('.rr-requests-panel');
  const chevron = header.querySelector('.rr-chevron');
  const isOpen = panelEl.style.display !== 'none';
  panelEl.style.display = isOpen ? 'none' : 'block';
  if (chevron) chevron.textContent = isOpen ? '▼' : '▲';
}

function advanceRequestRecord(code, nextStatus) {
  const req = REQUEST_RECORDS.find(r => r.code === code);
  if (!req) return;
  req.status = nextStatus;
  const boardReq = CERT_REQUESTS.find(r => r.code === code);
  if (boardReq) boardReq.status = nextStatus;
  addLiveAuditEntry('📋', 'cert', 'Request Proceeded', `${code} - ${nextStatus}`, currentUserName || 'Staff');
  showToast(`${code} proceeded to ${nextStatus}.`, 'green');
  renderRequestRecords(rrCurrentFilter, rrCurrentStatusFilter);
  renderCertKanban();
}

function legacyFilterRequestRecords() {
  rrCurrentFilter = document.getElementById('rr-search')?.value || '';
  renderRequestRecords(rrCurrentFilter, rrCurrentStatusFilter);
}

function legacyFilterRRStatus(status, el) {
  document.querySelectorAll('.rr-filter-btn').forEach(b => b.classList.remove('active'));
  if (el) el.classList.add('active');
  rrCurrentStatusFilter = status;
  renderRequestRecords(rrCurrentFilter, status);
}

// ═══════════════════════════════════════
// ELIGIBILITY CHECKER
// ═══════════════════════════════════════
let currentEligResidentId = null;

function elig_onResidentChange() {
  document.getElementById('elig-result').style.display = 'none';
  document.getElementById('elig-proceed-btn').style.display = 'none';
}

function populateEligResidentDropdown(preselect) {
  const sel = document.getElementById('elig-resident-select');
  if (!sel) return;
  sel.innerHTML = '<option value="">— Pumili ng Residente —</option>';
  const availableResidents = manualResidentsById.size > 0
    ? [...manualResidentsById.values()].map(residentFromApi)
    : RESIDENTS;
  availableResidents.forEach(r => {
    const opt = document.createElement('option');
    opt.value = r.id;
    const age = calcAge(r.dob);
    opt.textContent = `${r.name} (${r.id}) — ${age} yrs${age >= 60 ? ' 👴' : ''}`;
    if (preselect && r.id === preselect) opt.selected = true;
    sel.appendChild(opt);
  });
}

function openEligibilityForResident(residentId) {
  closeModal('modal-view-resident');
  closeModal('modal-rr-detail');
  populateEligResidentDropdown(residentId);
  document.getElementById('elig-result').style.display = 'none';
  document.getElementById('elig-proceed-btn').style.display = 'none';
  openModal('modal-eligibility-check');
}

function legacyRunEligibilityCheck() {
  const residentId = document.getElementById('elig-resident-select').value;
  const certId = document.getElementById('elig-doc-select').value;
  if (!residentId || !certId) { showToast('Pumili ng residente at dokumento.', 'red'); return; }
  const result = checkEligibility(residentId, certId);
  const resultEl = document.getElementById('elig-result');
  const proceedBtn = document.getElementById('elig-proceed-btn');
  const color = result.eligible ? 'var(--green-500)' : '#EF4444';
  const bg = result.eligible ? 'var(--green-dim)' : 'rgba(239,68,68,0.06)';
  const border = result.eligible ? 'var(--border-green)' : 'rgba(239,68,68,0.3)';
  const headline = result.eligible ? '✅ ELIGIBLE — Maaaring I-issue ang Dokumento' : '🚫 HINDI ELIGIBLE — Blocked ang Request';
  resultEl.style.display = 'block';
  resultEl.innerHTML = `
    <div style="background:${bg};border:1px solid ${border};border-radius:var(--radius);padding:14px;margin-bottom:12px;">
      <div style="font-weight:700;color:${color};font-size:13px;margin-bottom:8px;">${headline}</div>
      <div style="font-size:12.5px;color:var(--text-secondary);"><strong style="color:var(--text-primary);">Residente:</strong> ${result.resident?.name} (${result.resident?.id}) — ${calcAge(result.resident?.dob)} yrs old</div>
      <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:10px;"><strong style="color:var(--text-primary);">Dokumento:</strong> ${result.rule?.label}</div>
      <div style="display:flex;flex-direction:column;gap:6px;">${result.reasons.map(r => `<div style="font-size:12px;color:var(--text-secondary);line-height:1.5;">${r}</div>`).join('')}</div>
    </div>`;
  proceedBtn.style.display = result.eligible ? 'block' : 'none';
  currentEligResidentId = residentId;
}

function legacyCheckEligibility(residentId, certId) {
  const resident = RESIDENTS.find(r => r.id === residentId);
  const status = RESIDENT_STATUS[residentId];
  const rule = ELIGIBILITY_RULES[certId];
  if (!resident || !status || !rule) return { eligible: false, reasons: ['Hindi nahanap ang residente o dokumento.'] };
  const reasons = [];
  let eligible = true;
  if (rule.requiresActive && resident.status !== 'Active') {
    eligible = false; reasons.push('❌ Hindi active ang residente.');
  }
  if (rule.needsGoodStanding) {
    // Check blotter flag directly (set manually on resident record)
    if (status.blotter) {
      eligible = false;
      const blotterList = status.blotterDetails?.length
        ? status.blotterDetails.map(b => `• ${b}`).join('<br>')
        : '(walang detalye)';
      reasons.push(`❌ Naka-blotter ang residente:<br>${blotterList}`);
    }
    // Check good_standing flag
    if (!status.goodStanding) {
      eligible = false;
      reasons.push('❌ Hindi nasa mabuting kalagayan (good standing) ang residente.');
    }
    // Check incident reports where resident is the complainee
    const nameLower = resident.name.trim().toLowerCase();
    const blotterIncidents = (typeof INCIDENTS !== 'undefined' ? INCIDENTS : []).filter(inc =>
      inc.complainee && inc.complainee.trim().toLowerCase() === nameLower
    );
    if (blotterIncidents.length > 0) {
      eligible = false;
      const list = blotterIncidents.map(i => `• ${i.type} — ${i.date} (${i.id})`).join('<br>');
      reasons.push(`❌ Nakasangkot sa ${blotterIncidents.length} incident report bilang ine-reklamo:<br>${list}`);
    }
    if (eligible) reasons.push('✅ Nasa mabuting kalagayan sa barangay. Walang blotter records.');
  }
  if (rule.oneTimeOnly) {
    const prev = REQUEST_RECORDS.filter(r => r.residentId === residentId && r.certId === certId && r.status === 'Completed');
    if (prev.length > 0) { eligible = false; reasons.push(`❌ One-time only. Nakuha na noong: ${prev[0].date}.`); }
  }
  if (resident.status === 'Active') reasons.push('✅ Active resident.');
  return { eligible, reasons, resident, rule, status };
}

function legacyBrowserEligProceedRequest() {
  const residentId = document.getElementById('elig-resident-select').value;
  const certId = document.getElementById('elig-doc-select').value;
  const ct = CERTIFICATE_TYPES.find(c => c.id === certId);
  const resident = RESIDENTS.find(r => r.id === residentId);
  const code = 'REQ-' + (7800 + Math.floor(Math.random() * 100));
  REQUEST_RECORDS.unshift({ residentId, code, type: ct?.label || '', certId, date: new Date().toLocaleDateString('en-PH', {month:'short',day:'numeric',year:'numeric'}), status: 'Processing', via: 'Walk-in', purpose: 'Issued via Eligibility Check', eligible: true, eligNote: '' });
  CERT_REQUESTS.unshift({ code, name: resident?.name || '', type: ct?.label || '', requested: new Date().toLocaleString('en-PH'), status: 'Processing', via: 'Walk-in' });
  renderCertRequests();
  renderRequestRecords(rrCurrentFilter, rrCurrentStatusFilter);
  showToast(`Request ${code} na-add!`, 'green');
  closeModal('modal-eligibility-check');
}

function legacyOpenViewResidentRequests(residentId) {
  if (!residentId) return;
  const r = RESIDENTS.find(x => x.id === residentId);
  const requests = REQUEST_RECORDS.filter(req => req.residentId === residentId);
  document.getElementById('rr-detail-title').textContent = `📋 ${r?.name} — Request History`;
  const rows = requests.length === 0
    ? '<tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:16px;">Wala pang request records.</td></tr>'
    : requests.map(req => {
        const sc = req.status === 'Completed' ? 'badge-green' : req.status === 'Ready to Print' ? 'badge-blue' : req.status === 'Blocked' ? 'badge-red' : 'badge-amber';
        return `<tr>
          <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--green-500);">${req.code}</span></td>
          <td style="font-size:12px;">${req.type}</td>
          <td style="font-size:11px;color:var(--text-muted);">${req.date}</td>
          <td><span class="badge ${sc}" style="font-size:10px;">${req.status}</span></td>
          <td>${req.eligible ? '<span class="badge badge-green" style="font-size:10px;">✅ Eligible</span>' : '<span class="badge badge-red" style="font-size:10px;">🚫 Blocked</span>'}</td>
        </tr>`;
      }).join('');
  document.getElementById('rr-detail-content').innerHTML = `
    <div style="overflow-x:auto;"><table class="tbl"><thead><tr><th>Code</th><th>Dokumento</th><th>Petsa</th><th>Status</th><th>Eligibility</th></tr></thead><tbody>${rows}</tbody></table></div>`;
  closeModal('modal-view-resident');
  openModal('modal-rr-detail');
}

window.addEventListener('DOMContentLoaded', () => { console.log('SmartBrgy Anabu I-G — Ready'); });

// ═══════════════════════════════════════
// NOTIFICATIONS SYSTEM
// ═══════════════════════════════════════
const NOTIFICATIONS = [];

function renderNotifications() {
  const list = document.getElementById('notif-list');
  if (!list) return;
  list.innerHTML = NOTIFICATIONS.length ? '' : '<div class="civic-empty">No recent notifications.</div>';
  NOTIFICATIONS.forEach(n => {
    const div = document.createElement('div');
    div.className = 'notif-item' + (n.read ? '' : ' unread');
    div.onclick = () => { n.read = true; updateNotifBadge(); renderNotifications(); toggleNotifPanel(); showScreen(n.screen, findNavItem(n.screen)); };
    div.innerHTML = `
      <div class="notif-dot" style="background:${n.dot};margin-top:5px;flex-shrink:0;"></div>
      <div style="flex:1;">
        <div class="notif-body"><strong>${escapeText(n.title)}</strong></div>
        <div class="notif-body" style="margin-top:2px;">${escapeText(n.detail)}</div>
        <div class="notif-time">${escapeText(n.time)}</div>
      </div>
      ${!n.read ? '<div style="width:7px;height:7px;border-radius:50%;background:var(--blue-400);flex-shrink:0;margin-top:4px;"></div>' : ''}`;
    list.appendChild(div);
  });
}

function updateNotifBadge() {
  const unread = NOTIFICATIONS.filter(n => !n.read).length;
  const badge = document.getElementById('notif-count');
  if (badge) { badge.textContent = unread; badge.style.display = unread ? 'flex' : 'none'; }
}

function toggleNotifPanel() {
  const panel = document.getElementById('notif-panel');
  if (!panel) return;
  panel.classList.toggle('open');
  if (panel.classList.contains('open')) { renderNotifications(); }
}

function markAllNotifsRead() {
  NOTIFICATIONS.forEach(n => n.read = true);
  updateNotifBadge(); renderNotifications();
  showToast('All notifications marked as read.', 'green');
}

document.addEventListener('click', function(e) {
  const panel = document.getElementById('notif-panel');
  const chip = document.getElementById('notif-chip');
  if (panel && panel.classList.contains('open') && !panel.contains(e.target) && !chip.contains(e.target)) {
    panel.classList.remove('open');
  }
});

// ═══════════════════════════════════════
// ENHANCED DEMOGRAPHICS
// ═══════════════════════════════════════
const SPECIAL_GROUPS = [];

function renderSpecialGroups() {
  const container = document.getElementById('special-groups-container');
  if (!container) return;
  const active = RESIDENTS.filter(r => r.status === 'Active');
  const total = Number(DEMOGRAPHIC_SUMMARY?.total ?? active.length);
  const groups = Object.keys(SPECIAL_GROUP_META).map(key => {
    const count = key === 'Senior Citizen'
      ? Number(DEMOGRAPHIC_SUMMARY?.seniors ?? active.filter(r => isSenior(r.dob)).length)
      : Number(DEMOGRAPHIC_SUMMARY?.special_groups?.[key] ?? active.filter(r => getResidentGroups(r).includes(key)).length);
    const meta = SPECIAL_GROUP_META[key];
    return { ...meta, count, pct: total ? ((count / total) * 100).toFixed(1) : '0.0' };
  });
  container.innerHTML = '<div class="special-groups-grid">' +
    groups.map(g => `
      <div class="special-group-card" style="border-color:${g.border};background:linear-gradient(135deg,${g.bg},var(--bg-card));">
        <div class="sg-header">
          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:20px;">${g.icon}</span>
            <div>
              <div class="sg-label">${g.label}</div>
              <div class="sg-sub">${g.sub}</div>
            </div>
          </div>
        </div>
        <div class="sg-count" style="color:${g.color};">${g.count.toLocaleString()}</div>
        <div class="sg-pct">${g.pct}% of population</div>
      </div>`).join('') + '</div>';
}

function renderPurokCards() {
  const grid = document.getElementById('demo-purok-grid');
  if (!grid) return;
  syncPurokSelects();
  grid.innerHTML = '';
  const countByPurok = {};
  const seniorByPurok = {};
  const pwdByPurok = {};
  const beneByPurok = {};
  RESIDENTS.forEach(r => {
    countByPurok[r.purok] = (countByPurok[r.purok] || 0) + 1;
    if (isSenior(r.dob)) seniorByPurok[r.purok] = (seniorByPurok[r.purok] || 0) + 1;
    const groups = getResidentGroups(r);
    if (groups.includes('PWD')) pwdByPurok[r.purok] = (pwdByPurok[r.purok] || 0) + 1;
    if (groups.includes('4Ps Beneficiary')) beneByPurok[r.purok] = (beneByPurok[r.purok] || 0) + 1;
  });
  const total = Number(DEMOGRAPHIC_SUMMARY?.total ?? RESIDENTS.length);
  PUROK_DATA.forEach(p => {
    const count = Number(p.residentsCount ?? countByPurok[p.key] ?? 0);
    const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0';
    const barPct = total > 0 ? (count / total * 100) : 0;
    const seniors = Number(p.seniorCount ?? seniorByPurok[p.key] ?? 0);
    const pwd = Number(p.pwdCount ?? pwdByPurok[p.key] ?? 0);
    const bene = Number(p.fourPsCount ?? beneByPurok[p.key] ?? 0);
    grid.innerHTML += `
      <div class="demo-purok-card">
        <div class="demo-purok-name">📍 ${escapeText(p.label)}</div>
        <div class="demo-purok-pop" style="color:${p.color};">${count.toLocaleString()}</div>
        <div class="demo-purok-pct">${pct}% of total population</div>
        <div class="demo-purok-bar" style="margin:8px 0 6px;"><div class="progress-bar"><div class="progress-fill" style="width:${barPct}%;background:${p.color};"></div></div></div>
        <div class="sg-tags">
          ${seniors > 0 ? `<span class="demo-purok-tag" style="color:var(--senior-color);border-color:rgba(245,158,11,0.25);">👴 ${seniors} Seniors</span>` : ''}
          ${pwd > 0 ? `<span class="demo-purok-tag" style="color:#A78BFA;border-color:rgba(139,92,246,0.25);">♿ ${pwd} PWD</span>` : ''}
          ${bene > 0 ? `<span class="demo-purok-tag" style="color:#EF4444;border-color:rgba(239,68,68,0.25);">💰 ${bene} 4Ps</span>` : ''}
        </div>
      </div>`;
  });
}

function renderAgeDistribution() {
  const list = document.getElementById('age-distribution-list');
  if (!list) return;
  const groups = [
    { label: '0–12 (Children)',        color: '#60A5FA',             min: 0,  max: 12  },
    { label: '13–17 (Youth)',           color: 'var(--green-400)',    min: 13, max: 17  },
    { label: '18–35 (Young Adults)',    color: 'var(--green-500)',    min: 18, max: 35  },
    { label: '36–59 (Middle Age)',      color: 'var(--blue-400)',     min: 36, max: 59  },
    { label: '60+ (Senior Citizens)',   color: 'var(--senior-color)', min: 60, max: 999 },
  ];
  const counts = groups.map(g => ({ ...g, count: 0 }));
  RESIDENTS.forEach(r => {
    const age = calcAge(r.dob);
    const g = counts.find(x => age >= x.min && age <= x.max);
    if (g) g.count++;
  });
  if (DEMOGRAPHIC_SUMMARY?.age_groups) {
    const summaryCounts = DEMOGRAPHIC_SUMMARY.age_groups;
    counts[0].count = Number(summaryCounts.children || 0);
    counts[1].count = Number(summaryCounts.youth || 0);
    counts[2].count = Number(summaryCounts.young_adults || 0);
    counts[3].count = Number(summaryCounts.middle_age || 0);
    counts[4].count = Number(summaryCounts.seniors || 0);
  }
  const total = Number(DEMOGRAPHIC_SUMMARY?.total ?? RESIDENTS.length);
  const maxCount = Math.max(...counts.map(g => g.count), 1);
  list.innerHTML = counts.map(g => {
    const pct = total > 0 ? ((g.count / total) * 100).toFixed(1) : '0.0';
    const barPct = (g.count / maxCount * 100).toFixed(0);
    return `
    <div class="age-bar-item">
      <div class="age-bar-label">${g.label}</div>
      <div style="flex:1;"><div class="progress-bar"><div class="progress-fill" style="width:${barPct}%;background:${g.color};"></div></div></div>
      <div class="age-bar-count" style="color:${g.color};">${g.count.toLocaleString()} <span style="font-size:10px;font-weight:400;color:var(--text-muted);">(${pct}%)</span></div>
    </div>`;
  }).join('');
}

function renderDemographicsStats() {
  const active = RESIDENTS.filter(r => r.status === 'Active');
  const total  = Number(DEMOGRAPHIC_SUMMARY?.total ?? active.length);
  const male   = Number(DEMOGRAPHIC_SUMMARY?.male ?? active.filter(r => r.gender === 'Male').length);
  const female = Number(DEMOGRAPHIC_SUMMARY?.female ?? active.filter(r => r.gender === 'Female').length);
  const seniors = Number(DEMOGRAPHIC_SUMMARY?.seniors ?? active.filter(r => isSenior(r.dob)).length);

  const setEl = (id, val) => { const el = document.getElementById(id); if (el) { el.textContent = val.toLocaleString(); el.dataset.target = val; } };
  const setSub = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };

  setEl('demo-stat-total',      total);
  setEl('demo-stat-male',       male);
  setEl('demo-stat-female',     female);
  setEl('demo-stat-households', seniors);
  setSub('demo-sub-male',       total > 0 ? `${((male   / total) * 100).toFixed(1)}% ng populasyon` : '—');
  setSub('demo-sub-female',     total > 0 ? `${((female / total) * 100).toFixed(1)}% ng populasyon` : '—');
  setSub('demo-sub-households', 'Age 60 and above');
}

function renderDemographics() {
  renderDemographicsStats();
  renderPurokCards();
  renderAgeDistribution();
  renderSeniorList();
  renderSpecialGroups();
}

// ═══════════════════════════════════════
// CERTIFICATES KANBAN
// ═══════════════════════════════════════
const CERT_LANES = [
  { id: 'online',     label: 'Online Requests', color: '#A78BFA',          icon: '🌐', next: 'In Progress',    nextLabel: '▶ Process',        nextClass: 'btn-primary' },
  { id: 'processing', label: 'Processing',       color: '#F59E0B',          icon: '⏳', next: 'Ready to Print', nextLabel: '▶ Ready to Print', nextClass: 'btn-primary' },
  { id: 'ready',      label: 'Ready to Print',   color: 'var(--blue-400)', icon: '🖨️', next: 'Completed',      nextLabel: '✅ Print & Release',nextClass: 'btn-green'   },
  { id: 'completed',  label: 'Released / Done',  color: 'var(--green-500)',icon: '✅', next: null,              nextLabel: null,               nextClass: ''            },
];

function getLane(status, via) {
  if (status === 'Completed') return 'completed';
  if (status === 'Ready to Print') return 'ready';
  if (via === 'Online' && status === 'Pending') return 'online';
  if (status === 'In Progress') return 'processing';
  return 'processing';
}

async function advanceCertStatus(code, newStatus, event) {
  if (event) event.stopPropagation();

  const req = CERT_REQUESTS.find(r => r.code === code);
  if (!req) return;

  // Do NOT mark as released yet.
  // Open the Issue Certificate modal first.
  if (newStatus === 'Completed') {
    if (typeof printCert === 'function') {
      printCert(code);
    }
    return;
  }

  const statusMap = {
    'In Progress': 'processing',
    'Ready to Print': 'ready_for_release'
  };

  const laravelStatus = statusMap[newStatus];

  if (!laravelStatus) {
    showToast('Invalid status update.', 'red');
    return;
  }

  const button = event?.currentTarget;

  if (button) {
    button.disabled = true;
    button.dataset.originalText = button.innerHTML;
    button.innerHTML = '⏳ Updating...';
  }

  try {
    const response = await fetch(
      `/admin/document-requests/${req.id}/status`,
      {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...csrfRequestHeaders()
        },
        body: JSON.stringify({
          status: laravelStatus
        })
      }
    );

    if (!response.ok) {
      const errorData = await response.json().catch(() => null);
      throw new Error(
        errorData?.message || 'Status update failed.'
      );
    }

    req.status = newStatus;
    req.laravelStatus = laravelStatus;

    const rr = REQUEST_RECORDS.find(r => r.code === code);

    if (rr) {
      rr.status = newStatus;
    }

    await refreshDocumentRequestsLive();

    showToast(`✅ ${code} → ${newStatus}`, 'green');

    if (typeof addLiveAuditEntry === 'function') {
      addLiveAuditEntry(
        '📋',
        'cert',
        `Request status → ${newStatus}`,
        code,
        'Staff'
      );
    }

  } catch (error) {
    console.error('Status update error:', error);

    showToast(
      error.message || 'Failed to update request status.',
      'red'
    );

    if (button) {
      button.disabled = false;
      button.innerHTML =
        button.dataset.originalText || 'Retry';
    }
  }
}

function renderCertKanban(filter = '') {

  // Convert real Laravel/MySQL requests into the format
  // expected by the original SmartBrgy Kanban UI
  if (Array.isArray(window.LARAVEL_DOCUMENT_REQUESTS)) {

    const laravelRequests = window.LARAVEL_DOCUMENT_REQUESTS
      .filter(r => r.status !== 'rejected')
      .map(r => {

        let uiStatus = 'Pending';

        if (r.status === 'processing') {
          uiStatus = 'In Progress';
        }

        if (
          r.status === 'approved' ||
          r.status === 'ready_for_release'
        ) {
          uiStatus = 'Ready to Print';
        }

        if (r.status === 'released') {
          uiStatus = 'Completed';
        }

        return {
          id: r.id,
          code: r.reference_code,
          name: r.full_name,
          type: r.document_type,
          status: uiStatus,
          via: r.source === 'online' ? 'Online' : 'Walk-in',

          requested: r.created_at
            ? new Date(r.created_at).toLocaleString('en-PH')
            : null,
          dateOfBirth: r.date_of_birth,
          address: r.address,
          email: r.email,
          purpose: r.purpose,
          businessName: r.business_name,
          attachment: r.attachment_path,
          remarks: r.remarks,

          hidden: false,
          laravelStatus: r.status
        };
      });

    CERT_REQUESTS.splice(
      0,
      CERT_REQUESTS.length,
      ...laravelRequests
    );
  }

  const board = document.getElementById('cert-kanban-board');

  if (!board) return;

  const laneScrollPositions = {};

  board.querySelectorAll('.cert-lane[data-lane-id]').forEach(laneElement => {
    laneScrollPositions[laneElement.dataset.laneId] = laneElement.scrollTop;
  });

  board.innerHTML = '';

  const grouped = {
    online: [],
    processing: [],
    ready: [],
    completed: []
  };

  CERT_REQUESTS.forEach(r => {

    if (r.hidden) return;

    const searchValue = (filter || '').toLowerCase().trim();

    if (
      searchValue &&
      !String(r.name || '').toLowerCase().includes(searchValue) &&
      !String(r.code || '').toLowerCase().includes(searchValue) &&
      !String(r.type || '').toLowerCase().includes(searchValue)
    ) {
      return;
    }

    const lane = getLane(r.status, r.via);

    if (grouped[lane]) {
      grouped[lane].push(r);
    }
  });

  CERT_LANES.forEach(lane => {

    const items = grouped[lane.id] || [];

    const laneEl = document.createElement('div');

    laneEl.className = 'cert-lane';
    laneEl.dataset.laneId = lane.id;

    laneEl.innerHTML = `
      <div class="cert-lane-header">

        <span style="color:${lane.color};">
          ${lane.icon} ${lane.label}
        </span>

        <span
          class="badge"
          style="
            background:rgba(255,255,255,0.06);
            color:var(--text-muted);
            border-color:var(--border);
          "
        >
          ${items.length}
        </span>

      </div>

      ${
        items.length === 0

        ? `
          <div
            style="
              text-align:center;
              color:var(--text-muted);
              font-size:11px;
              padding:18px 0;
            "
          >
            No requests
          </div>
        `

        : items.map(r => {

            const ct = CERTIFICATE_TYPES.find(
              c => c.label === r.type
            );

            const viaBadge = `
              <span
                class="badge ${
                  r.via === 'Online'
                    ? 'badge-purple'
                    : 'badge-gray'
                }"
                style="font-size:9.5px;"
              >
                ${r.via}
              </span>
            `;

            const proceedBtn = lane.next

              ? `
                <button
                  class="btn btn-xs ${lane.nextClass}"
                  style="
                    flex:1;
                    font-size:9.5px;
                    padding:3px 6px;
                  "
                  onclick="
                    advanceCertStatus(
                      '${r.code}',
                      '${lane.next}',
                      event
                    )
                  "
                >
                  ${lane.nextLabel}
                </button>
              `

              : '';

            const viewBtn = `
              <button
                class="btn btn-xs"
                style="
                  flex:1;
                  font-size:9.5px;
                  padding:3px 6px;
                  background:rgba(234,179,8,0.12);
                  border-color:rgba(234,179,8,0.4);
                  color:#EAB308;
                "
                onclick="openViewCertReq('${r.code}')"
              >
                👁 View
              </button>
            `;

            const rejectBtn = lane.id !== 'completed' && r.via === 'Online'
              ? `
                <button
                  class="btn btn-xs btn-danger"
                  style="flex:1;font-size:9.5px;padding:3px 6px;"
                  onclick="openRejectRequest(${Number(r.id)}, '${r.code}', event)"
                >
                  Reject
                </button>
              `
              : '';

            const btnRow = `
              <div
                style="
                  display:flex;
                  gap:4px;
                  margin-top:7px;
                "
              >
                ${proceedBtn}
                ${viewBtn}
                ${rejectBtn}
              </div>
            `;

            return `
              <div class="cert-card">

                <div
                  style="
                    display:flex;
                    align-items:center;
                    gap:6px;
                    margin-bottom:4px;
                  "
                >

                  <span style="font-size:15px;">
                    ${ct?.icon || '📄'}
                  </span>

                  <div class="cert-card-name">
                    ${escapeText(r.name)}
                  </div>

                </div>

                <div class="cert-card-type">
                  ${escapeText(r.type)}
                </div>

                <div class="cert-card-code">
                  ${escapeText(r.code)}
                </div>

                <div
                  class="cert-card-meta"
                  style="margin-top:5px;"
                >
                  ${viaBadge}
                </div>

                ${btnRow}

              </div>
            `;
          }).join('')
      }
    `;

    board.appendChild(laneEl);

    const savedScrollTop = laneScrollPositions[lane.id];

    if (Number.isFinite(savedScrollTop)) {
      laneEl.scrollTop = savedScrollTop;
    }
  });

  // Update certificate dashboard counters
  const setCount = (id, value) => {

    const element = document.getElementById(id);

    if (element) {
      element.textContent = value;
    }

  };

  setCount(
    'cert-today',
    CERT_REQUESTS.filter(
      r => r.status === 'Completed'
    ).length
  );

  setCount(
    'cert-pending',
    CERT_REQUESTS.filter(
      r => r.status === 'In Progress'
    ).length
  );

  setCount(
    'cert-ready',
    CERT_REQUESTS.filter(
      r => r.status === 'Ready to Print'
    ).length
  );

  setCount(
    'cert-online',
    CERT_REQUESTS.filter(
      r =>
        r.via === 'Online' &&
        !r.hidden
    ).length
  );

  buildCharts();
  refreshDashboardStats();
}

function filterCertBoard(val) { renderCertKanban(val.toLowerCase()); }

function openViewCertReq(code) {
  const r = CERT_REQUESTS.find(x => x.code === code);
  if (!r) return;
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val || '—'; };
  set('vreq-code',    r.code);
  set('vreq-type',    r.type);
  set('vreq-name',    r.name);
  set('vreq-date',    r.requested || '—');
  set('vreq-address', r.address || '—');
  set('vreq-email',   r.email || '—');
  set('vreq-dob',     r.dateOfBirth || r.dob || '—');
  set('vreq-purpose', r.purpose || '—');
  const statusColors = { 'Processing':'#F59E0B', 'Ready to Print':'var(--blue-400)', 'Completed':'#22C55E' };
  const badgeEl = document.getElementById('vreq-status-badge');
  if (badgeEl) {
    badgeEl.textContent = r.status;
    badgeEl.style.cssText = `font-size:11px;font-weight:700;padding:2px 10px;border-radius:999px;background:rgba(255,255,255,0.06);border:1px solid var(--border);color:${statusColors[r.status]||'var(--text-muted)'}`;
  }
  const attWrap = document.getElementById('vreq-attachment-wrap');
  const attEl   = document.getElementById('vreq-attachment');
  if (attWrap && attEl && r.attachment) {
    const isImg = /\.(png|jpe?g|gif|webp|heic)$/i.test(r.attachment);
    attEl.innerHTML = isImg
      ? `<a href="${API}${r.attachment}" target="_blank"><img src="${API}${r.attachment}" style="max-width:100%;max-height:200px;border-radius:8px;border:1px solid var(--border);object-fit:contain;" /></a>`
      : `<a href="${API}${r.attachment}" target="_blank" style="font-size:12px;padding:6px 12px;border:1px solid var(--border);border-radius:6px;background:var(--bg-glass);color:var(--blue-400);">📎 Buksan ang Attachment</a>`;
    attWrap.style.display = 'block';
  } else if (attWrap) {
    attWrap.style.display = 'none';
  }
  openModal('modal-view-certreq');
}

function renderCertTypesList() {
  const container = document.getElementById('cert-types-list');
  if (!container) return;
  container.innerHTML = CERTIFICATE_TYPES.map(ct => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;transition:all 0.15s;" onclick="openModal('modal-cert-issue')" onmouseenter="this.style.borderColor='var(--border-green)'" onmouseleave="this.style.borderColor='var(--border)'">
      <div style="display:flex;align-items:center;gap:10px;">
        <span style="font-size:18px;">${ct.icon}</span>
        <div>
          <div style="font-size:12.5px;font-weight:600;color:var(--text-primary);">${ct.label}</div>
          <div style="font-size:10.5px;color:var(--text-muted);">Processing: ${ct.days}</div>
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:12px;font-weight:700;color:var(--green-500);">${ct.fee}</div>
        <button class="btn btn-xs btn-primary" style="margin-top:3px;">Issue</button>
      </div>
    </div>`).join('');
}

// ═══════════════════════════════════════
// ENHANCED AUDIT LOG
// ═══════════════════════════════════════
const LIVE_AUDIT_LOGS = [];

let auditCurrentType = 'all';
let auditCurrentSearch = '';

function renderAuditLog() {
  const container = document.getElementById('audit-log-list');
  if (!container) return;
  let logs = [...LIVE_AUDIT_LOGS];
  if (auditCurrentType !== 'all') logs = logs.filter(l => l.type === auditCurrentType);
  if (auditCurrentSearch) logs = logs.filter(l => l.action.toLowerCase().includes(auditCurrentSearch) || l.detail.toLowerCase().includes(auditCurrentSearch) || l.user.toLowerCase().includes(auditCurrentSearch));
  const dateFilter = document.getElementById('audit-date-filter')?.value;
  if (dateFilter) logs = logs.filter(log => log.date === dateFilter);
  // Update stats
  const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
  setEl('astat-total', LIVE_AUDIT_LOGS.length);
  setEl('astat-auth', LIVE_AUDIT_LOGS.filter(l => l.type === 'auth').length);
  setEl('astat-records', LIVE_AUDIT_LOGS.filter(l => l.type === 'record').length);
  setEl('astat-certs', LIVE_AUDIT_LOGS.filter(l => l.type === 'cert').length);
  setEl('astat-security', LIVE_AUDIT_LOGS.filter(l => l.type === 'security').length);
  const showCount = document.getElementById('audit-showing-count');
  if (showCount) showCount.textContent = `Showing ${logs.length} of ${LIVE_AUDIT_LOGS.length} events`;
  container.innerHTML = '';
  if (logs.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:32px 0;">No events match the current filter.</div>';
    return;
  }
  logs.forEach(log => {
    const sev = log.severity === 'danger' ? 'rgba(239,68,68,0.07)' : log.severity === 'warn' ? 'rgba(245,158,11,0.06)' : '';
    const typeColor = { auth: 'var(--green-500)', cert: '#F59E0B', rfid: 'var(--blue-400)', record: '#A78BFA', incident: '#FB923C', security: '#EF4444' }[log.type] || 'var(--text-muted)';
    const typeBg = { auth: 'rgba(0,255,106,0.08)', cert: 'rgba(245,158,11,0.08)', rfid: 'rgba(42,126,211,0.08)', record: 'rgba(139,92,246,0.08)', incident: 'rgba(251,146,60,0.08)', security: 'rgba(239,68,68,0.08)' }[log.type] || 'var(--bg-glass)';
    const row = document.createElement('div');
    row.className = 'audit-log-row';
    row.style.background = sev;
    row.innerHTML = `
      <div class="audit-type-icon" style="background:${typeBg};border-color:${typeColor}20;">${escapeText(log.icon)}</div>
      <div class="audit-time">${escapeText(log.time)}</div>
      <div>
        <div class="audit-action">${escapeText(log.action)}</div>
        <div class="audit-detail">${escapeText(log.detail)}</div>
      </div>
      <div style="font-size:11.5px;color:var(--text-secondary);">${escapeText(log.user)}</div>
      <span class="badge" style="background:${typeBg};color:${typeColor};border-color:${typeColor}30;font-size:9.5px;">${log.type.toUpperCase()}</span>`;
    container.appendChild(row);
  });
}

function filterAuditType(type, el) {
  document.querySelectorAll('.audit-type-btn').forEach(b => b.classList.remove('active'));
  if (el) el.classList.add('active');
  auditCurrentType = type;
  renderAuditLog();
}
function filterAuditLog() {
  auditCurrentSearch = document.getElementById('audit-search')?.value?.toLowerCase() || '';
  renderAuditLog();
}
function clearAuditFilter() {
  auditCurrentType = 'all';
  auditCurrentSearch = '';
  const s = document.getElementById('audit-search'); if (s) s.value = '';
  const d = document.getElementById('audit-date-filter'); if (d) d.value = '';
  document.querySelectorAll('.audit-type-btn').forEach(b => b.classList.remove('active'));
  document.querySelector('.audit-type-btn')?.classList.add('active');
  renderAuditLog();
}

async function reloadAuditLog() {
  try {
    const payload = await adminRequest('/admin/audit-log');
    LIVE_AUDIT_LOGS.splice(0, LIVE_AUDIT_LOGS.length, ...payload.events);
    renderAuditLog();
  } catch (error) {
    document.getElementById('audit-log-list').textContent = error.message;
    showToast(error.message, 'red');
  }
}

function addLiveAuditEntry() {
  if (document.getElementById('screen-audit')?.classList.contains('active')) void reloadAuditLog();
  if (document.getElementById('screen-dashboard')?.classList.contains('active')) void refreshDashboardStats();
}

// ═══════════════════════════════════════
// ENHANCED USER MANAGEMENT
// ═══════════════════════════════════════
const ACCESS_PERMS = {
  'Staff Access': ['Dashboard', 'Records', 'Certificates', 'Requests', 'Incidents', 'QR', 'Audit', 'Settings'],
  'Full Access':             ['Dashboard', 'Records', 'Certificates', 'Requests', 'Incidents', 'RFID', 'Cabinet', 'QR', 'Face', 'Audit', 'Users', 'Settings'],
  'Records & Certificates':  ['Dashboard', 'Records', 'Certificates', 'Requests', 'QR'],
  'Certificates Only':       ['Dashboard', 'Certificates', 'QR'],
  'Incidents Only':          ['Dashboard', 'Incidents'],
  'View Only':               ['Dashboard'],
};
const ALL_PERMS = ['Dashboard', 'Records', 'Certificates', 'Requests', 'Incidents', 'RFID', 'Cabinet', 'QR', 'Face', 'Audit', 'Users', 'Settings'];

let userRoleFilter = 'all';
let userSearch = '';

async function adminRequest(url, options = {}) {
  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...csrfRequestHeaders(), ...options.headers },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(payload.errors ? Object.values(payload.errors).flat()[0] : payload.message || 'The request could not be completed. Please try again.');
  }
  return payload;
}

async function reloadUsers() {
  const container = document.getElementById('users-list-container');
  try {
    const payload = await adminRequest('/admin/users');
    USERS.splice(0, USERS.length, ...payload.users);
    renderUsers();
  } catch (error) {
    if (container) container.textContent = error.message;
    showToast(error.message, 'red');
  }
}

function renderUsers() {
  const container = document.getElementById('users-list-container');
  if (!container) return;
  const users = USERS.filter(user =>
    (userRoleFilter !== 'admin' || user.role === 'admin') &&
    (userRoleFilter !== 'active' || user.is_active) &&
    (userRoleFilter !== 'suspended' || !user.is_active) &&
    (!userSearch || `${user.name} ${user.email} ${user.role}`.toLowerCase().includes(userSearch))
  );
  document.getElementById('usr-active').textContent = USERS.filter(user => user.is_active).length;
  document.getElementById('usr-suspended').textContent = USERS.filter(user => !user.is_active).length;
  container.innerHTML = users.length ? users.map(user => `
    <div class="user-card ${user.is_active ? '' : 'suspended'}">
      <div class="user-avatar-lg">${escapeText(initials(user.name))}</div>
      <div class="user-info">
        <div class="user-name">${escapeText(user.name)}</div>
        <div class="user-role-tag">${escapeText(user.email)}</div>
        <div class="user-badges"><span class="badge badge-blue">${escapeText(user.role)}</span>
          <span class="badge ${user.is_active ? 'badge-green' : 'badge-red'}">${user.is_active ? 'Active' : 'Suspended'}</span>
          <span class="badge badge-gray">${user.two_factor_confirmed_at ? '2FA enabled' : '2FA not configured'}</span>
        </div>
      </div>
      <div class="user-actions"><button class="btn btn-primary" onclick="openEditUser(${Number(user.id)})">Edit account</button>
      ${Number(user.id) !== Number(window.AUTHENTICATED_USER.id) ? `<button class="btn" onclick="setUserActive(${Number(user.id)}, ${!user.is_active})">${user.is_active ? 'Suspend' : 'Activate'}</button>` : ''}</div>
    </div>`).join('') : '<div class="card civic-empty">No accounts match your filters.</div>';
}

function filterUsers() {
  userSearch = document.getElementById('user-search').value.trim().toLowerCase();
  renderUsers();
}

function filterUserRole(role, element) {
  userRoleFilter = role;
  document.querySelectorAll('.user-role-filter').forEach(button => button.classList.toggle('active', button === element));
  renderUsers();
}

function openAddUser() {
  ['adduser-edit-id', 'adduser-name', 'adduser-email', 'adduser-password'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('adduser-role').value = 'staff';
  document.getElementById('adduser-status').value = 'active';
  document.getElementById('adduser-password').required = true;
  document.getElementById('adduser-modal-title').textContent = 'New user account';
  openModal('modal-adduser');
}

function openEditUser(id) {
  const user = USERS.find(item => item.id === id);
  if (!user) return;
  document.getElementById('adduser-edit-id').value = user.id;
  document.getElementById('adduser-name').value = user.name;
  document.getElementById('adduser-email').value = user.email;
  document.getElementById('adduser-role').value = user.role;
  document.getElementById('adduser-status').value = user.is_active ? 'active' : 'suspended';
  document.getElementById('adduser-password').value = '';
  document.getElementById('adduser-password').required = false;
  document.getElementById('adduser-modal-title').textContent = 'Edit user account';
  openModal('modal-adduser');
}

async function saveNewUser() {
  const id = document.getElementById('adduser-edit-id').value;
  const payload = {
    name: document.getElementById('adduser-name').value.trim(),
    email: document.getElementById('adduser-email').value.trim(),
    role: document.getElementById('adduser-role').value,
    is_active: document.getElementById('adduser-status').value === 'active',
    password: document.getElementById('adduser-password').value || null,
  };
  const button = document.getElementById('adduser-save-btn');
  button.disabled = true;
  try {
    const result = await adminRequest(id ? `/admin/users/${id}` : '/admin/users', { method: id ? 'PATCH' : 'POST', body: JSON.stringify(payload) });
    closeModal('modal-adduser');
    showToast(result.message, 'green');
    await reloadUsers();
  } catch (error) {
    showToast(error.message, 'red');
  } finally {
    button.disabled = false;
  }
}

async function setUserActive(id, active) {
  const user = USERS.find(item => item.id === id);
  if (!user || !confirm(`${active ? 'Activate' : 'Suspend'} ${user.name}'s account?`)) return;
  try {
    const result = await adminRequest(`/admin/users/${id}`, { method: 'PATCH', body: JSON.stringify({ name: user.name, email: user.email, role: user.role, is_active: active }) });
    showToast(result.message, 'green');
    await reloadUsers();
  } catch (error) {
    showToast(error.message, 'red');
  }
}

function exportUsers() {
  const cell = value => {
    const text = String(value ?? '');
    return '"' + (/^[=+@\-\t\r]/.test(text) ? "'" : '') + text.replaceAll('"', '""') + '"';
  };
  const rows = [['Name', 'Email', 'Role', 'Status'], ...USERS.map(user => [user.name, user.email, user.role, user.is_active ? 'Active' : 'Suspended'])];
  const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.map(row => row.map(cell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
  const link = document.createElement('a');
  link.href = url; link.download = 'barangay-users.csv'; link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// ═══════════════════════════════════════
// REQUEST RECORDS — ELIGIBILITY RULES PANEL
// ═══════════════════════════════════════
function renderEligRulesGrid() {
  const grid = document.getElementById('elig-rules-grid');
  if (!grid) return;
  grid.innerHTML = Object.entries(ELIGIBILITY_RULES).map(([id, rule]) => {
    const ct = CERTIFICATE_TYPES.find(c => c.id === id);
    return `<div style="background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-sm);padding:11px 12px;">
      <div style="display:flex;align-items:center;gap:7px;margin-bottom:7px;">
        <span style="font-size:15px;">${ct?.icon || '📄'}</span>
        <div style="font-size:12px;font-weight:700;color:var(--text-primary);">${rule.label}</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;font-size:11px;">
        <div style="color:${rule.needsGoodStanding ? '#EF4444' : 'var(--text-muted)'};">${rule.needsGoodStanding ? '⚠️ Requires clean record (no blotter)' : '✅ No clean record required'}</div>
        <div style="color:${rule.oneTimeOnly ? '#F59E0B' : 'var(--text-muted)'};">${rule.oneTimeOnly ? '🔒 One-time only per resident' : '🔄 Can be requested multiple times'}</div>
        <div style="color:${rule.requiresActive ? 'var(--green-500)' : 'var(--text-muted)'};">✅ Active resident status required</div>
        <div style="color:var(--text-muted);margin-top:3px;">Fee: <strong style="color:var(--text-primary);">${ct?.fee || '—'}</strong> | Processing: ${ct?.days || '—'}</div>
      </div>
    </div>`;
  }).join('');
}

function toggleEligRules() {
  const panel = document.getElementById('elig-rules-panel');
  const chevron = document.getElementById('elig-rules-chevron');
  if (!panel) return;
  const visible = panel.style.display !== 'none';
  panel.style.display = visible ? 'none' : 'block';
  if (chevron) chevron.textContent = visible ? '▼ Show' : '▲ Hide';
  if (!visible) renderEligRulesGrid();
}

// ═══════════════════════════════════════
// OVERRIDE renderCertRequests to use kanban
// ═══════════════════════════════════════
function renderCertRequests(filter = '') {
  renderCertKanban(filter);
  if (typeof refreshDashboardStats === 'function') refreshDashboardStats();
}

// ═══════════════════════════════════════
// REAL-TIME AUDIT LOG AUTO-REFRESH
// ═══════════════════════════════════════
let auditRefreshTimer = null;

function startAuditAutoRefresh() {
  if (auditRefreshTimer !== null) return;

  auditRefreshTimer = setInterval(() => {
    if (document.getElementById('screen-audit')?.classList.contains('active')) {
      void reloadAuditLog();
    }
  }, 15000);
}

// ═══════════════════════════════════════
// ENHANCED launchApp
// ═══════════════════════════════════════
const _origLaunch = launchApp;
function launchApp(name = 'Staff', role = 'Staff') {
  currentUserName = name || 'Staff';
  currentUserRole = role || 'Staff';
  const dbUser = USERS.find(u => u.name === currentUserName || u.role === currentUserRole);
  const roleAccessMap = {
    'admin': 'Full Access',
    'staff': 'Staff Access',
    'Super Administrator': 'Full',
    'Barangay Captain': 'Full',
    'Barangay Secretary': 'Full',
    'Records Officer': 'Records & Certs',
    'Barangay Clerk': 'Certificates Only',
    'Tanod Captain': 'Incidents Only',
    'Data Encoder': 'Records Only',
  };
  currentUserAccess = dbUser?.access || roleAccessMap[currentUserRole] || 'View Only';
  document.getElementById('login-screen').style.display = 'none';
  const app = document.getElementById('app');
  app.classList.add('visible');
  applyAccessControl(currentUserAccess);
  const userNameEl = document.getElementById('sidebar-user-name');
  const userRoleEl = document.getElementById('sidebar-user-role');
  const userAvatarEl = document.getElementById('sidebar-user-avatar');
  const topAvatarEl = document.querySelector('.topbar-avatar');
  if (userNameEl) userNameEl.textContent = currentUserName;
  if (userRoleEl) userRoleEl.textContent = currentUserRole;
  if (userAvatarEl) userAvatarEl.textContent = initials(currentUserName);
  if (topAvatarEl) topAvatarEl.textContent = initials(currentUserName);
  refreshPopulationStats();
  runCounters();
  buildCharts();
  renderResidentsTable();
  renderCertKanban();
  renderCertTypesList();
  renderRFIDTags();
  renderCabinet();
  renderAuditLog();
  if (currentUserRole === 'admin') void reloadUsers();
  void refreshDashboardStats();
  void loadIncidents();
  renderDemographics();
  renderDashPurokBreakdown();
  renderNotifications();
  updateNotifBadge();
  startClock();
  startAuditAutoRefresh();
  populateEligResidentDropdown(null);
  addLiveAuditEntry('🔐', 'auth', 'Login - Credentials', `System login - ${currentUserRole} access granted`, currentUserName);
  showToast(`Welcome back, ${currentUserName}!`, 'green');
}

// ═══════════════════════════════════════
// ENGLISH TRANSLATIONS for JS-generated text
// ═══════════════════════════════════════
function renderSeniorList() {
  const container = document.getElementById('senior-citizens-list');
  if (!container) return;
  const seniors = Array.isArray(DEMOGRAPHIC_SUMMARY?.senior_residents)
    ? DEMOGRAPHIC_SUMMARY.senior_residents.map(resident => ({
        id: resident.resident_number,
        name: resident.full_name,
        dob: resident.date_of_birth,
        purok: resident.purok,
        status: resident.status === 'active' ? 'Active' : 'Inactive',
        age: resident.age
      }))
    : RESIDENTS.filter(r => isSenior(r.dob));
  const countEl = document.getElementById('demo-senior-count');
  if (countEl) countEl.textContent = Number(DEMOGRAPHIC_SUMMARY?.seniors ?? seniors.length).toLocaleString();
  if (seniors.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:20px;">No registered senior citizens.</div>';
    return;
  }
  container.innerHTML = `
    <table class="tbl">
      <thead><tr><th>Resident ID</th><th>Full Name</th><th>Age</th><th>Date of Birth</th><th>Zone / Purok</th><th>Status</th><th>Classification</th></tr></thead>
      <tbody>${seniors.map(r => {
        const age = Number(r.age ?? calcAge(r.dob));
        return `<tr>
          <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--blue-400);">${escapeText(r.id)}</span></td>
          <td><strong style="color:var(--text-primary);">${escapeText(r.name)}</strong></td>
          <td><span style="font-weight:700;color:var(--senior-color);font-size:14px;">${age}</span></td>
          <td style="font-size:11.5px;">${r.dob}</td>
          <td>${escapeText(r.purok)}</td>
          <td><span class="badge ${r.status === 'Active' ? 'badge-green' : 'badge-red'}">${escapeText(r.status)}</span></td>
          <td><span class="badge badge-senior">👴 Senior Citizen</span></td>
        </tr>`;
      }).join('')}</tbody>
    </table>`;
}

// Override openViewResident for English
function legacyEnglishOpenViewResident(id) {
  currentViewResidentId = id;
  const r = RESIDENTS.find(x => x.id === id);
  if (!r) return;
  const rs = RESIDENT_STATUS[id];
  const age = calcAge(r.dob);
  const senior = age >= 60;
  const blotterHtml = rs?.blotter
    ? `<div style="grid-column:1/-1;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);padding:10px 12px;font-size:12px;"><strong style="color:#FCA5A5;">⚠️ Blotter Record:</strong><div style="color:var(--text-muted);margin-top:4px;">${rs.blotterDetails.join('<br>')}</div></div>`
    : `<div style="grid-column:1/-1;background:var(--green-dim);border:1px solid var(--border-green);border-radius:var(--radius-sm);padding:8px 12px;font-size:12px;color:var(--green-500);">✅ No blotter record — Good Standing</div>`;
  const sg = [];
  if (senior) sg.push('<span class="badge badge-senior">👴 Senior Citizen</span>');
  (r.specialGroups || []).forEach(g => sg.push(`<span class="badge badge-blue">${g}</span>`));
  document.getElementById('view-resident-content').innerHTML = `
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div class="form-group"><div class="form-label">Resident ID</div><div style="font-family:var(--font-mono);color:var(--blue-400);">${r.id}</div></div>
      <div class="form-group"><div class="form-label">Status</div>
        <span class="badge ${r.status === 'Active' ? 'badge-green' : 'badge-red'}">${r.status}</span>
        ${sg.join(' ')}
      </div>
      <div class="form-group"><div class="form-label">Full Name</div><div style="color:var(--text-primary);font-weight:600;">${r.name}</div></div>
      <div class="form-group"><div class="form-label">Date of Birth</div><div>${r.dob}</div></div>
      <div class="form-group"><div class="form-label">Age</div><div style="font-size:18px;font-weight:800;color:${senior ? 'var(--senior-color)' : 'var(--text-primary)'};">${age} years old</div></div>
      <div class="form-group"><div class="form-label">Gender</div><div>${r.gender}</div></div>
      <div class="form-group"><div class="form-label">Civil Status</div><div>${r.civil}</div></div>
      <div class="form-group"><div class="form-label">Zone / Purok</div><div>${r.purok}</div></div>
      <div class="form-group"><div class="form-label">Household No.</div><div>${r.household || 'Not assigned'}</div></div>
      <div class="form-group"><div class="form-label">Special Groups</div><div>${sg.length ? sg.join(' ') : 'None'}</div></div>
      <div class="form-group"><div class="form-label">Contact</div><div>${r.contact}</div></div>
      <div class="form-group"><div class="form-label">Residency Type</div><div>${r.type}</div></div>
      ${blotterHtml}
    </div>`;
  openModal('modal-view-resident');
}

// Override checkEligibility for English messages
function checkEligibility(residentId, certId) {
  const resident = RESIDENTS.find(r => r.id === residentId);
  const status = RESIDENT_STATUS[residentId];
  const rule = ELIGIBILITY_RULES[certId];
  if (!resident || !status || !rule) return { eligible: false, reasons: ['Resident or document not found.'] };
  const reasons = [];
  let eligible = true;
  if (rule.requiresActive && resident.status !== 'Active') { eligible = false; reasons.push('❌ Resident is not active.'); }
  if (rule.needsGoodStanding) {
    // Check blotter flag on resident record
    if (status.blotter) {
      eligible = false;
      const blotterList = status.blotterDetails?.length ? status.blotterDetails.map(b => `• ${b}`).join('<br>') : '(walang detalye)';
      reasons.push(`❌ Naka-blotter ang residente:<br>${blotterList}`);
    }
    // Check good_standing flag
    if (!status.goodStanding) {
      eligible = false;
      reasons.push(`❌ Hindi nasa mabuting kalagayan (good standing) ang residente.`);
    }
    // Check incident reports — resident named as complainee (Ine-reklamo)
    const nameLower = resident.name.trim().toLowerCase();
    const blotterIncidents = (typeof INCIDENTS !== 'undefined' ? INCIDENTS : []).filter(inc =>
      inc.complainee && inc.complainee.trim().toLowerCase() === nameLower
    );
    if (blotterIncidents.length > 0) {
      eligible = false;
      const list = blotterIncidents.map(i => `• ${i.type} — ${i.date} (${i.id})`).join('<br>');
      reasons.push(`❌ Nakasangkot sa ${blotterIncidents.length} incident report bilang ine-reklamo:<br>${list}`);
    }
    if (eligible) reasons.push('✅ Nasa mabuting kalagayan. Walang blotter records.');
  }
  if (rule.oneTimeOnly) {
    const prev = REQUEST_RECORDS.filter(r => r.residentId === residentId && r.certId === certId && r.status === 'Completed');
    if (prev.length > 0) { eligible = false; reasons.push(`❌ One-time only. Previously obtained on: ${prev[0].date}.`); }
  }
  if (resident.status === 'Active') reasons.push('✅ Active resident status confirmed.');
  return { eligible, reasons, resident, rule, status };
}

function legacyEnglishRunEligibilityCheck() {
  const residentId = document.getElementById('elig-resident-select').value;
  const certId = document.getElementById('elig-doc-select').value;
  if (!residentId || !certId) { showToast('Please select a resident and document type.', 'red'); return; }
  const result = checkEligibility(residentId, certId);
  const resultEl = document.getElementById('elig-result');
  const proceedBtn = document.getElementById('elig-proceed-btn');
  const color = result.eligible ? 'var(--green-500)' : '#EF4444';
  const bg = result.eligible ? 'var(--green-dim)' : 'rgba(239,68,68,0.06)';
  const border = result.eligible ? 'var(--border-green)' : 'rgba(239,68,68,0.3)';
  const headline = result.eligible ? '✅ ELIGIBLE — Document can be issued' : '🚫 NOT ELIGIBLE — Request is blocked';
  resultEl.style.display = 'block';
  resultEl.innerHTML = `
    <div style="background:${bg};border:1px solid ${border};border-radius:var(--radius);padding:14px;margin-bottom:12px;">
      <div style="font-weight:700;color:${color};font-size:13px;margin-bottom:8px;">${headline}</div>
      <div style="font-size:12.5px;color:var(--text-secondary);"><strong style="color:var(--text-primary);">Resident:</strong> ${result.resident?.name} (${result.resident?.id}) — ${calcAge(result.resident?.dob)} yrs old</div>
      <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:10px;"><strong style="color:var(--text-primary);">Document:</strong> ${result.rule?.label}</div>
      <div style="display:flex;flex-direction:column;gap:6px;">${result.reasons.map(r => `<div style="font-size:12px;color:var(--text-secondary);line-height:1.5;">${r}</div>`).join('')}</div>
    </div>`;
  proceedBtn.style.display = result.eligible ? 'block' : 'none';
  currentEligResidentId = residentId;
  addLiveAuditEntry('🔍', 'cert', 'Eligibility Check', `${result.resident?.name} — ${result.rule?.label} — ${result.eligible ? 'ELIGIBLE' : 'NOT ELIGIBLE'}`, currentUserName || 'Staff');
}

// Real-time audit log integration for key actions
const _origSaveResident = saveResident;
function legacyEnglishSaveResident() {
  const name = document.getElementById('res-name')?.value?.trim();
  const lastName = document.getElementById('res-lastname')?.value?.trim();
  const dob = document.getElementById('res-dob')?.value;
  const editId = document.getElementById('res-edit-id')?.value;
  if (!name || !dob) { showToast('Please fill in required fields.', 'red'); return; }
  const fullName = (name + (lastName ? ' ' + lastName : '')).trim();
  const contact = document.getElementById('res-contact')?.value || '';
  const gender = document.getElementById('res-gender')?.value || 'Male';
  const civil = document.getElementById('res-civil')?.value || 'Single';
  const purok = document.getElementById('res-purok')?.value || 'Purok 1 - Sampaguita';
  const type = document.getElementById('res-type')?.value || 'Homeowner';
  const address = document.getElementById('res-address')?.value || '';
  const specialGroups = getCheckedSpecialGroups();

  if (editId) {
    const resident = RESIDENTS.find(x => x.id === editId);
    if (!resident) { showToast('Resident not found.', 'red'); return; }
    const oldPurok = resident.purok;
    Object.assign(resident, { name: fullName, dob, contact, gender, civil, purok, type, address, specialGroups });
    if (oldPurok !== purok) {
      const oldZone = PUROK_DATA.find(p => p.key === oldPurok);
      const newZone = PUROK_DATA.find(p => p.key === purok);
      if (oldZone && oldZone.total > 0) oldZone.total -= 1;
      if (newZone) newZone.total += 1;
    }
    addLiveAuditEntry('🧑', 'record', 'Resident Record Updated', `${fullName} - ${editId}`, currentUserName || 'Staff');
    showToast(`Resident updated: ${fullName}`, 'green');
  } else {
    const newId = 'ANB-' + String(RESIDENTS.length + 1).padStart(4, '0');
    const household = 'HH-' + String(1200 + RESIDENTS.length + 1).padStart(4, '0');
    RESIDENTS.push({ id: newId, name: fullName, purok, dob, gender, civil, contact, status: 'Active', household, type, address, specialGroups });
    RESIDENT_STATUS[newId] = { blotter: false, blotterDetails: [], goodStanding: true, notes: '' };
    const zone = PUROK_DATA.find(p => p.key === purok);
    if (zone) zone.total += 1;
    addLiveAuditEntry('🧑', 'record', 'New Resident Registered', `${newId} - ${fullName}`, currentUserName || 'Staff');
    showToast(`Resident registered: ${fullName} (${newId})`, 'green');
  }
  closeModal('modal-resident');
  refreshPopulationStats();
  renderResidentsTable();
  renderDemographics();
  renderDashPurokBreakdown();
  populateEligResidentDropdown(null);
}

async function loadIncidents(page = 1) {
  const tbody = document.getElementById('incidents-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="9" class="resident-table-message">Loading incident reports...</td></tr>';
  const query = new URLSearchParams({ page: String(page), per_page: '15' });
  const search = document.getElementById('incident-search')?.value.trim();
  const status = document.getElementById('incident-status-filter')?.value;
  const severity = document.getElementById('incident-severity-filter')?.value;
  if (search) query.set('search', search);
  if (status) query.set('status', status);
  if (severity) query.set('severity', severity);

  try {
    const response = await fetch(`/admin/incidents?${query}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Hindi ma-load ang incident reports.');
    INCIDENTS.splice(0, INCIDENTS.length, ...(payload.data || []));
    incidentCurrentPage = Number(payload.current_page || 1);
    incidentLastPage = Number(payload.last_page || 1);
    renderIncidents(payload.total || 0);
    updateIncidentSummary(payload.summary || {});
  } catch (error) {
    tbody.innerHTML = `<tr><td colspan="9" class="resident-table-message resident-table-error">${escapeText(error.message)}</td></tr>`;
  }
}

function renderIncidents(total = 0) {
  const tbody = document.getElementById('incidents-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (INCIDENTS.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" class="resident-table-message">Walang nakitang incident report.</td></tr>';
  }

  INCIDENTS.forEach(incident => {
    const severityClass = incident.severity === 'high' ? 'badge-red' : incident.severity === 'medium' ? 'badge-amber' : 'badge-gray';
    const statusClass = incident.status === 'resolved' ? 'badge-green' : incident.status === 'dismissed' ? 'badge-gray' : 'badge-amber';
    const archiveButton = window.AUTHENTICATED_USER?.role === 'admin'
      ? `<button class="btn btn-xs btn-danger" onclick="archiveIncident(${incident.id})">Archive</button>`
      : '';
    const row = document.createElement('tr');
    row.innerHTML = `
      <td><span class="resident-number">${escapeText(incident.incident_number)}</span></td>
      <td><strong>${escapeText(incident.incident_type)}</strong></td>
      <td>${escapeText(incident.location)}</td>
      <td>${escapeText(incident.occurred_at_display)}</td>
      <td>${escapeText(incident.complainant_name)}</td>
      <td>${incident.respondent_name ? escapeText(incident.respondent_name) : '—'}</td>
      <td><span class="badge ${severityClass}">${escapeText(incident.severity_label)}</span></td>
      <td><span class="badge ${statusClass}">${escapeText(incident.status_label)}</span></td>
      <td class="resident-actions">
        <button class="btn btn-xs" onclick="openViewIncident(${incident.id})">View</button>
        <button class="btn btn-xs btn-primary" onclick="openEditIncident(${incident.id})">Edit</button>
        ${archiveButton}
      </td>`;
    tbody.appendChild(row);
  });

  const pagination = document.getElementById('incident-pagination');
  if (pagination) {
    pagination.innerHTML = `
      <span class="resident-page-summary">${Number(total).toLocaleString()} report${Number(total) === 1 ? '' : 's'}</span>
      <button class="btn btn-xs" ${incidentCurrentPage <= 1 ? 'disabled' : ''} onclick="loadIncidents(${incidentCurrentPage - 1})">Previous</button>
      <span>Page ${incidentCurrentPage} of ${incidentLastPage}</span>
      <button class="btn btn-xs" ${incidentCurrentPage >= incidentLastPage ? 'disabled' : ''} onclick="loadIncidents(${incidentCurrentPage + 1})">Next</button>`;
  }
}

function updateIncidentSummary(summary) {
  const set = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = Number(value || 0).toLocaleString(); };
  set('inc-stat-pending', summary.pending);
  set('inc-stat-resolved', summary.resolved_this_month);
  set('inc-stat-high', summary.high);
}

function filterIncidents() {
  clearTimeout(incidentSearchTimer);
  incidentSearchTimer = setTimeout(() => loadIncidents(1), 250);
}

function openViewIncident(id) {
  const incident = INCIDENTS.find(item => item.id === id);
  if (!incident) return;
  const set = (elementId, value) => { const element = document.getElementById(elementId); if (element) element.textContent = value || '—'; };
  set('view-inc-id', incident.incident_number);
  set('view-inc-type', incident.incident_type);
  set('view-inc-date', incident.occurred_at_display);
  set('view-inc-loc', incident.location);
  set('view-inc-reported', incident.complainant_name);
  set('view-inc-complainee', incident.respondent_name);
  set('view-inc-description', incident.details);
  set('view-inc-severity', incident.severity_label);
  set('view-inc-status', incident.status_label);
  const editButton = document.getElementById('view-inc-edit-btn');
  if (editButton) editButton.onclick = () => { closeModal('modal-view-incident'); openEditIncident(id); };
  const attachmentWrapper = document.getElementById('view-inc-attachments-wrap');
  const attachmentList = document.getElementById('view-inc-attachments');
  const attachments = Array.isArray(incident.attachments) ? incident.attachments : [];
  if (attachmentWrapper && attachmentList) {
    attachmentList.innerHTML = attachments.map(attachment => `<a href="${escapeText(attachment.url)}" target="_blank" rel="noopener" class="btn btn-xs">${escapeText(attachment.name)}</a>`).join('');
    attachmentWrapper.style.display = attachments.length ? 'block' : 'none';
  }
  openModal('modal-view-incident');
}

function openEditIncident(id) {
  const incident = INCIDENTS.find(item => item.id === id);
  if (!incident) return;
  const set = (elementId, value) => { const element = document.getElementById(elementId); if (element) element.value = value || ''; };
  set('inc-edit-id', incident.id);
  set('inc-type', incident.incident_type);
  set('inc-date', incident.occurred_date);
  set('inc-time', incident.occurred_time);
  set('inc-location', incident.location);
  set('inc-reported', incident.complainant_name === 'Anonymous' ? '' : incident.complainant_name);
  set('inc-complainee', incident.respondent_name);
  set('inc-severity', incident.severity);
  set('inc-details', incident.details);
  set('inc-status', incident.status);
  set('inc-resolution-notes', incident.resolution_notes);
  document.getElementById('inc-status-group').style.display = 'block';
  document.getElementById('inc-attachments').value = '';
  document.getElementById('inc-attachments-preview').innerHTML = '';
  document.getElementById('inc-modal-title').textContent = 'Edit Incident Report';
  document.querySelector('#modal-incident .btn-danger').textContent = 'Save Changes';
  toggleIncidentResolution();
  openModal('modal-incident');
}

function openAddIncident() {
  ['inc-type','inc-location','inc-reported','inc-complainee','inc-time','inc-details','inc-edit-id','inc-resolution-notes'].forEach(id => {
    const element = document.getElementById(id);
    if (element) element.value = '';
  });
  document.getElementById('inc-date').value = new Date().toISOString().slice(0, 10);
  document.getElementById('inc-severity').value = 'medium';
  document.getElementById('inc-status').value = 'pending';
  document.getElementById('inc-status-group').style.display = 'none';
  document.getElementById('inc-resolution-group').style.display = 'none';
  document.getElementById('inc-attachments').value = '';
  document.getElementById('inc-attachments-preview').innerHTML = '';
  document.getElementById('inc-modal-title').textContent = 'File Incident Report';
  document.querySelector('#modal-incident .btn-danger').textContent = 'File Report';
  openModal('modal-incident');
}

function toggleIncidentResolution() {
  const group = document.getElementById('inc-resolution-group');
  if (group) group.style.display = document.getElementById('inc-status')?.value === 'resolved' ? 'block' : 'none';
}

async function saveIncident() {
  const incidentId = document.getElementById('inc-edit-id')?.value;
  const formData = new FormData();
  formData.append('incident_type', document.getElementById('inc-type')?.value || '');
  formData.append('occurred_date', document.getElementById('inc-date')?.value || '');
  formData.append('occurred_time', document.getElementById('inc-time')?.value || '');
  formData.append('location', document.getElementById('inc-location')?.value.trim() || '');
  formData.append('complainant_name', document.getElementById('inc-reported')?.value.trim() || '');
  formData.append('respondent_name', document.getElementById('inc-complainee')?.value.trim() || '');
  formData.append('severity', document.getElementById('inc-severity')?.value.toLowerCase() || 'medium');
  formData.append('details', document.getElementById('inc-details')?.value.trim() || '');
  if (incidentId) {
    formData.append('_method', 'PATCH');
    formData.append('status', document.getElementById('inc-status')?.value || 'pending');
    formData.append('resolution_notes', document.getElementById('inc-resolution-notes')?.value.trim() || '');
  }
  [...(document.getElementById('inc-attachments')?.files || [])].forEach(file => formData.append('attachments[]', file));
  const button = document.querySelector('#modal-incident .btn-danger');
  if (button) button.disabled = true;

  try {
    const response = await fetch(incidentId ? `/admin/incidents/${incidentId}` : '/admin/incidents', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', ...csrfRequestHeaders() },
      body: formData
    });
    const result = await response.json();
    if (!response.ok) {
      const message = result?.errors ? Object.values(result.errors).flat()[0] : result?.message;
      throw new Error(message || 'Hindi ma-save ang incident report.');
    }
    closeModal('modal-incident');
    showToast(result.message, 'green');
    await loadIncidents(incidentId ? incidentCurrentPage : 1);
    await refreshDashboardStats();
  } catch (error) {
    showToast(error.message || 'Hindi ma-save ang incident report.', 'red');
  } finally {
    if (button) button.disabled = false;
  }
}

async function archiveIncident(id) {
  if (!confirm('I-archive ang incident report na ito?')) return;
  try {
    const response = await fetch(`/admin/incidents/${id}`, { method: 'DELETE', credentials: 'same-origin', headers: { 'Accept': 'application/json', ...csrfRequestHeaders() } });
    const result = await response.json();
    if (!response.ok) throw new Error(result?.message || 'Hindi ma-archive ang incident report.');
    showToast(result.message, 'green');
    await loadIncidents(incidentCurrentPage);
    await refreshDashboardStats();
  } catch (error) {
    showToast(error.message || 'Hindi ma-archive ang incident report.', 'red');
  }
}

async function loadRequestRecords(page = 1) {
  const container = document.getElementById('rr-resident-list');
  if (!container) return;
  container.innerHTML = '<div class="resident-table-message">Loading request records...</div>';
  const query = new URLSearchParams({ page: String(page), per_page: '15' });
  if (rrCurrentFilter.trim()) query.set('search', rrCurrentFilter.trim());
  if (rrCurrentStatusFilter) query.set('eligibility', rrCurrentStatusFilter);

  try {
    const response = await fetch(`/admin/request-records?${query}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Hindi ma-load ang request records.');
    requestRecordResidents = payload.data || [];
    rrCurrentPage = Number(payload.current_page || 1);
    rrLastPage = Number(payload.last_page || 1);
    renderRequestRecords(Number(payload.total || 0));
    const summary = payload.summary || {};
    const set = (id, value) => { const element = document.getElementById(id); if (element) element.textContent = Number(value || 0).toLocaleString(); };
    set('rr-total', summary.total_requests);
    set('rr-completed', summary.completed);
    set('rr-blocked', summary.blocked);
    set('rr-review', summary.needs_review);
  } catch (error) {
    container.innerHTML = `<div class="resident-table-message resident-table-error">${escapeText(error.message)}</div>`;
  }
}

function renderRequestRecords(total = 0) {
  const container = document.getElementById('rr-resident-list');
  if (!container) return;

  if (requestRecordResidents.length === 0) {
    container.innerHTML = '<div class="resident-table-message">Walang nakitang resident request record.</div>';
  } else {
    container.innerHTML = requestRecordResidents.map(resident => {
      const eligible = resident.status === 'active' && Boolean(resident.is_in_good_standing);
      const requests = Array.isArray(resident.document_requests) ? resident.document_requests : [];
      const requestRows = requests.length === 0
        ? '<tr><td colspan="5" class="resident-table-message">Wala pang linked request.</td></tr>'
        : requests.map(request => {
            const status = String(request.status || 'pending');
            const statusClass = status === 'released' ? 'badge-green' : status === 'rejected' ? 'badge-red' : status === 'ready_for_release' ? 'badge-blue' : 'badge-amber';
            return `<tr>
              <td><span class="resident-number">${escapeText(request.reference_code)}</span></td>
              <td>${escapeText(request.document_type)}</td>
              <td>${escapeText(String(request.created_at || '').slice(0, 10))}</td>
              <td>${escapeText(request.source || 'online')}</td>
              <td><span class="badge ${statusClass}">${escapeText(status.replaceAll('_', ' '))}</span></td>
            </tr>`;
          }).join('');

      return `<div class="card rr-resident-card" style="margin-bottom:0;">
        <div class="rr-resident-header" onclick="toggleRRCard(this)">
          <div style="display:flex;align-items:center;gap:10px;">
            <div class="rr-avatar">${escapeText(String(resident.full_name || '?').charAt(0))}</div>
            <div>
              <div style="font-weight:700;color:var(--text-primary);font-size:13.5px;">${escapeText(resident.full_name)}</div>
              <div style="font-size:11px;color:var(--text-muted);">${escapeText(resident.resident_number)} &nbsp;•&nbsp; ${escapeText(resident.purok)} &nbsp;•&nbsp; ${Number(resident.age)} yrs old</div>
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;">
            <span class="badge ${eligible ? 'badge-green' : 'badge-red'}">${eligible ? 'Good Standing' : 'Needs Review'}</span>
            <span class="badge badge-gray">${requests.length} req</span>
            <button class="btn btn-xs btn-primary" onclick="event.stopPropagation();openEligibilityForRequestResident(${resident.id})">Check</button>
            <span class="rr-chevron">▼</span>
          </div>
        </div>
        <div class="rr-requests-panel" style="display:none;">
          <div class="table-scroll"><table class="tbl"><thead><tr><th>Code</th><th>Document</th><th>Date</th><th>Source</th><th>Status</th></tr></thead><tbody>${requestRows}</tbody></table></div>
        </div>
      </div>`;
    }).join('');
  }

  const pagination = document.getElementById('rr-pagination');
  if (pagination) {
    pagination.innerHTML = `
      <span class="resident-page-summary">${Number(total).toLocaleString()} resident${Number(total) === 1 ? '' : 's'}</span>
      <button class="btn btn-xs" ${rrCurrentPage <= 1 ? 'disabled' : ''} onclick="loadRequestRecords(${rrCurrentPage - 1})">Previous</button>
      <span>Page ${rrCurrentPage} of ${rrLastPage}</span>
      <button class="btn btn-xs" ${rrCurrentPage >= rrLastPage ? 'disabled' : ''} onclick="loadRequestRecords(${rrCurrentPage + 1})">Next</button>`;
  }
}

function openEligibilityForRequestResident(databaseId) {
  const resident = requestRecordResidents.find(item => Number(item.id) === Number(databaseId));
  if (!resident) return;
  manualResidentsById.set(String(resident.id), resident);
  const mapped = residentFromApi(resident);
  const existingIndex = RESIDENTS.findIndex(item => item.id === mapped.id);
  if (existingIndex >= 0) RESIDENTS[existingIndex] = mapped;
  else RESIDENTS.push(mapped);
  openEligibilityForResident(mapped.id);
}

function filterRequestRecords() {
  rrCurrentFilter = document.getElementById('rr-search')?.value || '';
  clearTimeout(rrSearchTimer);
  rrSearchTimer = setTimeout(() => loadRequestRecords(1), 250);
}

function filterRRStatus(status, element) {
  document.querySelectorAll('.rr-filter-btn').forEach(button => button.classList.remove('active'));
  element?.classList.add('active');
  rrCurrentStatusFilter = status;
  void loadRequestRecords(1);
}

async function refreshDocumentRequestsLive() {
  try {
    const response = await fetch('/admin/document-requests-live', {
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) return;

    const requests = await response.json();
    window.LARAVEL_DOCUMENT_REQUESTS = requests;

    renderCertKanban(document.getElementById('cert-search')?.value || '');
  } catch (error) {
    console.error('Live request refresh failed:', error);
  }
}

let documentRequestLiveTimer = null;

function startDocumentRequestLiveRefresh() {
  if (documentRequestLiveTimer) {
    clearInterval(documentRequestLiveTimer);
  }

  documentRequestLiveTimer = setInterval(() => {
    if (!document.hidden) {
      refreshDocumentRequestsLive();
    }
  }, 3000);
}

startDocumentRequestLiveRefresh();
refreshIssuedCertificates();

document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    refreshDocumentRequestsLive();
  }
});

async function confirmPrintRelease() {
  const requestId = document.getElementById('print-document-request-id')?.value;

  if (!requestId) {
    showToast('Missing document request ID.', 'red');
    return;
  }

  const submitButton = document.getElementById('print-release-submit');
  const printWindow = window.open('about:blank', '_blank');

  if (submitButton) {
    submitButton.disabled = true;
    submitButton.dataset.originalText = submitButton.innerHTML;
    submitButton.innerHTML = '⏳ Issuing...';
  }

  try {
    const response = await fetch(
      `/admin/document-requests/${requestId}/issue`,
      {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          ...csrfRequestHeaders()
        }
      }
    );

    const data = await response.json();

    if (!response.ok) {
      throw new Error(
        data?.message || 'Failed to issue certificate.'
      );
    }

    closeModal('modal-print-release');

    showToast(
      '✅ Certificate issued successfully.',
      'green'
    );

    await refreshDocumentRequestsLive();
    await refreshIssuedCertificates();

    if (data.print_url) {
      if (printWindow) {
        printWindow.location.href = data.print_url;
      } else {
        window.location.href = data.print_url;
      }
    } else if (printWindow) {
      printWindow.close();
    }

  } catch (error) {
    if (printWindow) printWindow.close();

    console.error(
      'Issue certificate error:',
      error
    );

    showToast(
      error.message || 'Failed to issue certificate.',
      'red'
    );
  } finally {
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.innerHTML = submitButton.dataset.originalText || '🖨️ Issue & Print';
    }
  }
}

// Database-backed Resident Records module.
let residentCurrentPage = 1;
let residentLastPage = 1;
let residentStatusFilter = '';
let residentSearchTimer = null;
const manualResidentsById = new Map();

function residentFromApi(resident) {
  return {
    databaseId: resident.id,
    id: resident.resident_number,
    name: resident.full_name,
    firstName: resident.first_name,
    middleName: resident.middle_name || '',
    lastName: resident.last_name,
    suffix: resident.suffix || '',
    purok: resident.purok,
    dob: String(resident.date_of_birth || '').slice(0, 10),
    gender: resident.gender,
    civil: resident.civil_status,
    contact: resident.contact_number || '',
    status: resident.status === 'active' ? 'Active' : 'Inactive',
    type: resident.residency_type,
    address: resident.address,
    specialGroups: resident.special_groups || [],
    goodStanding: Boolean(resident.is_in_good_standing),
    archived: Boolean(resident.deleted_at),
    documentRequestsCount: resident.document_requests_count || 0,
    issuedCertificatesCount: resident.issued_certificates_count || 0
  };
}

async function loadResidents(page = 1) {
  const tbody = document.getElementById('records-tbody');
  if (tbody) tbody.innerHTML = '<tr><td colspan="8" class="resident-table-message">Loading resident records...</td></tr>';

  const query = new URLSearchParams({ page: String(page), per_page: '15' });
  const search = document.getElementById('residents-search')?.value.trim();
  if (search) query.set('search', search);
  if (residentStatusFilter) query.set('status', residentStatusFilter.toLowerCase());

  try {
    const response = await fetch(`/admin/residents?${query}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Unable to load resident records.');

    RESIDENTS.splice(0, RESIDENTS.length, ...payload.data.map(residentFromApi));
    RESIDENT_TOTAL = payload.total;
    residentCurrentPage = payload.current_page;
    residentLastPage = payload.last_page;
    renderResidentsTable();
    renderResidentPagination(payload.total);
    refreshPopulationStats();
    renderDemographics();
    renderDashPurokBreakdown();
    populateEligResidentDropdown(null);
  } catch (error) {
    if (tbody) tbody.innerHTML = `<tr><td colspan="8" class="resident-table-message resident-table-error">${escapeText(error.message)}</td></tr>`;
  }
}

function renderResidentsTable() {
  const tbody = document.getElementById('records-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (RESIDENTS.length === 0) {
    tbody.innerHTML = '<tr><td colspan="8" class="resident-table-message">No resident records found.</td></tr>';
    return;
  }

  RESIDENTS.forEach(resident => {
    const row = document.createElement('tr');
    const archivedBadge = resident.archived ? '<span class="badge badge-red">Archived</span>' : `<span class="badge ${resident.status === 'Active' ? 'badge-green' : 'badge-red'}">${escapeText(resident.status)}</span>`;
    const actions = resident.archived
      ? `<button class="btn btn-xs btn-green" onclick="restoreResident(${resident.databaseId})">Restore</button>`
      : `<button class="btn btn-xs btn-primary" onclick="openViewResident('${resident.id}')">View</button>
         <button class="btn btn-xs" onclick="openEditResident('${resident.id}')">Edit</button>
         <button class="btn btn-xs btn-danger" onclick="deleteResident('${resident.id}')">Archive</button>`;

    row.innerHTML = `
      <td><span class="resident-number">${escapeText(resident.id)}</span></td>
      <td><strong>${escapeText(resident.name)}</strong>${calcAge(resident.dob) >= 60 ? '<span class="badge badge-senior">Senior</span>' : ''}</td>
      <td>${calcAge(resident.dob)}</td>
      <td>${escapeText(resident.purok)}</td>
      <td>${escapeText(resident.gender)}</td>
      <td>${escapeText(resident.civil)}</td>
      <td>${archivedBadge}</td>
      <td><div class="resident-actions">${actions}</div></td>`;
    tbody.appendChild(row);
  });
}

function renderResidentPagination(total) {
  const container = document.getElementById('resident-pagination');
  if (!container) return;
  container.innerHTML = `
    <span class="resident-page-summary">${Number(total).toLocaleString()} record${total === 1 ? '' : 's'}</span>
    <button class="btn btn-xs" ${residentCurrentPage <= 1 ? 'disabled' : ''} onclick="loadResidents(${residentCurrentPage - 1})">Previous</button>
    <span>Page ${residentCurrentPage} of ${residentLastPage}</span>
    <button class="btn btn-xs" ${residentCurrentPage >= residentLastPage ? 'disabled' : ''} onclick="loadResidents(${residentCurrentPage + 1})">Next</button>`;
}

function filterResidents() {
  clearTimeout(residentSearchTimer);
  residentSearchTimer = setTimeout(() => loadResidents(1), 250);
}

function filterResidentStatus(value, element) {
  document.querySelectorAll('#screen-records .status-pill').forEach(pill => pill.classList.remove('active'));
  element?.classList.add('active');
  residentStatusFilter = value;
  loadResidents(1);
}

function exportResidents() {
  const query = new URLSearchParams();
  const search = document.getElementById('residents-search')?.value.trim();
  if (search) query.set('search', search);
  if (residentStatusFilter) query.set('status', residentStatusFilter.toLowerCase());
  window.location.href = `/admin/residents-export?${query}`;
}

function openAddResident() {
  syncPurokSelects();
  document.querySelector('#modal-resident-title span').textContent = 'Register New Resident';
  ['res-lastname', 'res-name', 'res-middlename', 'res-suffix', 'res-dob', 'res-contact', 'res-address', 'res-edit-id'].forEach(id => {
    const element = document.getElementById(id);
    if (element) element.value = '';
  });
  document.getElementById('res-status').value = 'active';
  document.getElementById('res-good-standing').checked = true;
  setCheckedSpecialGroups([]);
  openModal('modal-resident');
}

function openEditResident(residentNumber) {
  const resident = RESIDENTS.find(item => item.id === residentNumber);
  if (!resident) return showToast('Resident not found.', 'red');
  syncPurokSelects(resident.purok);
  document.querySelector('#modal-resident-title span').textContent = 'Edit Resident Record';
  const values = {
    'res-lastname': resident.lastName, 'res-name': resident.firstName,
    'res-middlename': resident.middleName, 'res-suffix': resident.suffix,
    'res-dob': resident.dob, 'res-contact': resident.contact,
    'res-address': resident.address, 'res-edit-id': resident.id,
    'res-gender': resident.gender, 'res-civil': resident.civil,
    'res-purok': resident.purok, 'res-type': resident.type,
    'res-status': resident.status.toLowerCase()
  };
  Object.entries(values).forEach(([id, value]) => {
    const element = document.getElementById(id);
    if (element) element.value = value || '';
  });
  document.getElementById('res-good-standing').checked = resident.goodStanding;
  setCheckedSpecialGroups(resident.specialGroups);
  openModal('modal-resident');
}

function residentFormPayload(confirmDuplicate = false) {
  return {
    first_name: document.getElementById('res-name')?.value.trim(),
    middle_name: document.getElementById('res-middlename')?.value.trim() || null,
    last_name: document.getElementById('res-lastname')?.value.trim(),
    suffix: document.getElementById('res-suffix')?.value.trim() || null,
    date_of_birth: document.getElementById('res-dob')?.value,
    gender: document.getElementById('res-gender')?.value,
    civil_status: document.getElementById('res-civil')?.value,
    purok: document.getElementById('res-purok')?.value,
    address: document.getElementById('res-address')?.value.trim(),
    contact_number: document.getElementById('res-contact')?.value.trim() || null,
    residency_type: document.getElementById('res-type')?.value,
    special_groups: getCheckedSpecialGroups(),
    status: document.getElementById('res-status')?.value,
    is_in_good_standing: document.getElementById('res-good-standing')?.checked,
    confirm_duplicate: confirmDuplicate
  };
}

async function saveResident(confirmDuplicate = false) {
  const residentNumber = document.getElementById('res-edit-id')?.value;
  const resident = RESIDENTS.find(item => item.id === residentNumber);
  const url = resident ? `/admin/residents/${resident.databaseId}` : '/admin/residents';
  const method = resident ? 'PATCH' : 'POST';

  try {
    const response = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...csrfRequestHeaders() },
      body: JSON.stringify(residentFormPayload(confirmDuplicate))
    });
    const payload = await response.json();
    if (response.status === 422 && payload?.errors?.duplicate && !confirmDuplicate) {
      if (confirm(`${payload.errors.duplicate[0]}\n\nSave as a separate resident anyway?`)) return saveResident(true);
      return;
    }
    if (!response.ok) {
      const message = payload?.errors ? Object.values(payload.errors).flat()[0] : payload?.message;
      throw new Error(message || 'Unable to save the resident record.');
    }
    closeModal('modal-resident');
    showToast(payload.message, 'green');
    await loadResidents(resident ? residentCurrentPage : 1);
    await loadPuroks();
    await populateManualResidentDropdown();
  } catch (error) {
    showToast(error.message, 'red');
  }
}

async function deleteResident(residentNumber) {
  const resident = RESIDENTS.find(item => item.id === residentNumber);
  if (!resident || !confirm(`Archive ${resident.name}? The record can be restored later.`)) return;
  await changeResidentArchiveState(`/admin/residents/${resident.databaseId}`, 'DELETE');
}

async function restoreResident(databaseId) {
  await changeResidentArchiveState(`/admin/residents/${databaseId}/restore`, 'PATCH');
}

async function changeResidentArchiveState(url, method) {
  try {
    const response = await fetch(url, { method, credentials: 'same-origin', headers: { 'Accept': 'application/json', ...csrfRequestHeaders() } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Unable to update the resident record.');
    showToast(payload.message, 'green');
    await loadResidents(1);
    await loadPuroks();
    await populateManualResidentDropdown();
  } catch (error) {
    showToast(error.message, 'red');
  }
}

function openViewResident(residentNumber) {
  const resident = RESIDENTS.find(item => item.id === residentNumber);
  if (!resident) return;
  currentViewResidentId = resident.id;
  const groups = resident.specialGroups.map(group => `<span class="badge badge-blue">${escapeText(group)}</span>`).join(' ') || 'None';
  document.getElementById('view-resident-content').innerHTML = `
    <div class="resident-detail-grid">
      <div><span>Resident ID</span><strong>${escapeText(resident.id)}</strong></div>
      <div><span>Status</span><strong>${escapeText(resident.status)}</strong></div>
      <div><span>Full Name</span><strong>${escapeText(resident.name)}</strong></div>
      <div><span>Date of Birth</span><strong>${escapeText(resident.dob)}</strong></div>
      <div><span>Age</span><strong>${calcAge(resident.dob)} years old</strong></div>
      <div><span>Gender</span><strong>${escapeText(resident.gender)}</strong></div>
      <div><span>Civil Status</span><strong>${escapeText(resident.civil)}</strong></div>
      <div><span>Purok</span><strong>${escapeText(resident.purok)}</strong></div>
      <div><span>Contact</span><strong>${escapeText(resident.contact || 'None')}</strong></div>
      <div><span>Residency Type</span><strong>${escapeText(resident.type)}</strong></div>
      <div class="resident-detail-wide"><span>Address</span><strong>${escapeText(resident.address)}</strong></div>
      <div class="resident-detail-wide"><span>Special Groups</span><strong>${groups}</strong></div>
      <div><span>Document Requests</span><strong>${resident.documentRequestsCount}</strong></div>
      <div><span>Issued Certificates</span><strong>${resident.issuedCertificatesCount}</strong></div>
      <div class="resident-detail-wide"><span>Eligibility</span><strong>${resident.goodStanding ? 'In good standing' : 'Not in good standing'}</strong></div>
    </div>`;
  openModal('modal-view-resident');
}

async function populateManualResidentDropdown() {
  const select = document.getElementById('manual-resident-id');
  if (!select) return;
  try {
    const response = await fetch('/admin/residents?status=active&per_page=100', { headers: { 'Accept': 'application/json' } });
    if (!response.ok) return;
    const payload = await response.json();
    manualResidentsById.clear();
    select.innerHTML = '<option value="">Manual / legacy issuance</option>';
    payload.data.forEach(resident => {
      manualResidentsById.set(String(resident.id), resident);
      const option = document.createElement('option');
      option.value = resident.id;
      option.textContent = `${resident.resident_number} — ${resident.full_name}`;
      select.appendChild(option);
    });
    populateEligResidentDropdown(currentEligResidentId);
  } catch (error) {
    console.error('Resident selector failed:', error);
  }
}

function selectManualResident() {
  const resident = manualResidentsById.get(document.getElementById('manual-resident-id')?.value);
  if (!resident) return;
  document.getElementById('manual-resident-name').value = resident.full_name;
  document.getElementById('manual-resident-address').value = resident.address;
}

async function openViewResidentRequests(residentNumber) {
  const resident = RESIDENTS.find(item => item.id === residentNumber);
  if (!resident) return;
  try {
    const response = await fetch(`/admin/residents/${resident.databaseId}`, { headers: { 'Accept': 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Unable to load request history.');
    const requests = payload.document_requests || [];
    const rows = requests.length === 0
      ? '<tr><td colspan="5" class="resident-table-message">No linked document requests.</td></tr>'
      : requests.map(request => `<tr>
          <td>${escapeText(request.reference_code)}</td>
          <td>${escapeText(request.document_type)}</td>
          <td>${escapeText(String(request.created_at || '').slice(0, 10))}</td>
          <td><span class="badge badge-blue">${escapeText(request.status)}</span></td>
          <td>${escapeText(request.source || 'online')}</td>
        </tr>`).join('');
    document.getElementById('rr-detail-title').textContent = `${resident.name} — Request History`;
    document.getElementById('rr-detail-content').innerHTML = `<div class="table-scroll"><table class="tbl"><thead><tr><th>Code</th><th>Document</th><th>Date</th><th>Status</th><th>Source</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    closeModal('modal-view-resident');
    openModal('modal-rr-detail');
  } catch (error) {
    showToast(error.message, 'red');
  }
}

async function runEligibilityCheck() {
  const residentNumber = document.getElementById('elig-resident-select')?.value;
  const certificateCode = document.getElementById('elig-doc-select')?.value;
  const resident = RESIDENTS.find(item => item.id === residentNumber);
  const certificateType = CERTIFICATE_TYPES.find(type => type.id === certificateCode)?.label;
  if (!resident || !certificateType) return showToast('Please select a resident and document type.', 'red');

  try {
    const query = new URLSearchParams({ certificate_type: certificateType });
    const response = await fetch(`/admin/residents/${resident.databaseId}/eligibility?${query}`, { headers: { 'Accept': 'application/json' } });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Unable to check eligibility.');
    const result = document.getElementById('elig-result');
    result.style.display = 'block';
    result.innerHTML = `<div class="eligibility-result ${payload.eligible ? 'eligible' : 'ineligible'}">
      <strong>${payload.eligible ? 'ELIGIBLE' : 'NOT ELIGIBLE'}</strong>
      <div>${escapeText(payload.resident.full_name)} — ${escapeText(payload.certificate_type)}</div>
      ${payload.reasons.map(reason => `<div>${escapeText(reason)}</div>`).join('')}
    </div>`;
    document.getElementById('elig-proceed-btn').style.display = payload.eligible ? 'block' : 'none';
    currentEligResidentId = resident.id;
  } catch (error) {
    showToast(error.message, 'red');
  }
}

function elig_proceedRequest() {
  const resident = RESIDENTS.find(item => item.id === currentEligResidentId);
  const certificateCode = document.getElementById('elig-doc-select')?.value;
  const certificateType = CERTIFICATE_TYPES.find(type => type.id === certificateCode)?.label;
  if (!resident || !certificateType) return;
  document.getElementById('manual-resident-id').value = String(resident.databaseId);
  document.getElementById('manual-certificate-type').value = certificateType;
  selectManualResident();
  updateManualCertificateFee();
  closeModal('modal-eligibility-check');
  openModal('modal-cert-issue');
}

let voterCurrentPage = 1;
let voterLastPage = 1;
let voterEligibilityFilter = '';
let voterPurokFilter = '';
let voterSearchTimer = null;
let voterRegistrations = [];
let voterEligibleResidents = [];

async function loadVoterRegistry(page = 1) {
  const tbody = document.getElementById('voter-registry-tbody');
  if (!tbody) return;
  tbody.innerHTML = '<tr><td colspan="7" class="resident-table-message">Loading voter list...</td></tr>';

  const query = new URLSearchParams({ page: String(page), per_page: '15' });
  const search = document.getElementById('voter-search')?.value.trim();
  if (search) query.set('search', search);
  if (voterEligibilityFilter) query.set('eligibility', voterEligibilityFilter);
  if (voterPurokFilter) query.set('purok', voterPurokFilter);

  try {
    const response = await fetch(`/admin/voter-registrations?${query}`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload?.message || 'Unable to load the voter registry.');

    voterRegistrations = payload.data || [];
    voterEligibleResidents = payload.eligible_residents || [];
    voterCurrentPage = Number(payload.current_page || 1);
    voterLastPage = Number(payload.last_page || 1);
    populateVoterPurokFilter(payload.puroks || []);
    renderVoterRegistry(payload.total || 0);
    updateVoterSummary(payload.summary || {});
  } catch (error) {
    tbody.innerHTML = `<tr><td colspan="7" class="resident-table-message resident-table-error">${escapeText(error.message)}</td></tr>`;
  }
}

function renderVoterRegistry(total) {
  const tbody = document.getElementById('voter-registry-tbody');
  if (!tbody) return;

  if (voterRegistrations.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="voter-empty-state"><strong>Walang nakitang botante.</strong><span>Subukang baguhin ang purok, age group, o search.</span></td></tr>';
  } else {
    tbody.innerHTML = voterRegistrations.map(registration => {
      const eligibilityClass = registration.voter_eligibility === 'sk_only'
        ? 'badge-amber'
        : registration.voter_eligibility === 'sk_and_regular' ? 'badge-green' : 'badge-blue';

      return `<tr>
        <td><span class="resident-number">${escapeText(registration.resident_number)}</span></td>
        <td><strong>${escapeText(registration.resident_name)}</strong></td>
        <td><strong>${Number(registration.age)}</strong></td>
        <td>${escapeText(registration.purok)}</td>
        <td><span class="badge ${eligibilityClass}">${escapeText(registration.voter_eligibility_label)}</span></td>
        <td>${escapeText(registration.precinct_number)}</td>
        <td>${escapeText(registration.cluster_number)}</td>
      </tr>`;
    }).join('');
  }

  const pagination = document.getElementById('voter-pagination');
  if (pagination) {
    pagination.innerHTML = `
      <span class="resident-page-summary">${Number(total).toLocaleString()} record${Number(total) === 1 ? '' : 's'}</span>
      <button class="btn btn-xs" ${voterCurrentPage <= 1 ? 'disabled' : ''} onclick="loadVoterRegistry(${voterCurrentPage - 1})">Previous</button>
      <span>Page ${voterCurrentPage} of ${voterLastPage}</span>
      <button class="btn btn-xs" ${voterCurrentPage >= voterLastPage ? 'disabled' : ''} onclick="loadVoterRegistry(${voterCurrentPage + 1})">Next</button>`;
  }
}

function updateVoterSummary(summary) {
  const values = {
    'voter-stat-total': summary.total || 0,
    'voter-stat-sk-only': summary.sk_only || 0,
    'voter-stat-sk-regular': summary.sk_and_regular || 0,
    'voter-stat-regular-only': summary.regular_only || 0
  };
  Object.entries(values).forEach(([id, value]) => {
    const element = document.getElementById(id);
    if (element) element.textContent = Number(value).toLocaleString();
  });
}

function filterVoterRegistry() {
  clearTimeout(voterSearchTimer);
  voterSearchTimer = setTimeout(() => loadVoterRegistry(1), 250);
}

function filterVoterEligibility(eligibility, element) {
  document.querySelectorAll('#screen-voters .status-pill').forEach(pill => pill.classList.remove('active'));
  element?.classList.add('active');
  voterEligibilityFilter = eligibility;
  loadVoterRegistry(1);
}

function filterVoterPurok(purok) {
  voterPurokFilter = purok;
  loadVoterRegistry(1);
}

function populateVoterPurokFilter(puroks) {
  const select = document.getElementById('voter-purok-filter');
  if (!select || select.options.length > 1) return;

  puroks.forEach(purok => {
    const option = document.createElement('option');
    option.value = purok;
    option.textContent = purok;
    select.appendChild(option);
  });
}

function populateVoterResidentSelect() {
  const select = document.getElementById('voter-resident-id');
  if (!select) return;

  select.innerHTML = '<option value="">Pumili ng resident edad 15 pataas...</option>';
  voterEligibleResidents.forEach(resident => {
    const option = document.createElement('option');
    option.value = resident.id;
    option.textContent = `${resident.resident_number} — ${resident.full_name}, ${resident.age} (${resident.purok}) — ${resident.voter_eligibility_label}`;
    select.appendChild(option);
  });
}

async function openVoterRegistration() {
  if (voterEligibleResidents.length === 0) await loadVoterRegistry(1);
  populateVoterResidentSelect();
  document.getElementById('voter-resident-id').value = '';
  document.getElementById('voter-comelec-number').value = '';
  document.getElementById('voter-precinct').value = '';
  document.getElementById('voter-cluster').value = '';
  document.getElementById('voter-registration-date').value = new Date().toISOString().slice(0, 10);
  openModal('modal-voter-registration');
}

async function saveVoterRegistration() {
  const payload = {
    resident_id: Number(document.getElementById('voter-resident-id')?.value),
    comelec_voter_number: document.getElementById('voter-comelec-number')?.value.trim(),
    precinct_number: document.getElementById('voter-precinct')?.value.trim(),
    cluster_number: document.getElementById('voter-cluster')?.value.trim(),
    registration_date: document.getElementById('voter-registration-date')?.value,
    status: 'active'
  };
  const button = document.getElementById('voter-save-button');
  if (button) button.disabled = true;

  try {
    const response = await fetch('/admin/voter-registrations', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...csrfRequestHeaders() },
      body: JSON.stringify(payload)
    });
    const result = await response.json();
    if (!response.ok) {
      const message = result?.errors ? Object.values(result.errors).flat()[0] : result?.message;
      throw new Error(message || 'Hindi ma-save ang voter registration.');
    }

    closeModal('modal-voter-registration');
    showToast('Naidagdag na ang botante.', 'green');
    await loadVoterRegistry(1);
  } catch (error) {
    showToast(error.message || 'Hindi ma-save ang voter registration.', 'red');
  } finally {
    if (button) button.disabled = false;
  }
}

function exportVoterRegistry() {
  const query = new URLSearchParams();
  const search = document.getElementById('voter-search')?.value.trim();
  if (search) query.set('search', search);
  if (voterEligibilityFilter) query.set('eligibility', voterEligibilityFilter);
  if (voterPurokFilter) query.set('purok', voterPurokFilter);
  window.location.href = `/admin/voter-registrations-export?${query}`;
}

function openRejectRequest(requestId, referenceCode, event) {
  event?.stopPropagation();
  document.getElementById('reject-request-id').value = String(requestId);
  document.getElementById('reject-request-code').textContent = `${referenceCode} — the reason will be saved in the request audit record.`;
  document.getElementById('reject-request-reason').value = '';
  openModal('modal-reject-request');
}

async function confirmRejectRequest() {
  const requestId = document.getElementById('reject-request-id')?.value;
  const reason = document.getElementById('reject-request-reason')?.value.trim();
  if (!requestId || !reason || reason.length < 10) {
    showToast('Enter a clear rejection reason of at least 10 characters.', 'red');
    return;
  }

  const button = document.getElementById('reject-request-submit');
  if (button) button.disabled = true;

  try {
    const response = await fetch(`/admin/document-requests/${requestId}/status`, {
      method: 'PATCH',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', ...csrfRequestHeaders() },
      body: JSON.stringify({ status: 'rejected', rejection_reason: reason })
    });
    const payload = await response.json();
    if (!response.ok) {
      const message = payload?.errors ? Object.values(payload.errors).flat()[0] : payload?.message;
      throw new Error(message || 'Unable to reject the request.');
    }
    closeModal('modal-reject-request');
    showToast(payload.message, 'green');
    await refreshDocumentRequestsLive();
  } catch (error) {
    showToast(error.message, 'red');
  } finally {
    if (button) button.disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', async () => {
  const authenticatedUser = window.AUTHENTICATED_USER;
  if (authenticatedUser) {
    await loadPuroks();
    launchApp(authenticatedUser.name, authenticatedUser.role);
    await loadResidents();
    populateManualResidentDropdown();
    loadVoterRegistry();
    try {
      const screen = new URLSearchParams(window.location.search).get('screen') || localStorage.getItem('smartbrgy_active_screen');
      const navigation = screen ? findNavItem(screen) : null;
      if (navigation && navigation.style.display !== 'none') showScreen(screen, navigation);
    } catch (_) {}
  }
});

function toggleNavigation(force) {
  const sidebar = document.getElementById('admin-navigation');
  const open = force ?? !sidebar.classList.contains('is-open');
  sidebar.classList.toggle('is-open', open);
  document.querySelector('.mobile-menu-button')?.setAttribute('aria-expanded', String(open));
}

document.addEventListener('keydown', event => {
  const modal = document.querySelector('.modal-overlay.show');
  if (event.key === 'Escape') {
    if (modal) closeModal(modal.id);
    toggleNavigation(false);
  }
  if (event.key === 'Tab' && modal) {
    const controls = [...modal.querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"]')].filter(element => !element.disabled && element.getClientRects().length);
    const first = controls[0], last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  }
});

document.addEventListener('DOMContentLoaded', () => {
  try { if (localStorage.getItem('smartbrgy_theme') === 'dark') toggleTheme(); } catch (_) {}
  document.querySelectorAll('.nav-item[onclick], .dark-mode-toggle, .topbar-avatar:not(button), .notif-badge-wrap, .modal-close:not(button)').forEach(element => {
    element.tabIndex = 0;
    element.setAttribute('role', 'button');
    if (element.classList.contains('topbar-avatar')) element.setAttribute('aria-label', 'Sign out');
    element.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); element.click(); } });
  });
  document.querySelectorAll('.form-group').forEach((group, index) => {
    const label = group.querySelector('.form-label');
    const field = group.querySelector('input:not([type="hidden"]), select, textarea');
    if (label && field && !field.labels?.length) { label.id ||= `field-label-${index}`; field.setAttribute('aria-labelledby', label.id); }
  });
});
