<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
<title>SmartBrgy — Barangay Anabu I-G, Imus City</title>
<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div id="loading-bar"></div>
<div id="toast-wrap"></div>

<!-- PUBLIC PORTAL -->
<div id="public-portal">
  <div class="portal-card">
    <div class="portal-header">
      <div class="seal">🏛️</div>
      <h2>Request a <span>Certificate</span></h2>
      <p>Barangay Anabu I-G, Imus City — Online Certificate Request</p>
      <p style="margin-top:6px;font-size:11px;color:var(--text-muted);">Select the document type and fill in the form. When you visit the barangay, present your confirmation code.</p>
    </div>
    <div id="portal-cert-grid" class="cert-type-grid">
      <div class="cert-type-btn" onclick="selectCertType('BC', this)"><span class="ct-icon">📄</span>Barangay Clearance</div>
      <div class="cert-type-btn" onclick="selectCertType('CR', this)"><span class="ct-icon">🏠</span>Cert. of Residency</div>
      <div class="cert-type-btn" onclick="selectCertType('CI', this)"><span class="ct-icon">📋</span>Cert. of Indigency</div>
      <div class="cert-type-btn" onclick="selectCertType('BID', this)"><span class="ct-icon">🪪</span>Barangay ID</div>
      <div class="cert-type-btn" onclick="selectCertType('CTFJ', this)"><span class="ct-icon">💼</span>First Time Jobseeker</div>
      <div class="cert-type-btn" onclick="selectCertType('BBC', this)"><span class="ct-icon">🏪</span>Business Clearance</div>
    </div>
    <button class="btn btn-green btn-full" id="portal-btn-next" style="display:none;margin-bottom:12px;" onclick="portalNext()">Susunod →</button>
    <div id="portal-form" style="display:none;">
      <div style="background:var(--bg-glass);border:1px solid var(--border-green);border-radius:var(--radius-sm);padding:10px 13px;margin-bottom:14px;font-size:12px;">
        📋 Napiling Dokumento: <strong id="portal-selected-cert" style="color:var(--green-500);"></strong>
      </div>
      <div class="form-group"><div class="form-label">Buong Full Name *</div><input class="form-input" id="portal-name" placeholder="Example: Juan dela Cruz"/></div>
      <div class="form-group"><div class="form-label">Barangay Address *</div><input class="form-input" id="portal-address" placeholder="Purok, Street, Barangay Anabu I-G"/></div>
      <div class="form-group"><div class="form-label">Contact Number</div><input class="form-input" id="portal-contact" placeholder="09XXXXXXXXX"/></div>
      <div class="form-group"><div class="form-label">Email Address <span style="color:var(--text-muted);font-weight:400;">(para sa notification)</span></div><input class="form-input" type="email" id="portal-email" placeholder="example@gmail.com"/></div>
      <div class="form-group"><div class="form-label">Purpose / Reason *</div><input class="form-input" id="portal-purpose" placeholder="Example: Employment, Loan, Scholarship..."/></div>
      <div class="form-group">
        <div class="form-label">Proof of Residency <span style="color:var(--text-muted);font-weight:400;">(optional — photo/PDF)</span></div>
        <input class="form-input" type="file" id="portal-attachment" accept="image/*,.pdf" style="padding:6px;cursor:pointer;"/>
        <div id="portal-attachment-preview" style="display:none;margin-top:8px;"></div>
      </div>
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:14px;">⚠️ Your information is kept strictly confidential.</div>
      <div style="display:flex;gap:8px;">
        <button class="btn" onclick="showPublicPortal()">← Back</button>
        <button class="btn btn-green" style="flex:1;" onclick="submitPortalRequest()">📨 Submit Request</button>
      </div>
    </div>
    <div class="portal-confirm" id="portal-confirm">
      <div style="text-align:center;margin-bottom:10px;font-size:13px;color:var(--green-500);font-weight:700;">✅ Request Submitted Successfully!</div>
      <div class="confirm-code" id="portal-confirm-code">REQ-0000</div>
      <div class="confirm-note">Keep this confirmation code safe. Present this when you visit the barangay.</div>
    </div>
  </div>
  <button class="btn btn-danger" onclick="hidePublicPortal()">✕ Close</button>
</div>

<!-- LOGIN SCREEN -->

