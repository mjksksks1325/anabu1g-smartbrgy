'use strict';

console.log('ADMIN JS LOADED');

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
  return BASE_POPULATION + Math.max(0, RESIDENTS.length - BASE_RESIDENT_SAMPLE_COUNT);
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

function refreshDashboardStats() {
  const reqs = typeof CERT_REQUESTS !== 'undefined' ? CERT_REQUESTS : [];
  const incs = typeof INCIDENTS !== 'undefined' ? INCIDENTS : [];
  const logs = typeof LIVE_AUDIT_LOGS !== 'undefined' ? LIVE_AUDIT_LOGS : [];

  const pending = reqs.filter(r => r.status !== 'Completed').length;
  const incidentCount = incs.length;

  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  set('dash-stat-pending',   pending);
  set('dash-stat-incidents', incidentCount);
  set('dash-sub-pending',   pending > 0 ? `${pending} request na hindi pa tapos` : 'No pending requests');
  set('dash-sub-incidents', incidentCount > 0 ? `${incidentCount} incident${incidentCount !== 1 ? 's' : ''} na naka-file` : 'No incidents filed');

  // Recent Activity
  const actEl = document.getElementById('dash-recent-activity');
  if (actEl) {
    const typeColor = { auth:'var(--green-500)', cert:'#F59E0B', rfid:'var(--blue-400)', record:'#A78BFA', incident:'#FB923C', security:'#EF4444' };
    const recent = logs.slice(0, 6);
    if (recent.length === 0) {
      actEl.innerHTML = '<div style="text-align:center;color:var(--text-muted);font-size:11px;padding:18px 0;">No recent activity.</div>';
    } else {
      actEl.innerHTML = recent.map(l => `
        <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:16px;flex-shrink:0;">${l.icon || '📌'}</span>
          <div style="flex:1;min-width:0;">
            <div style="font-size:12px;font-weight:600;color:${typeColor[l.type]||'var(--text-primary)'};">${l.action}</div>
            <div style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${l.detail}</div>
          </div>
          <div style="font-size:10px;color:var(--text-muted);flex-shrink:0;">${l.time}</div>
        </div>`).join('');
    }
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
  // Handled entirely by db-connector.js window.savePurok override
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
let isLightMode = false;

function toggleTheme() {
  isLightMode = !isLightMode;
  document.body.classList.toggle('light-mode', isLightMode);
  const icon = document.getElementById('theme-icon');
  const label = document.getElementById('theme-label');
  if (icon)  icon.textContent  = isLightMode ? '🌙' : '☀️';
  if (label) label.textContent = isLightMode ? 'Dark Mode' : 'Light Mode';
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
function showScreen(id, el) {
  // Role-based guard
  const screenPermMap = {
    'dashboard': 'Dashboard', 'demographics': 'Records',
    'records': 'Records', 'certificates': 'Certificates',
    'request-records': 'Requests', 'incidents': 'Incidents',
    'rfid': 'RFID', 'cabinet': 'Cabinet', 'qr': 'QR',
    'face': 'Face', 'audit': 'Audit', 'users': 'Users', 'settings': 'Settings'
  };
  const needed = screenPermMap[id];
  const allowed = ACCESS_PERMS[currentUserAccess] || ACCESS_PERMS['Full Access'];
  if (needed && !allowed.includes(needed)) {
    showToast(`🚫 Walang access sa "${needed}". Makipag-ugnayan sa Admin.`, 'red');
    return;
  }
  document.querySelectorAll('.content').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const screen = document.getElementById('screen-' + id);
  if (screen) screen.classList.add('active');
  if (el) el.classList.add('active');
  localStorage.setItem('smartbrgy_active_screen', id);
  showLoadingBar();
  if (id === 'dashboard') refreshDashboardStats();
  if (id === 'audit') {
    if (typeof reloadAuditLog === 'function') reloadAuditLog();
    else renderAuditLog();
  }
  if (id === 'request-records') renderRequestRecords(rrCurrentFilter, rrCurrentStatusFilter);
  if (id === 'certificates') {
    renderCertKanban();
    const badge = document.getElementById('cert-nav-badge');
    if (badge) { badge.textContent = '0'; badge.style.display = 'none'; }
  }
  if (id === 'demographics') renderDemographics();
}

function applyAccessControl(access) {
  currentUserAccess = access || 'Full';
  const allowed = ACCESS_PERMS[currentUserAccess] || ACCESS_PERMS['Full Access'];
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


const VALID_CREDENTIALS = [
  { empId: 'EMP-001', username: 'admin', password: 'Admin@1234!', name: 'Admin', role: 'Super Administrator' },
];

function isStrongPassword(pw) {
  // min 8 chars, at least one uppercase, lowercase, digit, special char
  return pw.length >= 8
    && /[A-Z]/.test(pw)
    && /[a-z]/.test(pw)
    && /[0-9]/.test(pw)
    && /[^A-Za-z0-9]/.test(pw);
}

function fillSample() {
  const e = document.getElementById('login-empid');
  const u = document.getElementById('login-user');
  const p = document.getElementById('login-pass');

  if (e) e.value = 'EMP-001';
  if (u) u.value = 'admin';
  if (p) p.value = 'Admin@1234!';

  showToast('Sample credentials filled. Click SECURE LOGIN.', 'green');
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
  startLoginClock();
  startTicker();
  initLoginParticles();
});


function launchApp(name, role) {
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
function doLogout() {
  if (!confirm('Mag-logout ka na?')) return;
  localStorage.removeItem('smartbrgy_active_screen');
  document.getElementById('app').classList.remove('visible');
  const ls = document.getElementById('login-screen');
  ls.style.display = 'flex';
  const box = document.getElementById('face-scan-box');
  if (box) box.classList.remove('success', 'scanning');
  const lbl = document.getElementById('face-scan-label');
  if (lbl) lbl.textContent = 'Scan Biometrics';
  const prog = document.getElementById('face-svg-progress');
  if (prog) { prog.style.strokeDashoffset = '264'; prog.style.stroke = ''; }
  const iconEl = document.getElementById('face-icon-svg');
  if (iconEl) {
    iconEl.innerHTML = '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>';
    iconEl.style.stroke = 'rgba(0,255,106,0.8)';
  }

  const ei = document.getElementById('login-empid');
  if (ei) ei.value = '';
  document.getElementById('login-user').value = '';
  document.getElementById('login-pass').value = '';
}

// ═══════════════════════════════════════
// CLOCK
// ═══════════════════════════════════════
function startClock() {
  function tick() {
    const el = document.getElementById('clock-display');
    if (el) el.textContent = new Date().toLocaleTimeString('en-PH', { hour12: false });
  }
  tick();
  setInterval(tick, 1000);
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
  const bg = getComputedStyle(document.documentElement).getPropertyValue('--card-bg').trim() || '#1a1f2e';
  ctx.beginPath();
  ctx.arc(60, 60, 30, 0, 2 * Math.PI);
  ctx.fillStyle = bg || '#1a1f2e';
  ctx.fill();

  // Center total
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--text-primary').trim() || '#fff';
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
  const total = RESIDENTS.length;
  PUROK_DATA.forEach(p => {
    const count = countByPurok[p.key] || 0;
    const pct = total > 0 ? Math.round(count / total * 100) : 0;
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
function renderDemographics() {
  renderPurokCards();
  renderAgeDistribution();
  renderSeniorList();
}

function renderPurokCards() {
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

function renderAgeDistribution() {
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

function renderSeniorList() {
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
function renderResidentsTable(filter = '', statusFilter = '') {
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

function filterResidents() {
  const q = document.getElementById('residents-search')?.value || '';
  renderResidentsTable(q);
}

function deleteResident(id) {
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

function filterResidentStatus(val, el) {
  document.querySelectorAll('#screen-records .status-pill').forEach(p => p.classList.remove('active'));
  if (el) el.classList.add('active');
  renderResidentsTable('', val);
}

function openEditResident(id) {
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

function openAddResident() {
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

function saveResident() {
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
function openViewResident(id) {
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
function renderCertRequests(filter = '') {
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
  if (mode === 'status') {
    // PURPOSE 2 — Request status
    const area = document.getElementById('qr-status-area');
    const lbl = document.getElementById('qr-status-label');
    area.classList.add('active-scan');
    lbl.textContent = '🔄 Reading QR slip...';
    setTimeout(() => {
      area.classList.remove('active-scan');
      // Pick a random request for simulation
      const r = CERT_REQUESTS[Math.floor(Math.random() * CERT_REQUESTS.length)];
      lbl.textContent = `✅ Request Found — ${r.code}`;
      showRequestStatus(r.code);
    }, 1400);
    return;
  }

  // PURPOSE 1 — Document verification
  const area = document.getElementById('qr-scan-area');
  const lbl = document.getElementById('qr-scan-label');
  area.classList.add('active-scan');
  lbl.textContent = '🔄 Reading QR code...';
  setTimeout(() => {
    area.classList.remove('active-scan');
    // Pick only completed (issued) documents for doc verification simulation
    const issued = CERT_REQUESTS.filter(r => r.status === 'Completed' || r.status === 'Ready to Print');
    const r = issued.length > 0 ? issued[Math.floor(Math.random() * issued.length)] : CERT_REQUESTS[0];
    lbl.textContent = `✅ Document Verified — ${r.code}`;
    showDocVerificationResult(r.code, true);
    pushQRRecentLog(r.code, r.name, r.type, true);
  }, 1400);
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
function checkRequestStatus(code) {
  if (!code || !code.trim()) { showToast('Please enter your confirmation code.', 'red'); return; }
  const r = CERT_REQUESTS.find(x => x.code.trim().toUpperCase() === code.trim().toUpperCase());
  showRequestStatus(r ? r.code : null, r);
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
          <div style="font-size:15px;font-weight:800;color:var(--text-primary);">${r.type}</div>
          <div style="font-size:11.5px;color:var(--text-muted);margin-top:2px;">Request for: <strong style="color:var(--text-primary);">${r.name}</strong></div>
        </div>
        ${statusBadge}
      </div>
      <div style="padding:14px 18px;display:flex;gap:16px;border-bottom:1px solid var(--row-sep);">
        <div style="flex:1;"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Confirmation Code</div><div style="font-family:var(--font-mono);font-size:14px;font-weight:700;color:${statusColor};">${r.code}</div></div>
        <div style="flex:1;"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Date Filed</div><div style="font-size:12.5px;font-weight:600;color:var(--text-primary);">${r.requested}</div></div>
        <div style="flex:1;"><div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.07em;margin-bottom:3px;">Channel</div><div><span class="badge ${r.via === 'Online' ? 'badge-purple' : 'badge-gray'}">${r.via}</span></div></div>
      </div>
      <div class="qr-status-track">${stepsHtml}</div>
      ${r.status === 'Ready to Print' ? `
      <div style="padding:12px 18px;background:var(--green-dim);border-top:1px solid var(--border-green);display:flex;align-items:center;gap:10px;font-size:12px;color:var(--green-500);">
        🏛️ <strong>Your document is ready!</strong> Visit Barangay Anabu I-G Hall and present your confirmation code: <strong style="font-family:var(--font-mono);">${r.code}</strong>
      </div>` : ''}
    </div>`;
  resultDiv.style.display = 'block';
  showToast(`Request ${r.code} found — Status: ${r.status}`, 'green');
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
// INCIDENTS
// ═══════════════════════════════════════
function renderIncidents() {
  const tbody = document.getElementById('incidents-tbody');
  if (!tbody) return;
  tbody.innerHTML = '';
  if (typeof refreshDashboardStats === 'function') refreshDashboardStats();

  // Update stat cards
  const now = new Date();
  const pending  = INCIDENTS.filter(i => i.status === 'Pending').length;
  const resolved = INCIDENTS.filter(i => {
    if (i.status !== 'Resolved') return false;
    const d = new Date(i.date);
    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth();
  }).length;
  const high = INCIDENTS.filter(i => i.severity === 'High').length;
  const setS = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
  setS('inc-stat-pending',  pending);
  setS('inc-stat-resolved', resolved);
  setS('inc-stat-high',     high);

  INCIDENTS.forEach(inc => {
    const sevClass = inc.severity === 'High' ? 'badge-red' : inc.severity === 'Medium' ? 'badge-amber' : 'badge-gray';
    const statClass = inc.status === 'Pending' ? 'badge-amber' : 'badge-green';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><span style="font-family:var(--font-mono);font-size:10.5px;color:var(--blue-400);">${escapeText(inc.id)}</span></td>
      <td style="font-weight:600;color:var(--text-primary);">${escapeText(inc.type)}</td>
      <td>${escapeText(inc.loc)}</td>
      <td style="font-size:11.5px;">${escapeText(inc.date)}</td>
      <td>${escapeText(inc.reported)}</td>
      <td style="color:${inc.complainee ? 'var(--text-primary)' : 'var(--text-muted)'};">${inc.complainee ? escapeText(inc.complainee) : '—'}</td>
      <td><span class="badge ${sevClass}">${inc.severity}</span></td>
      <td><span class="badge ${statClass}">${inc.status}</span></td>
      <td style="white-space:nowrap;">
        <button class="btn btn-xs" onclick="openViewIncident('${escapeText(inc.id)}')" style="background:rgba(34,197,94,0.08);border-color:rgba(34,197,94,0.3);color:#4ADE80;">👁 View</button>
        <button class="btn btn-xs btn-primary" onclick="openEditIncident('${escapeText(inc.id)}')">✏️ Edit</button>
        <button class="btn btn-xs btn-danger" onclick="deleteIncident('${escapeText(inc.id)}')">🗑</button>
      </td>`;
    tbody.appendChild(tr);
  });
}

function openViewIncident(id) {
  const inc = INCIDENTS.find(x => x.id === id);
  if (!inc) return;
  const sevColor = inc.severity === 'High' ? '#EF4444' : inc.severity === 'Medium' ? '#F59E0B' : '#6B7280';
  const statColor = inc.status === 'Pending' ? '#F59E0B' : '#22C55E';
  const set = (elId, val) => { const el = document.getElementById(elId); if (el) el.textContent = val || '—'; };
  set('view-inc-id',          inc.id);
  set('view-inc-type',        inc.type);
  set('view-inc-date',        inc.date);
  set('view-inc-loc',         inc.loc);
  set('view-inc-reported',    inc.reported);
  set('view-inc-complainee',  inc.complainee || '—');
  set('view-inc-description', inc.description || 'Walang detalye.');
  const sevEl = document.getElementById('view-inc-severity');
  if (sevEl) { sevEl.textContent = inc.severity; sevEl.style.color = sevColor; }
  const statEl = document.getElementById('view-inc-status');
  if (statEl) { statEl.textContent = inc.status; statEl.style.color = statColor; }
  const editBtn = document.getElementById('view-inc-edit-btn');
  if (editBtn) editBtn.onclick = () => { closeModal('modal-view-incident'); openEditIncident(id); };
  // Show attachments if any
  const attWrap = document.getElementById('view-inc-attachments-wrap');
  const attEl   = document.getElementById('view-inc-attachments');
  if (attWrap && attEl) {
    let urls = [];
    if (Array.isArray(inc.attachments)) {
      urls = inc.attachments;
    } else if (typeof inc.attachments === 'string' && inc.attachments) {
      try {
        urls = JSON.parse(inc.attachments);
        if (!Array.isArray(urls)) {
          try { urls = JSON.parse(urls); } catch(e) { urls = []; }
          if (!Array.isArray(urls)) urls = [];
        }
      } catch(e) { urls = []; }
    }
    if (urls.length) {
      attEl.innerHTML = urls.map(u => {
        const isImg = /\.(png|jpe?g|gif|webp|heic)$/i.test(u);
        return isImg
          ? `<a href="${u}" target="_blank"><img src="${u}" style="max-height:80px;max-width:120px;border-radius:6px;border:1px solid var(--border);object-fit:cover;" title="View attachment"/></a>`
          : `<a href="${u}" target="_blank" style="font-size:12px;padding:4px 10px;border:1px solid var(--border);border-radius:6px;background:var(--bg-glass);color:var(--blue-400);">📎 Attachment</a>`;
      }).join('');
      attWrap.style.display = 'block';
    } else {
      attWrap.style.display = 'none';
    }
  }
  openModal('modal-view-incident');
}

function deleteIncident(id) {
  if (!confirm('Sigurado ka bang tanggalin ang incident report na ito? Hindi na ito mababawi.')) return;
  const idx = INCIDENTS.findIndex(x => x.id === id);
  if (idx === -1) return;
  const inc = INCIDENTS[idx];
  INCIDENTS.splice(idx, 1);
  addLiveAuditEntry('🗑️', 'incident', 'Incident Report Deleted', `${id} — ${inc.type}`, currentUserName || 'Staff');
  showToast(`Incident report ${id} ay natanggal.`, '');
  renderIncidents();
}

function openEditIncident(id) {
  const inc = INCIDENTS.find(x => x.id === id);
  if (!inc) return;
  const set = (field, value) => { const el = document.getElementById(field); if (el) el.value = value || ''; };
  set('inc-edit-id',   inc.id);
  set('inc-type',      inc.type);
  set('inc-location',  inc.loc);
  set('inc-reported',  inc.reported);
  set('inc-complainee',inc.complainee);
  set('inc-severity',  inc.severity);
  set('inc-details',   inc.description);
  const attInput = document.getElementById('inc-attachments');
  if (attInput) attInput.value = '';
  const attPreview = document.getElementById('inc-attachments-preview');
  if (attPreview) attPreview.innerHTML = '';
  const titleEl = document.getElementById('inc-modal-title');
  if (titleEl) titleEl.textContent = '✏️ I-edit ang Incident Report';
  const saveBtn = document.querySelector('#modal-incident .btn-danger');
  if (saveBtn) saveBtn.textContent = '💾 I-update ang Report';
  openModal('modal-incident');
}

function openAddIncident() {
  ['inc-type','inc-location','inc-reported','inc-complainee','inc-severity','inc-date','inc-time','inc-details','inc-edit-id'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  const attInput = document.getElementById('inc-attachments');
  if (attInput) attInput.value = '';
  const attPreview = document.getElementById('inc-attachments-preview');
  if (attPreview) attPreview.innerHTML = '';
  const titleEl = document.getElementById('inc-modal-title');
  if (titleEl) titleEl.textContent = '🚨 File Incident Report';
  const saveBtn = document.querySelector('#modal-incident .btn-danger');
  if (saveBtn) saveBtn.textContent = '🚨 File Report';
  openModal('modal-incident');
}

function saveIncident() {
  const type = document.getElementById('inc-type')?.value;
  if (!type) { showToast('Please complete the incident report.', 'red'); return; }
  const newId = 'INC-2025-00' + (INCIDENTS.length + 1);
  showToast(`Incident report ${newId} filed successfully!`, 'green');
  closeModal('modal-incident');
  renderIncidents();
}

// ═══════════════════════════════════════
// AUDIT LOG
// ═══════════════════════════════════════
function renderAuditLog() {
  const container = document.getElementById('audit-log-list');
  if (!container) return;
  container.innerHTML = '';
  AUDIT_LOGS.forEach(log => {
    const div = document.createElement('div');
    div.className = 'log-item';
    div.innerHTML = `<div class="log-icon-box">${log.icon}</div><div style="flex:1;"><div class="log-action">${log.action}</div><div class="log-detail">${log.detail}</div><div class="log-time">🕐 ${log.time}</div></div>`;
    container.appendChild(div);
  });
}

// ═══════════════════════════════════════
// USER MANAGEMENT
// ═══════════════════════════════════════
function renderUsers() {
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
function openModal(id) { const el = document.getElementById(id); if (el) el.classList.add('show'); }
function closeModal(id) { const el = document.getElementById(id); if (el) el.classList.remove('show'); }
document.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('show'); });

// ═══════════════════════════════════════
// TOAST
// ═══════════════════════════════════════
function showToast(msg, type) {
  const wrap = document.getElementById('toast-wrap');
  if (!wrap) return;
  const toast = document.createElement('div');
  toast.className = 'toast' + (type ? ' ' + type : '');
  const icons = { green: '✅', red: '❌', '': 'ℹ️' };
  toast.innerHTML = `<span style="font-size:15px;">${icons[type] || 'ℹ️'}</span><span>${msg}</span>`;
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
function generateReport() {
  showToast('Generating report... Please wait.', '');
  setTimeout(() => { showToast('Report generated successfully!', 'green'); closeModal('modal-report'); }, 2000);
}
function saveSettings() { showToast('System settings saved successfully.', 'green'); }

// ═══════════════════════════════════════
// REQUEST RECORDS
// ═══════════════════════════════════════
let rrCurrentFilter = '';
let rrCurrentStatusFilter = '';

function renderRequestRecords(nameFilter = '', statusFilter = '') {
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

function filterRequestRecords() {
  rrCurrentFilter = document.getElementById('rr-search')?.value || '';
  renderRequestRecords(rrCurrentFilter, rrCurrentStatusFilter);
}

function filterRRStatus(status, el) {
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
  RESIDENTS.forEach(r => {
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

function runEligibilityCheck() {
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

function checkEligibility(residentId, certId) {
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

function elig_proceedRequest() {
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

function openViewResidentRequests(residentId) {
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
  list.innerHTML = '';
  NOTIFICATIONS.forEach(n => {
    const div = document.createElement('div');
    div.className = 'notif-item' + (n.read ? '' : ' unread');
    div.onclick = () => { n.read = true; updateNotifBadge(); renderNotifications(); toggleNotifPanel(); showScreen(n.screen, document.querySelector('.nav-item')); };
    div.innerHTML = `
      <div class="notif-dot" style="background:${n.dot};margin-top:5px;flex-shrink:0;"></div>
      <div style="flex:1;">
        <div class="notif-body"><strong>${n.title}</strong></div>
        <div class="notif-body" style="margin-top:2px;">${n.detail}</div>
        <div class="notif-time">${n.time}</div>
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
  const total = active.length;
  const groups = Object.keys(SPECIAL_GROUP_META).map(key => {
    const count = active.filter(r => getResidentGroups(r).includes(key)).length;
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
  const total = RESIDENTS.length;
  PUROK_DATA.forEach(p => {
    const count = countByPurok[p.key] || 0;
    const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0';
    const barPct = total > 0 ? (count / total * 100) : 0;
    const seniors = seniorByPurok[p.key] || 0;
    const pwd = pwdByPurok[p.key] || 0;
    const bene = beneByPurok[p.key] || 0;
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
  const total = RESIDENTS.length;
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
  const total  = active.length;
  const male   = active.filter(r => r.gender === 'Male').length;
  const female = active.filter(r => r.gender === 'Female').length;
  const uniqueHH = new Set(active.map(r => r.household).filter(Boolean)).size;
  const hh = uniqueHH > 0 ? uniqueHH : (total > 0 ? Math.ceil(total / 4) : 0);

  const setEl = (id, val) => { const el = document.getElementById(id); if (el) { el.textContent = val.toLocaleString(); el.dataset.target = val; } };
  const setSub = (id, txt) => { const el = document.getElementById(id); if (el) el.textContent = txt; };

  setEl('demo-stat-total',      total);
  setEl('demo-stat-male',       male);
  setEl('demo-stat-female',     female);
  setEl('demo-stat-households', hh);
  setSub('demo-sub-male',       total > 0 ? `${((male   / total) * 100).toFixed(1)}% ng populasyon` : '—');
  setSub('demo-sub-female',     total > 0 ? `${((female / total) * 100).toFixed(1)}% ng populasyon` : '—');
  setSub('demo-sub-households', uniqueHH > 0 ? `${hh} household${hh !== 1 ? 's' : ''} na naka-register` : `tinatayang ${hh} household`);
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
          via: 'Online',

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

            const removeBtn = `
              <button
                class="btn btn-xs"
                style="
                  flex:1;
                  font-size:9.5px;
                  padding:3px 6px;
                  background:rgba(239,68,68,0.08);
                  border-color:rgba(239,68,68,0.3);
                  color:#EF4444;
                "
                onclick="hideCertRequest('${r.code}')"
              >
                🗑️ Alisin
              </button>
            `;

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
                ${removeBtn}
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
                    ${r.name}
                  </div>

                </div>

                <div class="cert-card-type">
                  ${r.type}
                </div>

                <div class="cert-card-code">
                  ${r.code}
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
      <div class="audit-type-icon" style="background:${typeBg};border-color:${typeColor}20;">${log.icon}</div>
      <div class="audit-time">${log.time}</div>
      <div>
        <div class="audit-action">${log.action}</div>
        <div class="audit-detail">${log.detail}</div>
      </div>
      <div style="font-size:11.5px;color:var(--text-secondary);">${log.user}</div>
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

function addLiveAuditEntry(icon, type, action, detail, user) {
  const now = new Date().toLocaleTimeString('en-PH', { hour12: false });
  const entry = { icon, type, action, detail, user, time: 'Today ' + now, severity: type === 'security' ? 'danger' : 'ok' };
  LIVE_AUDIT_LOGS.unshift(entry);
  if (document.getElementById('screen-audit')?.classList.contains('active')) {
    renderAuditLog();
    const firstRow = document.querySelector('#audit-log-list .audit-log-row');
    if (firstRow) { firstRow.classList.add('new-row'); setTimeout(() => firstRow.classList.remove('new-row'), 3000); }
  }
  if (document.getElementById('screen-dashboard')?.classList.contains('active')) {
    refreshDashboardStats();
  }
}

// ═══════════════════════════════════════
// ENHANCED USER MANAGEMENT
// ═══════════════════════════════════════
const ACCESS_PERMS = {
  'Full Access':             ['Dashboard', 'Records', 'Certificates', 'Requests', 'Incidents', 'RFID', 'Cabinet', 'QR', 'Face', 'Audit', 'Users', 'Settings'],
  'Records & Certificates':  ['Dashboard', 'Records', 'Certificates', 'Requests', 'QR'],
  'Certificates Only':       ['Dashboard', 'Certificates', 'QR'],
  'Incidents Only':          ['Dashboard', 'Incidents'],
  'View Only':               ['Dashboard'],
};
const ALL_PERMS = ['Dashboard', 'Records', 'Certificates', 'Requests', 'Incidents', 'RFID', 'Cabinet', 'QR', 'Face', 'Audit', 'Users', 'Settings'];

let userRoleFilter = 'all';
let userSearch = '';

function renderUsers() {
  const container = document.getElementById('users-list-container');
  if (!container) return;
  let users = [...USERS];
  if (userRoleFilter === 'admin') users = users.filter(u => u.access === 'Full');
  if (userRoleFilter === 'active') users = users.filter(u => u.status === 'Active');
  if (userRoleFilter === 'suspended') users = users.filter(u => u.status === 'Suspended');
  if (userSearch) users = users.filter(u => u.name.toLowerCase().includes(userSearch) || u.role.toLowerCase().includes(userSearch));
  // Stats
  const setEl = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
  setEl('usr-active', USERS.filter(u => u.status === 'Active').length);
  setEl('usr-face', USERS.filter(u => u.face).length);
  setEl('usr-rfid', USERS.filter(u => u.rfid).length);
  setEl('usr-suspended', USERS.filter(u => u.status === 'Suspended').length);
  container.innerHTML = '';
  users.forEach(u => {
    const perms = ACCESS_PERMS[u.access] || [];
    const card = document.createElement('div');
    card.className = 'user-card' + (u.status === 'Suspended' ? ' suspended' : '');
    card.innerHTML = `
      <div class="user-avatar-lg" style="${u.status === 'Suspended' ? 'border-color:rgba(239,68,68,0.4);color:#EF4444;' : ''}">${u.name.split(' ').map(n => n[0]).join('').slice(0,2)}</div>
      <div class="user-info">
        <div class="user-name">${u.name}
          ${u.status === 'Suspended' ? '<span class="badge badge-red" style="margin-left:6px;font-size:9px;">Suspended</span>' : ''}
        </div>
        <div class="user-role-tag">${u.role} — <span style="font-family:var(--font-mono);font-size:10px;">${u.id}</span></div>
        <div class="user-badges" style="margin-top:5px;">
          ${u.face ? '<span class="badge badge-green" style="font-size:9.5px;">😊 Face ID</span>' : '<span class="badge badge-gray" style="font-size:9.5px;">No Face ID</span>'}
          ${u.rfid ? '<span class="badge badge-blue" style="font-size:9.5px;">📡 RFID Card</span>' : '<span class="badge badge-gray" style="font-size:9.5px;">No RFID</span>'}
          <span class="badge badge-gray" style="font-size:9.5px;">Last: ${u.last}</span>
        </div>
        <div class="user-perm-grid" style="margin-top:8px;grid-template-columns:repeat(6,1fr);">
          ${ALL_PERMS.map(p => `<div class="user-perm ${perms.includes(p) ? 'allowed' : 'denied'}" title="${p}">${p.slice(0,5)}</div>`).join('')}
        </div>
      </div>
      <div class="user-actions">
        <button class="btn btn-xs btn-primary" onclick="openEditUser('${u.id}')">✏️ Edit</button>
        ${u.status === 'Active' ? `<button class="btn btn-xs btn-danger" onclick="suspendUser('${u.id}')">🚫 Suspend</button>` : `<button class="btn btn-xs btn-green" onclick="activateUser('${u.id}')">✓ Activate</button>`}
        <button class="btn btn-xs" onclick="showToast('Viewing activity for ${u.name}','')">📋 Log</button>
        <button class="btn btn-xs btn-danger" onclick="deleteUser('${u.id}')">🗑 Delete</button>
      </div>`;
    container.appendChild(card);
  });
}

function filterUsers() {
  userSearch = document.getElementById('user-search')?.value?.toLowerCase() || '';
  renderUsers();
}
function filterUserRole(role, el) {
  document.querySelectorAll('.user-role-filter').forEach(b => b.classList.remove('active'));
  if (el) el.classList.add('active');
  userRoleFilter = role;
  renderUsers();
}
function suspendUser(id) {
  const u = USERS.find(x => x.id === id);
  if (!u) return;
  u.status = 'Suspended';
  addLiveAuditEntry('🚫', 'security', 'User Suspended', `${u.name} (${u.id}) — suspended by admin`, currentUserName || 'Staff');
  showToast(`${u.name} has been suspended.`, '');
  renderUsers();
}
function activateUser(id) {
  const u = USERS.find(x => x.id === id);
  if (!u) return;
  u.status = 'Active';
  addLiveAuditEntry('✅', 'auth', 'User Activated', `${u.name} (${u.id}) — account reactivated`, currentUserName || 'Staff');
  showToast(`${u.name} has been reactivated.`, 'green');
  renderUsers();
}

function deleteUser(id) {
  if (!confirm('Sigurado ka bang tanggalin ang user account na ito? Hindi na ito mababawi.')) return;
  const idx = USERS.findIndex(u => u.id === id);
  if (idx === -1) return;
  const name = USERS[idx].name;
  USERS.splice(idx, 1);
  const credIdx = VALID_CREDENTIALS.findIndex(c => c.empId === id);
  if (credIdx !== -1) VALID_CREDENTIALS.splice(credIdx, 1);
  addLiveAuditEntry('🗑️', 'security', 'User Account Deleted', `${id} — ${name}`, currentUserName || 'Staff');
  showToast(`User account ni ${name} ay natanggal.`, '');
  renderUsers();
}

function openAddUser() {
  const f = id => { const e = document.getElementById(id); if (e) e.value = ''; };
  ['adduser-name','adduser-empid','adduser-username','adduser-password'].forEach(f);
  const roleEl = document.getElementById('adduser-role'); if (roleEl) roleEl.selectedIndex = 3;
  const accEl  = document.getElementById('adduser-access'); if (accEl) accEl.selectedIndex = 4;
  const editId = document.getElementById('adduser-edit-id'); if (editId) editId.value = '';
  const title  = document.getElementById('adduser-modal-title'); if (title) title.textContent = '👤 Add New User';
  const passLbl = document.getElementById('adduser-pass-label'); if (passLbl) passLbl.style.display = 'none';
  const saveBtn = document.getElementById('adduser-save-btn'); if (saveBtn) saveBtn.textContent = '💾 Create User';
  const toggle = document.getElementById('adduser-facetoggle');
  if (toggle) { toggle.classList.remove('off'); toggle.classList.add('on'); }
  openModal('modal-adduser');
}

function openEditUser(id) {
  const u = USERS.find(x => x.id === id);
  if (!u) return;
  const set = (elId, val) => { const e = document.getElementById(elId); if (e) e.value = val || ''; };
  set('adduser-edit-id', u.id);
  set('adduser-name', u.name);
  set('adduser-empid', u.id);
  set('adduser-username', u.username || '');
  set('adduser-password', '');
  // Set role dropdown
  const roleEl = document.getElementById('adduser-role');
  if (roleEl) { for (let i=0;i<roleEl.options.length;i++) { if (roleEl.options[i].text === u.role) { roleEl.selectedIndex=i; break; } } }
  // Set access dropdown
  const accEl = document.getElementById('adduser-access');
  if (accEl) {
    const accessMap = { 'Full':'Full Access', 'Full Access':'Full Access', 'Records & Certs':'Records & Certificates', 'Records & Certificates':'Records & Certificates', 'Certificates Only':'Certificates Only', 'Incidents Only':'Incidents Only', 'View Only':'View Only', 'Records Only':'View Only' };
    const mapped = accessMap[u.access] || u.access;
    for (let i=0;i<accEl.options.length;i++) { if (accEl.options[i].text === mapped) { accEl.selectedIndex=i; break; } }
  }
  // Toggle face
  const toggle = document.getElementById('adduser-facetoggle');
  if (toggle) { toggle.classList.toggle('on', !!u.face); toggle.classList.toggle('off', !u.face); }
  // Update modal UI for edit mode
  const title   = document.getElementById('adduser-modal-title'); if (title) title.textContent = `✏️ Edit User — ${u.name}`;
  const passLbl = document.getElementById('adduser-pass-label'); if (passLbl) passLbl.style.display = '';
  const saveBtn = document.getElementById('adduser-save-btn'); if (saveBtn) saveBtn.textContent = '💾 Save Changes';
  openModal('modal-adduser');
}

function saveNewUser() {
  const f    = id => document.getElementById(id)?.value?.trim();
  const name = f('adduser-name');
  const pass = document.getElementById('adduser-password')?.value || '';
  const role = f('adduser-role') || 'Barangay Clerk';
  const accessRaw = f('adduser-access') || 'View Only';
  const accessMap = { 'Full Access':'Full', 'Records & Certificates':'Records & Certs', 'Certificates Only':'Certificates Only', 'Incidents Only':'Incidents Only', 'View Only':'View Only' };
  const access = accessMap[accessRaw] || accessRaw;
  const username = f('adduser-username') || (name ? name.toLowerCase().replace(/\s+/g,'.') : '');
  const faceOn   = document.getElementById('adduser-facetoggle')?.classList.contains('on');
  const editId   = f('adduser-edit-id');

  if (!name) { showToast('❌ Pakiusap ilagay ang pangalan.', 'red'); return; }

  if (editId) {
    // EDIT existing user
    const u = USERS.find(x => x.id === editId);
    if (!u) { showToast('❌ User not found.', 'red'); return; }
    u.name     = name;
    u.role     = role;
    u.access   = access;
    u.username = username;
    u.face     = faceOn;
    if (pass.length >= 8) {
      // Update password in VALID_CREDENTIALS too
      if (typeof VALID_CREDENTIALS !== 'undefined') {
        const cred = VALID_CREDENTIALS.find(c => c.username === u.username || c.empId === editId);
        if (cred) { cred.password = pass; cred.username = username; cred.name = name; cred.role = role; }
      }
    } else if (pass.length > 0 && pass.length < 8) {
      showToast('❌ Password ay dapat 8 characters man lang.', 'red'); return;
    }
    // DB sync if available
    if (typeof http !== 'undefined') {
      const payload = { name, role, access: accessRaw, username, face: faceOn, rfid: u.rfid||false, status: u.status||'Active' };
      if (pass.length >= 8) payload.password = pass;
      http.put(`/api/users/${editId}`, payload).then(() => {
        reloadUsers && reloadUsers();
      }).catch(()=>{});
    }
    addLiveAuditEntry('✏️','auth','User Updated',`${name} — ${role} — ${editId}`,'Admin');
    showToast(`✅ Na-update ang user na "${name}"!`, 'green');
    closeModal('modal-adduser');
    renderUsers();
  } else {
    // ADD new user
    if (pass.length < 8) { showToast('❌ Ang password ay dapat 8 characters man lang.', 'red'); return; }
    const newId = 'USR-' + String(USERS.length + 1).padStart(3,'0');
    const newUser = { id: newId, name, role, access, username, face: faceOn, rfid: false, status: 'Active', last: '—' };
    USERS.push(newUser);
    if (typeof VALID_CREDENTIALS !== 'undefined') {
      VALID_CREDENTIALS.push({ empId: newId, username, password: pass, name, role });
    }
    // DB sync if available
    if (typeof http !== 'undefined') {
      http.post('/api/users', { name, role, access: accessRaw, username, password: pass, face: faceOn, rfid: false }).then(result => {
        if (result?.id) newUser.id = result.id;
        reloadUsers && reloadUsers();
      }).catch(()=>{});
    }
    addLiveAuditEntry('👤','auth','New User Created',`${name} — ${role} — ${newId}`,'Admin');
    showToast(`✅ User "${name}" (${newId}) na-create!`, 'green');
    closeModal('modal-adduser');
    renderUsers();
  }
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
function startAuditAutoRefresh() {
  setInterval(() => {
    if (document.getElementById('screen-audit')?.classList.contains('active')) {
      renderAuditLog();
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
  renderUsers();
  renderIncidents();
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
  const seniors = RESIDENTS.filter(r => isSenior(r.dob));
  const countEl = document.getElementById('demo-senior-count');
  if (countEl) countEl.textContent = RESIDENTS.filter(r => isSenior(r.dob)).length.toLocaleString();
  if (seniors.length === 0) {
    container.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:20px;">No senior citizens in sample data.</div>';
    return;
  }
  container.innerHTML = `
    <table class="tbl">
      <thead><tr><th>Resident ID</th><th>Full Name</th><th>Age</th><th>Date of Birth</th><th>Zone / Purok</th><th>Status</th><th>Classification</th></tr></thead>
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

// Override openViewResident for English
function openViewResident(id) {
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

function runEligibilityCheck() {
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
function saveResident() {
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

const _origSaveIncident = saveIncident;
function saveIncident() {
  const type = document.getElementById('inc-type')?.value;
  const loc = document.getElementById('inc-location')?.value?.trim() || '';
  const reported = document.getElementById('inc-reported')?.value?.trim() || 'Anonymous';
  const complainee = document.getElementById('inc-complainee')?.value?.trim() || '';
  const severity = document.getElementById('inc-severity')?.value || 'Medium';
  const dateVal = document.getElementById('inc-date')?.value;
  const editId = document.getElementById('inc-edit-id')?.value;
  if (!type || !loc) { showToast('Please complete the incident type and location.', 'red'); return; }
  const displayDate = dateVal
    ? new Date(dateVal + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
    : new Date().toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
  if (editId) {
    const inc = INCIDENTS.find(x => x.id === editId);
    if (!inc) { showToast('Incident not found.', 'red'); return; }
    Object.assign(inc, { type, loc, reported, complainee, severity, date: displayDate });
    addLiveAuditEntry('🚨', 'incident', 'Incident Report Updated', `${editId} - ${type}`, currentUserName || 'Staff');
    showToast(`Incident ${editId} updated.`, 'green');
  } else {
    const newId = 'INC-2025-' + String(INCIDENTS.length + 1).padStart(3, '0');
    INCIDENTS.unshift({ id: newId, type, loc, date: displayDate, reported, complainee, status: 'Pending', severity });
    addLiveAuditEntry('🚨', 'incident', 'Incident Report Filed', `${newId} - ${type}`, currentUserName || 'Staff');
    showToast(`Incident report ${newId} filed successfully!`, 'green');
  }
  closeModal('modal-incident');
  ['inc-type','inc-location','inc-reported','inc-complainee','inc-severity','inc-date','inc-time','inc-details','inc-edit-id'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });
  renderIncidents();
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