<!-- MAIN APPLICATION -->
<div id="app" style="display:block;">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-logo">
      <div class="topbar-logo-box"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G logo"/></div>
      <div class="topbar-brand">SmartBrgy <span>ANABU I-G • IMUS CITY</span></div>
    </div>
    <div class="topbar-sep"></div>
    <div class="topbar-status"><span class="pulse-dot"></span> System Online</div>
    <div class="topbar-right">
      <div class="dark-mode-toggle" id="theme-toggle" onclick="toggleTheme()">
        <span id="theme-icon">☀️</span>
        <span id="theme-label">Light Mode</span>
      </div>
      <div class="topbar-chip">📡 RFID: Active</div>
      <div class="topbar-chip notif-badge-wrap" onclick="toggleNotifPanel()" id="notif-chip" style="cursor:pointer;position:relative;">🔔 Notifications <span class="notif-count" id="notif-count">3</span></div>
      <div class="topbar-chip" id="clock-display">--:--:--</div>
      <div class="topbar-avatar" onclick="doLogout()" title="Mag-logout">JC</div>
    </div>
  </div>

  <div class="app-body">
    <!-- SIDEBAR -->
    <div class="sidebar">
      <div class="sidebar-sec">
        <div class="sidebar-label">Overview</div>
        <div class="nav-item active" data-perm="Dashboard" onclick="showScreen('dashboard',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
          Dashboard
        </div>
        <div class="nav-item" data-perm="Records" onclick="showScreen('demographics',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Demographics
        </div>
      </div>
      <div class="sidebar-sec">
        <div class="sidebar-label">Records</div>
        <div class="nav-item" data-perm="Records" onclick="showScreen('records',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Resident Records
        </div>
        <div class="nav-item"
            data-perm="Certificates"
            onclick="showScreen('certificates', this)">

            <svg class="nav-icon"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <path d="M8 21h8M12 17v4"/>
            </svg>

            Certificates & Clearances

            <span class="nav-badge"
                id="cert-nav-badge"
                style="display:none;">0</span>
        </div>
        <div class="nav-item" data-perm="Requests" onclick="showScreen('request-records',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
          Request Eligibility
        </div>
        <div class="nav-item" data-perm="Incidents" onclick="showScreen('incidents',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Incident Reports
        </div>
      </div>
      <div class="sidebar-sec">
        <div class="sidebar-label">IoT Security</div>
        <div class="nav-item" data-perm="RFID" onclick="showScreen('rfid',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
          RFID File Tracking
        </div>
        <div class="nav-item" data-perm="Cabinet" onclick="showScreen('cabinet',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="2" width="18" height="20" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="16" x2="21" y2="16"/><circle cx="8" cy="5.5" r="1"/><circle cx="8" cy="12.5" r="1"/><circle cx="8" cy="19" r="1"/></svg>
          Smart Cabinet
        </div>
        <div class="nav-item" data-perm="QR" onclick="showScreen('qr',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h.01"/></svg>
          QR Verification
        </div>
        <div class="nav-item" data-perm="Face" onclick="showScreen('face',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/></svg>
          Face Recognition
        </div>
      </div>
      <div class="sidebar-sec">
        <div class="sidebar-label">Administration</div>
        <div class="nav-item" data-perm="Audit" onclick="showScreen('audit',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
          Audit Log
        </div>
        <div class="nav-item" data-perm="Users" onclick="showScreen('users',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          User Management
        </div>
        <div class="nav-item" data-perm="Settings" onclick="showScreen('settings',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          Settings
        </div>
        <div class="nav-item" onclick="window.open('portal.html','_blank')" style="margin-top:8px;border-top:1px solid var(--border-color);padding-top:8px;">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
          Portal ng Residente
        </div>
      </div>
      <div class="sidebar-footer">
        <div class="su-card">
          <div class="su-avatar" id="sidebar-user-avatar">JC</div>
          <div><div class="su-name" id="sidebar-user-name">Juan dela Cruz</div><div class="su-role" id="sidebar-user-role">⬤ Super Administrator</div></div>
        </div>
      </div>
    </div>

    <!-- DASHBOARD -->
    <div class="content active" id="screen-dashboard">
      <div class="page-header-row">
        <div class="page-header"><h1><span>Dashboard</span></h1><p>Real-time overview — Barangay Anabu I-G, Imus City</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" onclick="refreshDashboardStats();showToast('Dashboard refreshed','green')">⟳ Refresh</button>
        </div>
      </div>
      <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-label">Total Residents</div><div class="stat-value counter" data-target="0">0</div><div class="stat-sub">Registered households: 0</div><div class="stat-icon">👥</div></div>
        <div class="stat-card green"><div class="stat-label">RFID Active Tags</div><div class="stat-value counter" data-target="0">0</div><div class="stat-sub">No tags registered</div><div class="stat-icon">📡</div></div>
        <div class="stat-card amber"><div class="stat-label">Pending Requests</div><div class="stat-value" id="dash-stat-pending">0</div><div class="stat-sub" id="dash-sub-pending">No pending requests</div><div class="stat-icon">📋</div></div>
        <div class="stat-card red"><div class="stat-label">Incident Reports</div><div class="stat-value" id="dash-stat-incidents">0</div><div class="stat-sub" id="dash-sub-incidents">No incidents filed</div><div class="stat-icon">🚨</div></div>
      </div>
      <div class="two-col">
        <div class="card">
          <div class="card-header"><div><div class="card-title">📊 Uri ng Certificate Requests</div><div class="card-sub">Real-time na bilang ng bawat uri ng kahilingan</div></div><span class="badge badge-green">Live</span></div>
          <div id="dash-chart"></div>
        </div>
        <div class="card">
          <div class="card-header"><div><div class="card-title">🔔 Recent Activity</div><div class="card-sub">Last 24 hours</div></div><span class="badge badge-green">Live</span></div>
          <div id="dash-recent-activity">
            <div style="text-align:center;color:var(--text-muted);font-size:11px;padding:18px 0;">No recent activity.</div>
          </div>
        </div>
      </div>
      <div class="three-col">
        <div class="card">
          <div class="card-header"><div class="card-title">🏘️ Purok Breakdown</div></div>
          <div style="display:flex;flex-direction:column;gap:9px;" id="dash-purok-breakdown"></div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">⚡ System Status</div></div>
          <div class="sys-row"><div class="sys-name">RFID Reader Network</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
          <div class="sys-row"><div class="sys-name">Smart Cabinet IoT</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
          <div class="sys-row"><div class="sys-name">Biometric Server</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
          <div class="sys-row"><div class="sys-name">QR Scanner Module</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
          <div class="sys-row"><div class="sys-name">Database Backup</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
          <div class="sys-row"><div class="sys-name">Firewall / IDS</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">📑 Quick Actions</div></div>
          <div style="display:flex;flex-direction:column;gap:7px;">
            <button class="btn btn-green btn-full" onclick="showScreen('records',document.querySelectorAll('.nav-item')[2]);openAddResident()">➕ Register New Resident</button>
            <button class="btn btn-primary btn-full" onclick="showScreen('certificates',document.querySelectorAll('.nav-item')[3])">📄 Issue Certificate</button>
            <button class="btn btn-full" onclick="showScreen('rfid',document.querySelectorAll('.nav-item')[6])">📡 RFID Scan Session</button>
            <button class="btn btn-full" onclick="showScreen('cabinet',document.querySelectorAll('.nav-item')[7])">🗄️ Smart Cabinet Control</button>
            <button class="btn btn-full" onclick="showScreen('incidents',document.querySelectorAll('.nav-item')[5])">🚨 File Incident Report</button>
          </div>
        </div>
      </div>
    </div>

    <!-- DEMOGRAPHICS -->
    <div class="content" id="screen-demographics">
      <div class="page-header-row">
        <div class="page-header"><h1>Population <span>Demographics</span></h1><p>Census data — Barangay Anabu I-G, Imus City | 2025</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-sm" onclick="showToast('Demographics data refreshed.','green')">⟳ Refresh</button>
          <button class="btn btn-green btn-sm" onclick="showToast('Exporting census report...','green')">📥 Export Census</button>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-label">Total Population</div><div class="stat-value counter" id="demo-stat-total" data-target="0">0</div><div class="stat-sub">Based on registered residents</div><div class="stat-icon">🏘️</div></div>
        <div class="stat-card green"><div class="stat-label">Male</div><div class="stat-value counter" id="demo-stat-male" data-target="0">0</div><div class="stat-sub" id="demo-sub-male">—</div><div class="stat-icon">👨</div></div>
        <div class="stat-card blue"><div class="stat-label">Female</div><div class="stat-value counter" id="demo-stat-female" data-target="0">0</div><div class="stat-sub" id="demo-sub-female">—</div><div class="stat-icon">👩</div></div>
        <div class="stat-card amber"><div class="stat-label">Households</div><div class="stat-value counter" id="demo-stat-households" data-target="0">0</div><div class="stat-sub" id="demo-sub-households">—</div><div class="stat-icon">🏠</div></div>
      </div>

      <!-- Purok Population Cards -->
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">🏘️ Population per Purok / Subdivision</div><div class="card-sub">Resident count per zone including special group breakdown</div></div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
            <button class="btn btn-green btn-sm" onclick="openAddPurok()">Add Purok</button>
            <span class="live-badge"><span class="pulse-dot"></span>Live Count</span>
          </div>
        </div>
        <div class="demo-purok-grid" id="demo-purok-grid"></div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">💡 Counts include all registered residents. Special groups (Senior Citizens, PWD, Solo Parents, etc.) are automatically tagged per zone.</div>
      </div>

      <div class="two-col">
        <!-- Age Distribution -->
        <div class="card">
          <div class="card-header"><div class="card-title">📊 Age Distribution</div><div class="card-sub">Population breakdown by age group</div></div>
          <div style="display:flex;flex-direction:column;gap:12px;" id="age-distribution-list"></div>
        </div>

        <!-- Special Groups -->
        <div class="card">
          <div class="card-header"><div class="card-title">⭐ Special Groups</div><div class="card-sub">Classified sectors of the community</div></div>
          <div id="special-groups-container"></div>
        </div>
      </div>

      <!-- Senior Citizens Register -->
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">👴👵 Senior Citizens Register (60+)</div><div class="card-sub">Auto-updated when a resident reaches age 60 based on date of birth</div></div>
          <span class="badge badge-senior">🔄 Auto-Updated</span>
        </div>
        <div id="senior-citizens-list"></div>
        <div style="margin-top:10px;padding:10px;background:var(--senior-bg);border:1px solid var(--senior-border);border-radius:var(--radius-sm);font-size:11.5px;color:var(--senior-color);">
          ℹ️ <strong>Automated Process:</strong> The system checks each resident's date of birth. Upon reaching age 60, they are automatically added to this register and receive a Senior Citizen badge on their profile.
        </div>
      </div>
    </div>

    <!-- RESIDENT RECORDS -->
    <div class="content" id="screen-records">
      <div class="page-header-row">
        <div class="page-header"><h1>Resident <span>Records</span></h1><p>All registered residents — Barangay Anabu I-G</p></div>
        <button class="btn btn-green" onclick="openAddResident()">➕ New Resident</button>
      </div>
      <div class="search-row">
        <div class="search-wrap"><span class="si">🔍</span><input class="search-input" id="residents-search" placeholder="Search resident by name, ID, or purok..." oninput="filterResidents()"/></div>
        <button class="btn btn-primary btn-sm" onclick="showToast('Nag-export ng resident records...','green')">Export</button>
      </div>
      <div class="status-bar">
        <div class="status-pill active" onclick="filterResidentStatus('',this)">All</div>
        <div class="status-pill" onclick="filterResidentStatus('Active',this)">Active</div>
        <div class="status-pill" onclick="filterResidentStatus('Inactive',this)">Inactive</div>
        <div class="status-pill" onclick="filterResidentStatus('Senior',this)">👴 Senior Citizens</div>
      </div>
      <div class="card">
        <table class="tbl">
          <thead><tr><th>Resident ID</th><th>Full Name</th><th>Age</th><th>Purok</th><th>Gender</th><th>Civil Status</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="records-tbody"></tbody>
        </table>
      </div>
    </div>

    <!-- CERTIFICATES -->
    <div class="content" id="screen-certificates">
      <div class="page-header-row">
        <div class="page-header"><h1>Certificates &amp; <span>Clearances</span></h1><p>Issue, track, and verify barangay documents — QR-enabled</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" onclick="showPublicPortal()">🌐 Public Portal</button>
          <button class="btn btn-green btn-sm" onclick="openModal('modal-cert-issue')">📄 Issue Certificate</button>
        </div>
      </div>

      <!-- Process Flow -->
      <div class="card" style="padding:14px 18px;margin-bottom:16px;">
        <div class="card-header" style="margin-bottom:10px;"><div class="card-title">📋 Certificate Process Flow</div></div>
        <div class="process-steps">
          <div class="process-step"><div class="ps-num active">1</div><div class="ps-label">Request<br>Received</div></div>
          <div class="process-step"><div class="ps-num active">2</div><div class="ps-label">Eligibility<br>Check</div></div>
          <div class="process-step"><div class="ps-num active">3</div><div class="ps-label">Staff<br>Processing</div></div>
          <div class="process-step"><div class="ps-num active">4</div><div class="ps-label">Print + QR<br>Embed</div></div>
          <div class="process-step"><div class="ps-num active">5</div><div class="ps-label">Release &amp;<br>Signature</div></div>
          <div class="process-step"><div class="ps-num active">6</div><div class="ps-label">QR<br>Verification</div></div>
        </div>
      </div>

      <!-- Summary Stats -->
      <div class="stats-grid stats-grid-4" style="margin-bottom:16px;">
            <div class="stat-card green">
                <div class="stat-label">Processed Today</div>
                <div class="stat-value" id="cert-today">
                    {{ $requests->filter(fn($r) => $r->status === 'released' && $r->updated_at->isToday())->count() }}
                </div>
                <div class="stat-sub">Released / Completed</div>
            </div>

            <div class="stat-card amber">
                <div class="stat-label">Pending / Processing</div>
                <div class="stat-value" id="cert-pending">
                    {{ $requests->whereIn('status', ['pending', 'processing'])->count() }}
                </div>
                <div class="stat-sub">In progress</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-label">Ready to Print</div>
                <div class="stat-value" id="cert-ready">
                    {{ $requests->whereIn('status', ['approved', 'ready_for_release'])->count() }}
                </div>
                <div class="stat-sub">Awaiting release</div>
            </div>

            <div class="stat-card green">
                <div class="stat-label">Online Requests</div>
                <div class="stat-value" id="cert-online">
                    {{ $requests->count() }}
                </div>
                <div class="stat-sub">Via public portal</div>
            </div>
      </div>

      <!-- Kanban Board -->
        <!-- Document Requests -->
        <!-- Request Board -->
      <div class="card" style="margin-bottom:16px;">
          <div class="card-header">
              <div>
                  <div class="card-title">📁 Request Board</div>
                  <div class="card-sub">Drag-and-drop style status tracking</div>
              </div>

              <div class="search-wrap" style="width:220px; flex:none;">
                  <span class="si">🔍</span>
                  <input
                      class="search-input"
                      id="document-request-search"
                      placeholder="Search requests..."
                      oninput="filterRequestBoard(this.value)"
                  >
              </div>
          </div>

          @php
              $pendingRequests = $requests->where('status', 'pending');
              $processingRequests = $requests->where('status', 'processing');
              $readyRequests = $requests->whereIn('status', ['approved', 'ready_for_release']);
              $releasedRequests = $requests->where('status', 'released');
          @endphp

          <div class="request-board-grid">

              <!-- ONLINE REQUESTS -->
              <div class="request-board-column">
                  <div class="request-board-header">
                      <span>🌐 ONLINE REQUESTS</span>
                      <span class="badge badge-blue">{{ $pendingRequests->count() }}</span>
                  </div>

                  <div class="request-board-list">
                      @forelse ($pendingRequests as $request)
                          @include('admin.partials.request-card', [
                              'request' => $request,
                              'stage' => 'pending'
                          ])
                      @empty
                          <div class="request-board-empty">No pending requests.</div>
                      @endforelse
                  </div>
              </div>

              <!-- PROCESSING -->
              <div class="request-board-column">
                  <div class="request-board-header">
                      <span>⏳ PROCESSING</span>
                      <span class="badge badge-amber">{{ $processingRequests->count() }}</span>
                  </div>

                  <div class="request-board-list">
                      @forelse ($processingRequests as $request)
                          @include('admin.partials.request-card', [
                              'request' => $request,
                              'stage' => 'processing'
                          ])
                      @empty
                          <div class="request-board-empty">No requests processing.</div>
                      @endforelse
                  </div>
              </div>

              <!-- READY TO PRINT -->
              <div class="request-board-column">
                  <div class="request-board-header">
                      <span>🖨️ READY TO PRINT</span>
                      <span class="badge badge-green">{{ $readyRequests->count() }}</span>
                  </div>

                  <div class="request-board-list">
                      @forelse ($readyRequests as $request)
                          @include('admin.partials.request-card', [
                              'request' => $request,
                              'stage' => 'ready'
                          ])
                      @empty
                          <div class="request-board-empty">No requests ready to print.</div>
                      @endforelse
                  </div>
              </div>

              <!-- RELEASED -->
              <div class="request-board-column">
                  <div class="request-board-header">
                      <span>✅ RELEASED / DONE</span>
                      <span class="badge badge-green">{{ $releasedRequests->count() }}</span>
                  </div>

                  <div class="request-board-list">
                      @forelse ($releasedRequests as $request)
                          @include('admin.partials.request-card', [
                              'request' => $request,
                              'stage' => 'released'
                          ])
                      @empty
                          <div class="request-board-empty">No released requests.</div>
                      @endforelse
                  </div>
              </div>

          </div>
      </div>

      <!-- Certificate Types Reference -->
      <div class="two-col">
        <div class="card">
          <div class="card-header"><div class="card-title">📃 Available Certificates & Fees</div></div>
          <div id="cert-types-list" style="display:flex;flex-direction:column;gap:7px;"></div>
        </div>
        <div class="card card-green">
          <div class="card-header"><div class="card-title">🔐 QR Verification System</div></div>
          <div style="font-size:12px;color:var(--text-secondary);line-height:1.8;">
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>📱</span><span>Every issued document gets a unique embedded QR code</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>🔒</span><span>QR contains encrypted document metadata — unforgeable</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>✅</span><span>3rd parties (employers, schools) can scan to verify authenticity</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>🚫</span><span>Photocopies and fake documents cannot pass QR verification</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;"><span>📊</span><span>Every scan is logged in the audit trail</span></div>
          </div>
          <button class="btn btn-green btn-full" style="margin-top:12px;" onclick="showScreen('qr',document.querySelectorAll('.nav-item')[8])">📷 Open QR Scanner →</button>
        </div>
      </div>
    </div>

    <!-- INCIDENTS -->
    <div class="content" id="screen-incidents">
      <div class="page-header-row">
        <div class="page-header"><h1>Incident <span>Reports</span></h1><p>Complaints and incidents in the barangay</p></div>
        <button class="btn btn-danger" onclick="openAddIncident()">🚨 File Incident</button>
      </div>
      <div class="stats-grid stats-grid-3">
        <div class="stat-card red"><div class="stat-label">Pending</div><div class="stat-value" id="inc-stat-pending">0</div><div class="stat-icon">⏳</div></div>
        <div class="stat-card green"><div class="stat-label">Resolved This Month</div><div class="stat-value" id="inc-stat-resolved">0</div><div class="stat-icon">✅</div></div>
        <div class="stat-card amber"><div class="stat-label">High Severity</div><div class="stat-value" id="inc-stat-high">0</div><div class="stat-icon">⚠️</div></div>
      </div>
      <div class="card">
        <table class="tbl">
          <thead><tr><th>Incident ID</th><th>Uri</th><th>Lokasyon</th><th>Petsa</th><th>Nag-report</th><th>Ine-reklamo</th><th>Severity</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="incidents-tbody"></tbody>
        </table>
      </div>
    </div>

    <!-- REQUEST RECORDS -->
    <div class="content" id="screen-request-records">
      <div class="page-header-row">
        <div class="page-header"><h1>Request <span>Eligibility</span></h1><p>Complete document request history per resident — eligibility tracking & audit trail</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-eligibility-check')">🔍 Check Eligibility</button>
          <button class="btn btn-green btn-sm" onclick="showToast('Exporting request records...','green')">📥 Export</button>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid stats-grid-4" style="margin-bottom:16px;">
        <div class="stat-card green"><div class="stat-label">Total Requests</div><div class="stat-value" id="rr-total">0</div><div class="stat-icon">📋</div></div>
        <div class="stat-card blue"><div class="stat-label">Completed</div><div class="stat-value" id="rr-completed">0</div><div class="stat-icon">✅</div></div>
        <div class="stat-card amber"><div class="stat-label">Ineligible / Blocked</div><div class="stat-value" id="rr-blocked">0</div><div class="stat-icon">🚫</div></div>
        <div class="stat-card red"><div class="stat-label">With Blotter Record</div><div class="stat-value" id="rr-blotter">0</div><div class="stat-icon">⚠️</div></div>
      </div>

      <!-- Eligibility Rules Reference -->
      <div class="card" style="margin-bottom:14px;">
        <div class="card-header" onclick="toggleEligRules()" style="cursor:pointer;">
          <div><div class="card-title">📘 Eligibility Rules Reference</div><div class="card-sub">Click to expand — rules applied per document type</div></div>
          <span style="color:var(--text-muted);font-size:12px;" id="elig-rules-chevron">▼ Show</span>
        </div>
        <div id="elig-rules-panel" style="display:none;">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:4px;" id="elig-rules-grid"></div>
        </div>
      </div>

      <!-- Search & Filter -->
      <div class="search-row">
        <div class="search-wrap"><span class="si">🔍</span><input class="search-input" id="rr-search" placeholder="Search resident by name or ID..." oninput="filterRequestRecords()"/></div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <button class="btn btn-sm rr-filter-btn active" onclick="filterRRStatus('',this)">All</button>
          <button class="btn btn-sm rr-filter-btn" onclick="filterRRStatus('Eligible',this)">✅ Eligible</button>
          <button class="btn btn-sm rr-filter-btn" onclick="filterRRStatus('Ineligible',this)">🚫 Ineligible</button>
          <button class="btn btn-sm rr-filter-btn" onclick="filterRRStatus('Blotter',this)">⚠️ With Blotter</button>
        </div>
      </div>
      <div id="rr-resident-list" style="display:flex;flex-direction:column;gap:10px;margin-top:4px;"></div>
    </div>

    <!-- RFID FILE TRACKING -->
    <div class="content" id="screen-rfid">
      <div class="page-header"><h1>RFID <span>File Tracking</span></h1><p>Real-time document and file tracking using RFID tags</p></div>
      <div class="two-col">
        <div>
          <div class="card" style="margin-bottom:14px;border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.04);">
            <div class="card-header"><div class="card-title">📡 RFID Scanner</div><span class="badge badge-red">Offline</span></div>
            <div class="rfid-scan-area" id="rfid-scan-area" onclick="simulateRFIDScan()">
              <div class="rfid-icon">📡</div>
              <div class="rfid-label" id="rfid-scan-label">Click to simulate RFID scan</div>
              <div class="rfid-sub">Place file or card on the RFID reader</div>
            </div>
          </div>
          <div class="card"><div class="card-header"><div class="card-title">🏷️ Registered RFID Tags</div></div><div id="rfid-tags-list" style="display:flex;flex-direction:column;gap:8px;"></div></div>
        </div>
        <div class="card">
          <div class="card-header"><div><div class="card-title">📋 RFID Scan Log</div><div class="card-sub">Real-time log of all RFID activities</div></div><span class="badge badge-green">Live</span></div>
          <div style="overflow-x:auto;">
            <table class="tbl">
              <thead><tr><th>Time</th><th>Tag ID</th><th>File / Tao</th><th>Lokasyon</th><th>Direksyon</th><th>Status</th></tr></thead>
              <tbody id="rfid-log-tbody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- SMART CABINET -->
    <div class="content" id="screen-cabinet">
      <div class="page-header"><h1>Smart <span>Cabinet</span></h1><p>IoT-controlled na filing cabinet — 2-Factor Authentication: Facial Recognition + RFID Key Card</p></div>

      <!-- 2FA Authentication Panel -->
      <div class="cab-2fa-panel">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <div>
            <div class="card-title">🔐 Cabinet 2-Factor Authentication</div>
            <div style="font-size:11.5px;color:var(--text-muted);margin-top:3px;">Kailangan ng DALAWANG verification para mabuksan ang pangunahing pinto ng cabinet</div>
          </div>
          <span class="badge badge-green"><span class="pulse-dot" style="margin-right:4px;"></span>Cabinet Online</span>
        </div>
        <div class="cab-2fa-steps">
          <div class="cab-step active" id="cab-step-1">
            <div class="cab-step-icon">😊</div>
            <div class="cab-step-label">Step 1: Face Scan</div>
          </div>
          <div class="cab-step" id="cab-step-2">
            <div class="cab-step-icon">📡</div>
            <div class="cab-step-label">Step 2: RFID Card</div>
          </div>
          <div class="cab-step" id="cab-step-3">
            <div class="cab-step-icon">🔓</div>
            <div class="cab-step-label">Cabinet Open</div>
          </div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;">
          <button class="btn btn-green btn-sm" onclick="simulateCabFaceScan()">😊 I-simulate ang Face Scan</button>
          <button class="btn btn-primary btn-sm" onclick="simulateCabRFID()">📡 I-tap ang RFID Card</button>
          <button class="btn btn-danger btn-sm" onclick="resetCabAuth()">↺ I-reset</button>
        </div>
        <div id="cab-auth-status" style="display:none;margin-top:10px;padding:8px 12px;border-radius:var(--radius-sm);font-size:12px;"></div>
      </div>

      <div class="col-3-2">
        <div>
          <div class="card card-green" style="margin-bottom:14px;">
            <div class="card-header">
              <div><div class="card-title">🗄️ Cabinet Control Panel</div><div class="card-sub">Mga drawer — i-tap ang RFID key card para mabuksan ang bawat isa</div></div>
              <span class="badge badge-green"><span class="pulse-dot" style="margin-right:4px;"></span>Online</span>
            </div>
            <div class="search-row" style="margin-bottom:10px;">
              <div class="search-wrap"><span class="si">🔍</span><input class="search-input" id="drawer-search" placeholder="Hanapin ang drawer o kategorya..." oninput="searchDrawer()"/></div>
            </div>
            <div id="cabinet-drawers" style="display:flex;flex-direction:column;gap:6px;"></div>
          </div>

          <!-- Folder/File RFID inside drawers -->
          <div class="card">
            <div class="card-header">
              <div><div class="card-title">📁 Files sa Loob ng Cabinet</div><div class="card-sub">Bawat folder ay may RFID tag — alam ng sistema kung anong file ang kinukuha</div></div>
              <span class="badge badge-amber">RFID-Tagged</span>
            </div>
            <div id="cabinet-folders-list" style="display:flex;flex-direction:column;gap:4px;"></div>
            <div style="margin-top:10px;padding:8px 12px;background:var(--green-dim);border:1px solid var(--border-green);border-radius:var(--radius-sm);font-size:11.5px;color:var(--green-500);">
              📡 Kapag kinuha ang isang folder, nire-record ng RFID scanner kung sino ang kumuha at kelan.
            </div>
          </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:14px;">
          <div class="cabinet-visual">
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.08em;margin-bottom:12px;">Cabinet Diagram</div>
            <div class="cabinet-body">
              <div class="cabinet-top">🏛️ SMARTBRGY CABINET</div>
              <div style="padding:6px;display:flex;flex-direction:column;gap:3px;">
                <div style="background:rgba(0,138,56,0.15);border-radius:4px;padding:5px 7px;display:flex;align-items:center;justify-content:space-between;">
                  <span style="font-size:9px;color:var(--text-secondary);">Row A — Resident Files A–L</span>
                  <span style="font-size:9px;color:var(--green-500);">🟢 Unlocked</span>
                </div>
                <div style="background:rgba(239,68,68,0.08);border-radius:4px;padding:5px 7px;display:flex;align-items:center;justify-content:space-between;">
                  <span style="font-size:9px;color:var(--text-secondary);">Row B — Resident Files M–Z</span>
                  <span style="font-size:9px;color:#EF4444;">🔴 Locked</span>
                </div>
                <div style="background:rgba(0,138,56,0.08);border-radius:4px;padding:5px 7px;display:flex;align-items:center;justify-content:space-between;">
                  <span style="font-size:9px;color:var(--text-secondary);">Row C — Certificates</span>
                  <span style="font-size:9px;color:var(--green-500);">🟢 Unlocked</span>
                </div>
                <div style="background:rgba(239,68,68,0.08);border-radius:4px;padding:5px 7px;display:flex;align-items:center;justify-content:space-between;">
                  <span style="font-size:9px;color:var(--text-secondary);">Row D — Sensitive Records</span>
                  <span style="font-size:9px;color:#EF4444;">🔴 Locked</span>
                </div>
              </div>
            </div>
            <div style="margin-top:10px;font-size:10px;color:var(--text-muted);">🟢 Unlocked &nbsp;🔴 Locked (RFID + Face required)</div>
          </div>
          <div class="card card-green">
            <div class="card-header"><div class="card-title">🔐 Access Methods</div></div>
            <div style="display:flex;flex-direction:column;gap:9px;font-size:12px;">
              <div style="display:flex;align-items:center;gap:10px;"><span style="font-size:18px;">🚪</span><div><div style="font-weight:600;color:var(--text-primary);">Main Door — 2FA Required</div><div style="color:var(--text-muted);font-size:11px;">Face Recognition + RFID Key Card (parehong kailangan)</div></div></div>
              <div style="display:flex;align-items:center;gap:10px;"><span style="font-size:18px;">📡</span><div><div style="font-weight:600;color:var(--text-primary);">Drawer — RFID Card Only</div><div style="color:var(--text-muted);font-size:11px;">I-tap ang authorized RFID card sa bawat drawer</div></div></div>
              <div style="display:flex;align-items:center;gap:10px;"><span style="font-size:18px;">📁</span><div><div style="font-weight:600;color:var(--text-primary);">Folder Tracking — Auto RFID</div><div style="color:var(--text-muted);font-size:11px;">All ng folder sa loob ay RFID-tagged, alam kung sino ang kumuha</div></div></div>
            </div>
          </div>
          <div class="card">
            <div class="card-header"><div class="card-title">⚡ Cabinet Status</div></div>
            <div class="sys-row"><div class="sys-name">Power Supply</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
            <div class="sys-row"><div class="sys-name">Lock Mechanism</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
            <div class="sys-row"><div class="sys-name">RFID Reader</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
            <div class="sys-row"><div class="sys-name">Camera Module</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
            <div class="sys-row"><div class="sys-name">Tamper Alert</div><div class="sys-status"><div class="sdot offline"></div><span style="color:#EF4444">Offline</span></div></div>
          </div>
          <div class="card">
            <div class="card-header"><div class="card-title">📋 Cabinet Access Log</div></div>
            <div style="overflow-x:auto;">
              <table class="tbl">
                <thead><tr><th>Time</th><th>Drawer</th><th>Actions</th><th>Staff</th></tr></thead>
                <tbody id="cabinet-log-tbody">
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- QR VERIFICATION — TWO PURPOSES -->
    <div class="content" id="screen-qr">
      <div class="page-header">
        <h1>QR <span>Verification</span></h1>
        <p>Two purposes: verify document authenticity (for employers/schools/third parties) and check request status (for residents)</p>
      </div>

      <!-- PURPOSE CARDS -->
      <div class="two-col" style="margin-bottom:20px;">
        <div style="border:1.5px solid var(--border-green);border-radius:var(--radius-lg);padding:18px;background:var(--green-dim);">
          <div style="display:inline-flex;align-items:center;gap:6px;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;background:var(--border-green);color:var(--green-500);padding:3px 10px;border-radius:20px;margin-bottom:10px;">📄 PURPOSE 1</div>
          <div style="font-size:14px;font-weight:700;color:var(--green-500);margin-bottom:8px;">Document Authenticity Verification</div>
          <div style="font-size:12px;color:var(--text-secondary);line-height:1.8;">
            For <strong style="color:var(--text-primary);">employers, schools, banks, and any third party</strong> who received a barangay document and wants to confirm it is real.<br><br>
            ✅ Every printed barangay document (clearance, indigency, residency, etc.) has an <strong style="color:var(--text-primary);">embedded QR code</strong>.<br>
            ✅ Scanning it shows: document type, resident name, date issued, issued by, and validity — <strong style="color:var(--text-primary);">no system login needed</strong>.<br>
            🚫 Fake or photocopied documents <strong style="color:#EF4444;">cannot pass</strong> this verification.
          </div>
        </div>
        <div style="border:1.5px solid rgba(42,126,211,.3);border-radius:var(--radius-lg);padding:18px;background:rgba(42,126,211,.05);">
          <div style="display:inline-flex;align-items:center;gap:6px;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;background:rgba(42,126,211,.2);color:var(--blue-400);padding:3px 10px;border-radius:20px;margin-bottom:10px;">📋 PURPOSE 2</div>
          <div style="font-size:14px;font-weight:700;color:var(--blue-400);margin-bottom:8px;">Request Status Tracking</div>
          <div style="font-size:12px;color:var(--text-secondary);line-height:1.8;">
            For <strong style="color:var(--text-primary);">residents</strong> who filed a request and want to know if their document is ready — without going back to the barangay.<br><br>
            ✅ When a resident files a request, they receive a <strong style="color:var(--text-primary);">QR code slip</strong> (or confirmation code).<br>
            ✅ Scanning the QR shows: current status (Processing / Ready / Released), estimated release date.<br>
            📱 Works from any phone — <strong style="color:var(--text-primary);">no app needed</strong>, no login required.
          </div>
        </div>
      </div>

      <!-- QR MODE TABS -->
      <div style="display:flex;gap:0;border-bottom:1px solid var(--border);margin-bottom:18px;">
        <div class="qr-tab active" id="qr-tab-doc" onclick="switchQRTab('doc')" style="padding:9px 20px;font-size:12.5px;font-weight:600;cursor:pointer;border-bottom:2px solid var(--green-500);color:var(--green-500);margin-bottom:-1px;">📄 Document Verification</div>
        <div class="qr-tab" id="qr-tab-status" onclick="switchQRTab('status')" style="padding:9px 20px;font-size:12.5px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent;color:var(--text-muted);margin-bottom:-1px;">📋 Request Status</div>
      </div>

      <!-- TAB 1: DOCUMENT VERIFICATION -->
      <div id="qr-panel-doc">
        <div class="two-col">
          <div>
            <div class="card card-green" style="margin-bottom:14px;">
              <div class="card-header"><div class="card-title">📷 Document QR Scanner</div><span class="badge badge-green">Active</span></div>
              <div class="qr-scan-area" id="qr-scan-area" onclick="simulateQRScan('doc')">
                <div class="qr-icon">▣</div>
                <div style="font-size:13px;font-weight:600;color:var(--green-500);" id="qr-scan-label">Click to Simulate Scanning a Document QR</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Point camera at the QR code printed on the barangay document</div>
              </div>
            </div>
            <div class="card">
              <div class="card-header"><div class="card-title">🔍 Manual Code Lookup</div></div>
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px;">Enter the confirmation code found on the document or given to the resident upon request.</div>
              <div class="form-group">
                <div class="form-label">Confirmation Code / Document ID</div>
                <div style="display:flex;gap:8px;">
                  <input class="form-input" id="manual-code" placeholder="e.g. REQ-7742" style="flex:1;"/>
                  <button class="btn btn-green" onclick="verifyCertCode(document.getElementById('manual-code').value)">🔍 Verify</button>
                </div>
              </div>
            </div>
          </div>
          <div>
            <!-- VERIFICATION RESULT (hidden by default) -->
            <div id="qr-doc-result" style="display:none;margin-bottom:14px;">
              <div class="card" id="qr-doc-result-card"></div>
            </div>
            <div class="card" style="margin-bottom:14px;">
              <div class="card-header"><div class="card-title">📋 Recent Verifications</div><span class="rt-indicator"><span class="pulse-dot"></span>Live</span></div>
              <div id="qr-recent-list" style="display:flex;flex-direction:column;gap:7px;"></div>
            </div>
            <div class="card card-green">
              <div class="card-header"><div class="card-title">🔐 Document Security</div></div>
              <div style="font-size:12px;color:var(--text-secondary);line-height:1.9;">
                🔒 QR contains encrypted metadata — cannot be forged.<br>
                📋 Each QR is unique per document issuance.<br>
                ⏱️ Validity period is embedded and checked automatically.<br>
                🚫 Photocopies fail verification — only originals pass.<br>
                📍 Verification logs every scan with timestamp and location.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: REQUEST STATUS -->
      <div id="qr-panel-status" style="display:none;">
        <div class="two-col">
          <div>
            <div class="card card-blue" style="margin-bottom:14px;border-color:rgba(42,126,211,.3);background:rgba(42,126,211,.05);">
              <div class="card-header"><div class="card-title">📱 Request Status QR Scanner</div><span class="badge badge-blue">Active</span></div>
              <div class="qr-scan-area" id="qr-status-area" style="border-color:rgba(42,126,211,.4);background:rgba(42,126,211,.06);" onclick="simulateQRScan('status')">
                <div class="qr-icon" style="color:var(--blue-400);">▣</div>
                <div style="font-size:13px;font-weight:600;color:var(--blue-400);" id="qr-status-label">Click to Simulate Scanning a Request QR Slip</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Residents scan the QR from their request slip to check status</div>
              </div>
            </div>
            <div class="card">
              <div class="card-header"><div class="card-title">🔍 Manual Request Lookup</div></div>
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px;">Residents can enter their confirmation code here to check their document status anytime.</div>
              <div class="form-group">
                <div class="form-label">Request Confirmation Code</div>
                <div style="display:flex;gap:8px;">
                  <input class="form-input" id="status-code" placeholder="e.g. REQ-7741" style="flex:1;"/>
                  <button class="btn btn-primary" onclick="checkRequestStatus(document.getElementById('status-code').value)">🔍 Check Status</button>
                </div>
              </div>
            </div>
          </div>
          <div>
            <!-- STATUS RESULT (hidden by default) -->
            <div id="qr-status-result" style="display:none;margin-bottom:14px;">
              <div class="card" id="qr-status-result-card"></div>
            </div>
            <div class="card" style="margin-bottom:14px;">
              <div class="card-header"><div class="card-title">📊 What Each Status Means</div></div>
              <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-sm);">
                  <span class="badge badge-amber">⏳ Processing</span>
                  <div style="font-size:12px;color:var(--text-secondary);">Request received. Staff is reviewing and preparing your document. Usually takes 1 business day.</div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-sm);">
                  <span class="badge badge-green">🖨️ Ready to Pick Up</span>
                  <div style="font-size:12px;color:var(--text-secondary);">Your document is printed and ready! Visit the Barangay Hall and present your confirmation code.</div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-sm);">
                  <span class="badge badge-blue">✅ Released</span>
                  <div style="font-size:12px;color:var(--text-secondary);">Document has been released to the resident. Transaction complete.</div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:10px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius-sm);">
                  <span class="badge badge-red">🚫 On Hold</span>
                  <div style="font-size:12px;color:var(--text-secondary);">Request is on hold — usually due to missing requirements or a blotter record. Visit the barangay for details.</div>
                </div>
              </div>
            </div>
            <div class="card" style="background:rgba(42,126,211,.05);border-color:rgba(42,126,211,.25);">
              <div class="card-header"><div class="card-title" style="color:var(--blue-400);">📱 For Residents: No App Needed</div></div>
              <div style="font-size:12px;color:var(--text-secondary);line-height:1.9;">
                📲 When you file a request, you receive a <strong style="color:var(--text-primary);">QR code slip</strong>.<br>
                🔍 Scan it anytime using any phone camera.<br>
                🌐 Or visit the <strong style="color:var(--text-primary);">Barangay Public Portal</strong> and enter your code.<br>
                ⏰ No need to visit the barangay just to check status.<br>
                📩 You'll also receive an SMS when your document is ready.
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- FACE RECOGNITION -->
    <div class="content" id="screen-face">
      <div class="page-header"><h1>Face <span>Recognition</span></h1><p>Biometric access control for barangay staff and cabinet access</p></div>
      <div class="two-col">
        <div>
          <div class="card card-green" style="margin-bottom:14px;">
            <div class="card-header"><div class="card-title">😊 Face Recognition Scanner</div><span class="badge badge-green">Camera Active</span></div>
            <div class="face-live-area" id="face-live-area" onclick="simulateFaceRecognition()">
              <div class="face-ring-wrap"><div class="face-ring"></div><div class="face-emoji">😊</div></div>
              <div style="font-size:13px;font-weight:600;color:var(--green-500);margin-top:4px;" id="face-live-label">Click to simulate face scan</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Look at the camera to be recognized</div>
            </div>
            <div class="recognition-result" id="face-live-result">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="font-size:30px;">😊</div>
                <div><div class="rec-name">Juan dela Cruz</div><div class="rec-role">⬤ Super Administrator — Full Access</div><div class="rec-conf">Confidence: 99.4% • Match ID: ANB-USR-001</div></div>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header"><div class="card-title">👤 Enrolled Faces</div></div>
            <div style="display:flex;flex-direction:column;gap:7px;">
              <div class="cert-request-item"><div class="cert-icon-box">😊</div><div style="flex:1;"><div class="cert-name">Juan dela Cruz</div><div class="cert-detail">Admin • 2 face templates enrolled</div></div><span class="badge badge-green">Active</span></div>
              <div class="cert-request-item"><div class="cert-icon-box">😊</div><div style="flex:1;"><div class="cert-name">Maria R. Lim</div><div class="cert-detail">Records Officer • 1 face template</div></div><span class="badge badge-green">Active</span></div>
              <div class="cert-request-item"><div class="cert-icon-box">😊</div><div style="flex:1;"><div class="cert-name">Rosario C. Dela Vega</div><div class="cert-detail">Tanod Captain • 1 face template</div></div><span class="badge badge-green">Active</span></div>
            </div>
            <button class="btn btn-green btn-full" style="margin-top:12px;" onclick="showToast('Face enrollment mode activated. Pumunta sa camera.','green')">➕ Enroll New Face</button>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">📋 Face Recognition Access Log</div></div>
          <div style="display:flex;flex-direction:column;gap:0;">
            <div class="log-item"><div class="log-icon-box">✅</div><div><div class="log-action">Access Granted — Juan dela Cruz</div><div class="log-detail">Admin Panel Login • Confidence: 99.4%</div><div class="log-time">Today 09:02:14</div></div></div>
            <div class="log-item"><div class="log-icon-box">✅</div><div><div class="log-action">Cabinet Access — Rosario Dela Vega</div><div class="log-detail">Row C - Drawer 1 unlocked • Confidence: 97.8%</div><div class="log-time">Today 08:55:00</div></div></div>
            <div class="log-item"><div class="log-icon-box">❌</div><div><div class="log-action">Access Denied — Unknown Face</div><div class="log-detail">No match found • Alert sent to admin</div><div class="log-time">Today 08:30:44</div></div></div>
            <div class="log-item"><div class="log-icon-box">✅</div><div><div class="log-action">Login — Maria R. Lim</div><div class="log-detail">Records Officer Login • Confidence: 98.1%</div><div class="log-time">Today 08:10:22</div></div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- AUDIT LOG -->
    <div class="content" id="screen-audit">
      <div class="page-header-row">
        <div class="page-header"><h1>Audit <span>Log</span></h1><p>Real-time record of all system actions — transparency, accountability, and security monitoring</p></div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span class="live-badge"><span class="pulse-dot"></span>Live Feed</span>
        </div>
      </div>

      <!-- Audit Stats -->
      <div class="audit-stats">
        <div class="audit-stat"><div class="audit-stat-val" id="astat-total">0</div><div class="audit-stat-label">Total Events</div></div>
        <div class="audit-stat"><div class="audit-stat-val" style="color:var(--green-500);" id="astat-auth">0</div><div class="audit-stat-label">Auth Events</div></div>
        <div class="audit-stat"><div class="audit-stat-val" style="color:var(--blue-400);" id="astat-records">0</div><div class="audit-stat-label">Record Changes</div></div>
        <div class="audit-stat"><div class="audit-stat-val" style="color:#F59E0B;" id="astat-certs">0</div><div class="audit-stat-label">Certificates</div></div>
        <div class="audit-stat"><div class="audit-stat-val" style="color:#EF4444;" id="astat-security">0</div><div class="audit-stat-label">Security Alerts</div></div>
      </div>

      <div class="card">
        <div class="card-header">
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <div class="search-wrap" style="width:220px;"><span class="si">🔍</span><input class="search-input" id="audit-search" placeholder="Search logs..." oninput="filterAuditLog()"/></div>
            <div class="audit-filter-bar" style="margin-bottom:0;">
              <button class="audit-type-btn active" onclick="filterAuditType('all',this)">All</button>
              <button class="audit-type-btn" onclick="filterAuditType('auth',this)">🔐 Auth</button>
              <button class="audit-type-btn" onclick="filterAuditType('cert',this)">📄 Certificates</button>
              <button class="audit-type-btn" onclick="filterAuditType('rfid',this)">📡 RFID</button>
              <button class="audit-type-btn" onclick="filterAuditType('record',this)">🧑 Records</button>
              <button class="audit-type-btn" onclick="filterAuditType('incident',this)">🚨 Incidents</button>
              <button class="audit-type-btn" onclick="filterAuditType('security',this)">⚠️ Security</button>
            </div>
          </div>
        </div>
        <!-- Log Table Header -->
        <div style="display:grid;grid-template-columns:50px 130px 1fr 200px 90px;gap:12px;padding:7px 14px;border-bottom:1px solid var(--border);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);background:var(--table-header);border-radius:var(--radius-sm);">
          <span>Type</span><span>Timestamp</span><span>Action & Detail</span><span>User / Source</span><span>Category</span>
        </div>
        <div id="audit-log-list" style="max-height:520px;overflow-y:auto;"></div>
        <div style="padding:10px 14px;border-top:1px solid var(--border);font-size:11px;color:var(--text-muted);display:flex;justify-content:space-between;">
          <span id="audit-showing-count">Showing all events</span>
          <span>🔄 Auto-refreshes every 15 seconds</span>
        </div>
      </div>
    </div>

    <!-- USER MANAGEMENT -->
    <div class="content" id="screen-users">
      <div class="page-header-row">
        <div class="page-header"><h1>User <span>Management</span></h1><p>Authorized staff, access levels, biometric enrollment, and security configuration</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" onclick="showToast('Exporting user report...','green')">📥 Export</button>
          <button class="btn btn-green" onclick="openAddUser()">➕ Add New User</button>
        </div>
      </div>

      <!-- Security Shield Banner -->
      <div class="security-shield">
        <div class="security-shield-icon">🛡️</div>
        <div style="flex:1;">
          <div style="font-size:13px;font-weight:700;color:var(--green-500);">Access Control — Secure</div>
          <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px;">All users are verified with biometric authentication. Role-based access prevents unauthorized data access.</div>
        </div>
        <div style="display:flex;gap:8px;">
          <span class="sec-status-ok">✓ 2FA Active</span>
          <span class="sec-status-ok">✓ Facial Recognition</span>
          <span class="sec-status-ok">✓ RFID Cards</span>
        </div>
      </div>

      <!-- User Stats -->
      <div class="stats-grid stats-grid-4" style="margin-bottom:16px;">
        <div class="stat-card green"><div class="stat-label">Active Users</div><div class="stat-value" id="usr-active">0</div><div class="stat-icon">👤</div></div>
        <div class="stat-card blue"><div class="stat-label">Face ID Enrolled</div><div class="stat-value" id="usr-face">0</div><div class="stat-icon">😊</div></div>
        <div class="stat-card amber"><div class="stat-label">RFID Cards</div><div class="stat-value" id="usr-rfid">0</div><div class="stat-icon">📡</div></div>
        <div class="stat-card red"><div class="stat-label">Suspended</div><div class="stat-value" id="usr-suspended">0</div><div class="stat-icon">🚫</div></div>
      </div>

      <!-- Search & Filter -->
      <div class="search-row" style="margin-bottom:12px;">
        <div class="search-wrap"><span class="si">🔍</span><input class="search-input" id="user-search" placeholder="Search users by name or role..." oninput="filterUsers()"/></div>
        <div style="display:flex;gap:6px;">
          <button class="btn btn-sm user-role-filter active" onclick="filterUserRole('all',this)">All</button>
          <button class="btn btn-sm user-role-filter" onclick="filterUserRole('admin',this)">Admins</button>
          <button class="btn btn-sm user-role-filter" onclick="filterUserRole('active',this)">Active</button>
          <button class="btn btn-sm user-role-filter" onclick="filterUserRole('suspended',this)">Suspended</button>
        </div>
      </div>

      <!-- Users List -->
      <div id="users-list-container"></div>

      <!-- Security Events -->
      <div class="card" style="margin-top:14px;">
        <div class="card-header">
          <div><div class="card-title">🚨 Recent Security Events</div><div class="card-sub">Login attempts, access denials, and suspicious activity</div></div>
          <span class="live-badge"><span class="pulse-dot"></span>Live</span>
        </div>
        <div class="threat-feed" id="security-feed">
          <div class="threat-row">
            <div class="threat-icon threat-low">✅</div>
            <div><div class="threat-text"><strong>Juan dela Cruz</strong> — Successful login via Face Recognition (99.4% confidence)</div><div class="threat-time">Today 09:02:14</div></div>
          </div>
          <div class="threat-row">
            <div class="threat-icon threat-high">🚨</div>
            <div><div class="threat-text"><strong>Unknown</strong> — 3 failed login attempts from IP 192.168.1.47 — Account temporarily blocked</div><div class="threat-time">Today 10:33:00</div></div>
          </div>
          <div class="threat-row">
            <div class="threat-icon threat-med">⚠️</div>
            <div><div class="threat-text"><strong>Eduardo Salazar</strong> — Account suspended — attempted access blocked</div><div class="threat-time">Apr 10, 10:00 AM</div></div>
          </div>
          <div class="threat-row">
            <div class="threat-icon threat-low">✅</div>
            <div><div class="threat-text"><strong>Maria R. Lim</strong> — Successful login, Records Officer session started</div><div class="threat-time">Today 08:47:00</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- SETTINGS -->
    <div class="content" id="screen-settings">
      <div class="page-header"><h1>System <span>Settings</span></h1><p>Configuration ng SmartBrgy system — Barangay Anabu I-G</p></div>
      <div class="two-col">
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div class="card">
            <div class="card-header"><div class="card-title">🔐 Security Settings</div></div>
            <div class="setting-row"><div><div class="setting-label">Facial Recognition Login</div><div class="setting-sub">Require face scan for login</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">2-Factor Authentication</div><div class="setting-sub">OTP on mobile for all logins</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">Auto Session Timeout</div><div class="setting-sub">Auto-logout after 15 minutes of inactivity</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">Failed Login Lockout</div><div class="setting-sub">Block account after 5 failed attempts</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">Audit Logging</div><div class="setting-sub">Log all system actions</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
          </div>
          <div class="card">
            <div class="card-header"><div class="card-title">📡 IoT Device Settings</div></div>
            <div class="setting-row"><div><div class="setting-label">RFID Reader Auto-Scan</div><div class="setting-sub">Auto-scan when someone approaches</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">Smart Cabinet Alerts</div><div class="setting-sub">Alert on unauthorized cabinet access</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">Tamper Detection</div><div class="setting-sub">Alert admin on cabinet tamper attempts</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">Night Mode Lock</div><div class="setting-sub">Lock all cabinets after office hours</div></div><div class="toggle"><div class="toggle-knob"></div></div></div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div class="card">
            <div class="card-header"><div class="card-title">🎨 Display Settings</div></div>
            <div class="setting-row">
              <div><div class="setting-label">Dark / Light Mode</div><div class="setting-sub">Toggle system theme</div></div>
              <button class="btn btn-primary btn-sm" onclick="toggleTheme()">🌗 Toggle Theme</button>
            </div>
            <div class="setting-row"><div><div class="setting-label">Font Size</div><div class="setting-sub">Larger text for easier reading</div></div>
              <div style="display:flex;gap:6px;">
                <button class="btn btn-xs" onclick="changeFontSize(-1)">A-</button>
                <button class="btn btn-xs btn-green" onclick="changeFontSize(0)">Default</button>
                <button class="btn btn-xs" onclick="changeFontSize(1)">A+</button>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header"><div class="card-title">🗂️ Document Settings</div></div>
            <div class="setting-row"><div><div class="setting-label">QR Code sa All ng Documents</div><div class="setting-sub">Auto-embed QR on every printed document</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">Online Certificate Request</div><div class="setting-sub">Allow residents to request documents online</div></div><div class="toggle on"><div class="toggle-knob"></div></div></div>
            <div class="setting-row"><div><div class="setting-label">SMS Notification</div><div class="setting-sub">SMS resident when document is ready</div></div><div class="toggle"><div class="toggle-knob"></div></div></div>
          </div>
          <div class="card">
            <div class="card-header"><div class="card-title">🏛️ Barangay Information</div></div>
            <div class="form-group"><div class="form-label">Full Name ng Barangay</div><input class="form-input" value="Barangay Anabu I-G"/></div>
            <div class="form-group"><div class="form-label">City / Municipality</div><input class="form-input" value="Imus City, Cavite"/></div>
            <div class="form-group"><div class="form-label">Full Name ng Barangay Captain</div><input class="form-input" value="Hon. Robinson Papa Silla"/></div>
            <div class="form-group"><div class="form-label">Barangay Phone</div><input class="form-input" value="(046) XXX-XXXX"/></div>
            <button class="btn btn-green" onclick="saveSettings()">💾 Save Settings</button>
          </div>
        </div>
      </div>
    </div>

  
  <!-- NOTIFICATION PANEL -->
  <div class="notif-panel" id="notif-panel">
    <div class="notif-panel-header">
      <div class="notif-panel-title">🔔 Notifications</div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn btn-xs btn-green" onclick="markAllNotifsRead()">Mark all read</button>
        <span style="font-size:18px;cursor:pointer;color:var(--text-muted);" onclick="toggleNotifPanel()">✕</span>
      </div>
    </div>
    <div id="notif-list"></div>
    <div style="padding:10px 16px;text-align:center;border-top:1px solid var(--border);">
      <button class="btn btn-xs" onclick="toggleNotifPanel();showScreen('audit',document.querySelectorAll('.nav-item')[10])">View all in Audit Log</button>
    </div>
  </div>
  </div><!-- end app-body -->
</div><!-- end app -->

@if (session('success'))
    <div id="flash-success"
         style="
             position: fixed;
             top: 20px;
             right: 20px;
             z-index: 9999;
             padding: 12px 16px;
             border-radius: 8px;
             background: var(--green-dim);
             border: 1px solid var(--border-green);
             color: var(--green);
             font-size: 13px;
             box-shadow: 0 8px 24px rgba(0,0,0,.25);
         ">
        ✅ {{ session('success') }}
    </div>

    <script>
        setTimeout(() => {
            const flash = document.getElementById('flash-success');
            if (flash) {
                flash.style.opacity = '0';
                flash.style.transition = 'opacity 0.3s ease';

                setTimeout(() => flash.remove(), 300);
            }
        }, 3000);
    </script>
@endif

<!-- MODALS -->
<!-- Document Request Details -->
<div class="modal-overlay" id="modal-document-request">
    <div class="modal modal-lg">

        <div class="modal-header">
            <div class="modal-title">
                📄 <span>Document Request Details</span>
            </div>

            <div class="modal-close"
                 onclick="closeModal('modal-document-request')">
                ✕
            </div>
        </div>

        <form id="document-request-form"
              method="POST">

            @csrf
            @method('PATCH')

            <div class="form-row">

                <div class="form-group">
                    <div class="form-label">Reference Code</div>
                    <input class="form-input"
                           id="dr-reference"
                           readonly>
                </div>

                <div class="form-group">
                    <div class="form-label">Resident Name</div>
                    <input class="form-input"
                           id="dr-name"
                           readonly>
                </div>

            </div>

            <div class="form-row">

                <div class="form-group">
                    <div class="form-label">Document Type</div>
                    <input class="form-input"
                           id="dr-document"
                           readonly>
                </div>

                <div class="form-group">
                    <div class="form-label">Date of Birth</div>
                    <input class="form-input"
                           id="dr-dob"
                           readonly>
                </div>

            </div>

            <div class="form-group">
                <div class="form-label">Address</div>
                <input class="form-input"
                       id="dr-address"
                       readonly>
            </div>

            <div class="form-row">

                <div class="form-group">
                    <div class="form-label">Contact Number</div>
                    <input class="form-input"
                           id="dr-contact"
                           readonly>
                </div>

                <div class="form-group">
                    <div class="form-label">Business Name</div>
                    <input class="form-input"
                           id="dr-business"
                           readonly>
                </div>

            </div>

            <div class="form-group">
                <div class="form-label">Purpose</div>
                <textarea class="form-input"
                          id="dr-purpose"
                          rows="2"
                          readonly></textarea>
            </div>

            <div class="form-group">
                <div class="form-label">Status</div>

                <select class="form-input"
                        id="dr-status"
                        name="status"
                        required>

                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="approved">Approved</option>
                    <option value="ready_for_release">Ready for Release</option>
                    <option value="released">Released</option>
                    <option value="rejected">Rejected</option>

                </select>
            </div>

            <div class="form-group">
                <div class="form-label">Remarks</div>

                <textarea class="form-input"
                          id="dr-remarks"
                          name="remarks"
                          rows="3"
                          placeholder="Optional staff remarks..."></textarea>
            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn"
                        onclick="closeModal('modal-document-request')">
                    Cancel
                </button>

                <button type="button"
                        class="btn btn-primary"
                        id="dr-issue-certificate"
                        style="display:none;"
                        onclick="openIssueCertificateFromRequest()">
                    📄 Issue Certificate
                </button>

                <button type="submit"
                        class="btn btn-green">
                    ✓ Update Request
                </button>

            </div>

        </form>

    </div>
</div>
<!-- Add/Edit Purok -->
<div class="modal-overlay" id="modal-purok">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">🏘️ Add Purok</div><div class="modal-close" onclick="closeModal('modal-purok')">x</div></div>
    <div class="form-group"><div class="form-label">Purok ID / Key *</div><input class="form-input" id="purok-name" placeholder="Example: purok1"/><div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Unique ID — hindi na mababago pagkatapos ma-save.</div></div>
    <div class="form-group"><div class="form-label">Display Name *</div><input class="form-input" id="purok-label" placeholder="Example: Purok 1 - Sampaguita"/></div>
    <div class="form-group"><div class="form-label">Display Color</div><input class="form-input" type="color" id="purok-color" value="#22C55E"/></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-purok')">Cancel</button>
      <button class="btn btn-green" onclick="savePurok()">💾 Save Purok</button>
    </div>
  </div>
</div>

<!-- Add/Edit Resident -->
<div class="modal-overlay" id="modal-resident">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-resident-title">➕ <span>I-register ang Bagong Resident</span></div><div class="modal-close" onclick="closeModal('modal-resident')">✕</div></div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Last Name *</div><input class="form-input" id="res-lastname" placeholder="Santos"/></div>
      <div class="form-group"><div class="form-label">Full Name *</div><input class="form-input" id="res-name" placeholder="Maria"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Date of Birth *</div><input class="form-input" type="date" id="res-dob"/></div>
      <div class="form-group"><div class="form-label">Gender *</div><select class="form-input" id="res-gender"><option>Male</option><option>Female</option></select></div>
    </div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Civil Status</div><select class="form-input" id="res-civil"><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option></select></div>
      <div class="form-group"><div class="form-label">Purok / Zone *</div><select class="form-input" id="res-purok"><option>Purok 1 - Sampaguita</option><option>Purok 2 - Rosal</option><option>Purok 3 - Camia</option><option>Purok 4 - Ilang-Ilang</option><option>Purok 5 - Mabini</option></select></div>
    </div>
    <div class="form-group"><div class="form-label">Detailed Address</div><input class="form-input" id="res-address" placeholder="Block, Lot, Street"/></div>
    <div class="form-group">
      <div class="form-label">Special Groups</div>
      <div class="special-check-grid">
        <label><input type="checkbox" class="res-special-group" value="PWD"/> PWD</label>
        <label><input type="checkbox" class="res-special-group" value="Solo Parent"/> Solo Parent</label>
        <label><input type="checkbox" class="res-special-group" value="Indigenous People"/> Indigenous People</label>
        <label><input type="checkbox" class="res-special-group" value="4Ps Beneficiary"/> 4Ps Beneficiary</label>
        <label><input type="checkbox" class="res-special-group" value="Teenage Mother"/> Teenage Mother</label>
        <label><input type="checkbox" class="res-special-group" value="Out-of-School Youth"/> Out-of-School Youth</label>
        <label><input type="checkbox" class="res-special-group" value="Unemployed Adult"/> Unemployed Adult</label>
        <label><input type="checkbox" class="res-special-group" value="Malnourished Child"/> Malnourished Child</label>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Contact Number</div><input class="form-input" id="res-contact" placeholder="09XXXXXXXXX"/></div>
      <div class="form-group"><div class="form-label">Type of Residency</div><select class="form-input" id="res-type"><option>Homeowner</option><option>Renter</option><option>Boarder</option></select></div>
    </div>
    <input type="hidden" id="res-edit-id" value=""/>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-resident')">Cancel</button>
      <button class="btn btn-green" onclick="saveResident()">💾 Save Record</button>
    </div>
  </div>
</div>

<!-- View Resident -->
<div class="modal-overlay" id="modal-view-resident">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">👤 <span>Resident Details</span></div><div class="modal-close" onclick="closeModal('modal-view-resident')">✕</div></div>
    <div id="view-resident-content"></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-view-resident')">Close</button>
      <button class="btn btn-primary" onclick="openViewResidentRequests(currentViewResidentId)">📋 View Requests</button>
      <button class="btn btn-green" onclick="openEligibilityForResident(currentViewResidentId)">🔍 Check Eligibility</button>
    </div>
  </div>
</div>

<!-- Issue Certificate -->
<!-- View Certificate Request -->
<div class="modal-overlay" id="modal-view-certreq">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <div class="modal-title">📋 Detalye ng Request</div>
      <div class="modal-close" onclick="closeModal('modal-view-certreq')">✕</div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--border);">
      <span style="font-size:13px;font-family:var(--font-mono);color:var(--blue-400);font-weight:700;" id="vreq-code">—</span>
      <span style="flex:1;font-size:13px;font-weight:700;color:var(--text-primary);" id="vreq-type">—</span>
      <span id="vreq-status-badge"></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Pangalan</div>
        <div style="font-size:13px;color:var(--text-primary);font-weight:600;" id="vreq-name">—</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Petsa ng Request</div>
        <div style="font-size:13px;color:var(--text-primary);" id="vreq-date">—</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Address</div>
        <div style="font-size:13px;color:var(--text-primary);" id="vreq-address">—</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Email</div>
        <div style="font-size:13px;color:var(--text-primary);" id="vreq-email">—</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Date of Birth</div>
        <div style="font-size:13px;color:var(--text-primary);" id="vreq-dob">—</div>
      </div>
      <div style="grid-column:1/-1;">
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Layunin / Purpose</div>
        <div style="font-size:13px;color:var(--text-primary);background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 10px;" id="vreq-purpose">—</div>
      </div>
    </div>
    <div id="vreq-attachment-wrap" style="display:none;margin-bottom:14px;">
      <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Attachment / Valid ID</div>
      <div id="vreq-attachment"></div>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-view-certreq')">Isara</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modal-cert-issue">
    <div class="modal">

        <div class="modal-header">
            <div class="modal-title">📄 <span>Issue Certificate</span></div>
            <div class="modal-close" onclick="closeModal('modal-cert-issue')">✕</div>
        </div>

        <form method="POST"
              action="{{ route('admin.issued-certificates.store') }}">

            @csrf

            <input type="hidden"
                  id="issue-document-request-id"
                  name="document_request_id">

            <div class="form-group">
                <div class="form-label">Certificate Type</div>

                <select class="form-input"
                        id="issue-certificate-type"
                        name="certificate_type"
                        required>
                    <option value="Barangay Clearance">Barangay Clearance</option>
                    <option value="Certificate of Residency">Certificate of Residency</option>
                    <option value="Certificate of Indigency">Certificate of Indigency</option>
                    <option value="Barangay ID">Barangay ID</option>
                    <option value="First Time Job Seeker">First Time Job Seeker</option>
                    <option value="Business Clearance">Business Clearance</option>
                </select>
            </div>

            <div class="form-group">
                <div class="form-label">Full Name ng Resident</div>

                <input class="form-input"
                       id="issue-resident-name"
                       name="resident_name"
                       placeholder="Search or type name..."
                       required>
            </div>

            <div class="form-group">
                <div class="form-label">Purpose / Reason</div>

                <input class="form-input"
                       id="issue-purpose"
                       name="purpose"
                       placeholder="Employment, Loan, Scholarship...">
            </div>

            <div class="form-group">
                <div class="form-label">Payment</div>

                <input class="form-input"
                       type="number"
                       id="issue-amount-paid"
                       name="amount_paid"
                       min="0"
                       step="0.01"
                       value="0.00"
                       readonly>
            </div>

            <div style="background:var(--green-dim);
                        border:1px solid var(--border-green);
                        border-radius:var(--radius-sm);
                        padding:10px;
                        font-size:11.5px;
                        color:var(--text-secondary);
                        margin-bottom:14px;">
                🔐 A unique certificate number and verification code will be generated automatically.
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn"
                        onclick="closeModal('modal-cert-issue')">
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-green">
                    📄 Issue Certificate
                </button>
            </div>

        </form>

    </div>
</div>

<!-- QR Verify -->
<div class="modal-overlay" id="modal-qr-verify">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">✅ <span>QR Verification Result</span></div><div class="modal-close" onclick="closeModal('modal-qr-verify')">✕</div></div>
    <div style="background:var(--green-dim);border:1px solid var(--border-green);border-radius:var(--radius);padding:16px;margin-bottom:14px;text-align:center;">
      <div style="font-size:28px;margin-bottom:6px;">✅</div>
      <div style="font-size:14px;font-weight:700;color:var(--green-500);">AUTHENTIC DOCUMENT</div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">Verified authentic document from Barangay Anabu I-G</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;">
      <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-muted);">Document Type</span><span style="color:var(--text-primary);font-weight:600;" id="qr-verify-doc-title">—</span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-muted);">Full Name</span><span style="color:var(--text-primary);font-weight:600;" id="qr-verify-name">—</span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-muted);">Confirmation Code</span><span style="color:var(--green-500);font-family:var(--font-mono);" id="qr-verify-code">—</span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-muted);">Request Date</span><span style="color:var(--text-primary);" id="qr-verify-date">—</span></div>
      <div style="display:flex;justify-content:space-between;font-size:13px;"><span style="color:var(--text-muted);">Status</span><span id="qr-verify-status">—</span></div>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-qr-verify')">Close</button>
      <button class="btn btn-green" onclick="showToast('Document marked as released.','green');closeModal('modal-qr-verify')">✅ Mark as Released</button>
    </div>
  </div>
</div>

<!-- Incident Report -->
<div class="modal-overlay" id="modal-incident">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="inc-modal-title">🚨 File Incident Report</div><div class="modal-close" onclick="closeModal('modal-incident')">✕</div></div>
    <div class="form-group"><div class="form-label">Incident Type *</div>
      <select class="form-input" id="inc-type"><option value="">Select type...</option><option>Noise Complaint</option><option>Property Dispute</option><option>Domestic Dispute</option><option>Vandalism</option><option>Theft</option><option>Physical Assault</option><option>Iba pa</option></select>
    </div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Incident Date *</div><input class="form-input" type="date" id="inc-date"/></div>
      <div class="form-group"><div class="form-label">Time</div><input class="form-input" type="time" id="inc-time"/></div>
    </div>
    <div class="form-group"><div class="form-label">Location *</div><input class="form-input" id="inc-location" placeholder="Purok, Street..."/></div>
    <div class="form-group"><div class="form-label">Complainant / Reporter</div><input class="form-input" id="inc-reported" placeholder="Full name of complainant or Anonymous"/></div>
    <div class="form-group"><div class="form-label">Ine-reklamo</div><input class="form-input" id="inc-complainee" placeholder="Buong pangalan ng ine-reklamo"/></div>
    <div class="form-group"><div class="form-label">Severity</div><select class="form-input" id="inc-severity"><option>Low</option><option>Medium</option><option>High</option></select></div>
    <div class="form-group"><div class="form-label">Incident Details *</div><textarea class="form-input" id="inc-details" rows="4" placeholder="Describe what happened..."></textarea></div>
    <div class="form-group">
      <div class="form-label">Attachments <span style="color:var(--text-muted);font-weight:400;">(optional — photos/PDF)</span></div>
      <input class="form-input" type="file" id="inc-attachments" accept="image/*,.pdf" multiple style="padding:6px;cursor:pointer;"/>
      <div id="inc-attachments-preview" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
    </div>
    <input type="hidden" id="inc-edit-id" value=""/>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-incident')">Cancel</button>
      <button class="btn btn-danger" onclick="saveIncident()">🚨 File Report</button>
    </div>
  </div>
</div>

<!-- View Incident Details -->
<div class="modal-overlay" id="modal-view-incident">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">🚨 Detalye ng Incident Report</div>
      <div class="modal-close" onclick="closeModal('modal-view-incident')">✕</div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid var(--border);">
      <span style="font-family:var(--font-mono);font-size:13px;color:var(--blue-400);font-weight:700;" id="view-inc-id">—</span>
      <span style="font-size:13px;font-weight:700;color:var(--text-primary);flex:1;" id="view-inc-type">—</span>
      <span style="font-size:12px;font-weight:700;padding:2px 10px;border-radius:999px;background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);" id="view-inc-severity">—</span>
      <span style="font-size:12px;font-weight:700;padding:2px 10px;border-radius:999px;background:rgba(34,197,94,0.1);border:1px solid rgba(34,197,94,0.25);" id="view-inc-status">—</span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Petsa</div>
        <div style="font-size:13px;color:var(--text-primary);" id="view-inc-date">—</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Lokasyon</div>
        <div style="font-size:13px;color:var(--text-primary);" id="view-inc-loc">—</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Nag-reklamo</div>
        <div style="font-size:13px;color:var(--text-primary);" id="view-inc-reported">—</div>
      </div>
      <div>
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Ine-reklamo</div>
        <div style="font-size:13px;font-weight:700;color:#EF4444;" id="view-inc-complainee">—</div>
      </div>
    </div>
    <div>
      <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;">Detalye / Description</div>
      <div style="font-size:13px;color:var(--text-secondary);line-height:1.6;background:var(--bg-glass);border:1px solid var(--border);border-radius:var(--radius);padding:12px;min-height:60px;white-space:pre-wrap;" id="view-inc-description">—</div>
    </div>
    <div id="view-inc-attachments-wrap" style="display:none;margin-top:14px;">
      <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Mga Attachment</div>
      <div id="view-inc-attachments" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-view-incident')">Isara</button>
      <button class="btn btn-primary" id="view-inc-edit-btn">✏️ I-edit ang Report</button>
    </div>
  </div>
</div>

<!-- Add User -->
<div class="modal-overlay" id="modal-adduser">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="adduser-modal-title">👤 Add New User</div><div class="modal-close" onclick="closeModal('modal-adduser')">✕</div></div>
    <input type="hidden" id="adduser-edit-id"/>
    <div class="form-group"><div class="form-label">Buong Full Name *</div><input class="form-input" id="adduser-name" placeholder="Juan dela Cruz"/></div>
    <div class="form-group"><div class="form-label">Employee ID</div><input class="form-input" id="adduser-empid" placeholder="EMP-001"/></div>
    <div class="form-group"><div class="form-label">Role *</div><select class="form-input" id="adduser-role"><option>Barangay Captain</option><option>Barangay Secretary</option><option>Records Officer</option><option>Barangay Clerk</option><option>Tanod Captain</option><option>Data Encoder</option></select></div>
    <div class="form-group"><div class="form-label">Access Level</div><select class="form-input" id="adduser-access"><option>Full Access</option><option>Records & Certificates</option><option>Certificates Only</option><option>Incidents Only</option><option>View Only</option></select></div>
    <div class="form-group"><div class="form-label">Username</div><input class="form-input" id="adduser-username" placeholder="firstname.lastname"/></div>
    <div class="form-group"><div class="form-label">Password <span id="adduser-pass-label" style="color:var(--text-muted);font-weight:400;">(leave blank to keep current)</span></div><input class="form-input" id="adduser-password" type="password" placeholder="Min. 8 characters"/></div>
    <div class="setting-row"><div><div class="setting-label">Face ID Enrollment</div><div class="setting-sub">Enroll facial recognition after account creation</div></div><div class="toggle on" id="adduser-facetoggle"><div class="toggle-knob"></div></div></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-adduser')">Cancel</button>
      <button class="btn btn-green" id="adduser-save-btn" onclick="saveNewUser()">💾 Create User</button>
    </div>
  </div>
</div>

<!-- Eligibility Checker Modal -->
<div class="modal-overlay" id="modal-eligibility-check">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header"><div class="modal-title">🔍 <span>Eligibility Checker</span></div><div class="modal-close" onclick="closeModal('modal-eligibility-check')">✕</div></div>
    <div class="form-group"><div class="form-label">Full Name ng Residente *</div><select class="form-input" id="elig-resident-select" onchange="elig_onResidentChange()"><option value="">— Select Resident —</option></select></div>
    <div class="form-group"><div class="form-label">Document Type *</div>
      <select class="form-input" id="elig-doc-select">
        <option value="">— Select Document —</option>
        <option value="BC">📄 Barangay Clearance</option><option value="CR">🏠 Certificate of Residency</option>
        <option value="CI">📋 Certificate of Indigency</option><option value="BID">🪪 Barangay ID</option>
        <option value="CTFJ">💼 First Time Jobseeker</option><option value="BBC">🏪 Business Clearance</option>
      </select>
    </div>
    <button class="btn btn-green btn-full" style="margin-bottom:14px;" onclick="runEligibilityCheck()">🔍 Check Eligibility</button>
    <div id="elig-result" style="display:none;"></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-eligibility-check')">Close</button>
      <button class="btn btn-green" id="elig-proceed-btn" style="display:none;" onclick="elig_proceedRequest()">📨 Issue Document</button>
    </div>
  </div>
</div>

<!-- Request Record Detail Modal -->
<div class="modal-overlay" id="modal-rr-detail">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header"><div class="modal-title">📋 <span id="rr-detail-title">Request History</span></div><div class="modal-close" onclick="closeModal('modal-rr-detail')">✕</div></div>
    <div id="rr-detail-content"></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-rr-detail')">Close</button>
      <button class="btn btn-green" onclick="closeModal('modal-rr-detail');openModal('modal-eligibility-check')">🔍 Check Eligibility</button>
    </div>
  </div>
</div>

<!-- Generate Report -->
<div class="modal-overlay" id="modal-report">
  <div class="modal">
    <div class="modal-header"><div class="modal-title">📊 <span>Generate Report</span></div><div class="modal-close" onclick="closeModal('modal-report')">✕</div></div>
    <div class="form-group"><div class="form-label">Report Type</div><select class="form-input"><option>Monthly Summary Report</option><option>Resident Census Report</option><option>Certificate Issuance Report</option><option>Incident Report Summary</option><option>RFID Activity Report</option><option>Audit Trail Report</option></select></div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">From</div><input class="form-input" type="date"/></div>
      <div class="form-group"><div class="form-label">To</div><input class="form-input" type="date"/></div>
    </div>
    <div class="form-group"><div class="form-label">Format</div><select class="form-input"><option>PDF</option><option>Excel (.xlsx)</option><option>CSV</option></select></div>
    <div class="modal-footer">
      <button class="btn" onclick="closeModal('modal-report')">Cancel</button>
      <button class="btn btn-green" onclick="generateReport()">📥 Generate Report</button>
    </div>
  </div>
</div>

<script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
