<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Barangay Anabu I-G — Administration</title>
<link rel="icon" type="image/jpeg" href="{{ asset('images/anabu-logo.jpg') }}">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
<link rel="stylesheet" href="{{ asset('css/government.css') }}">
</head>
<body class="light-mode">
<div id="loading-bar"></div>
<a href="#screen-dashboard" class="skip-link">Skip to main content</a>
<div id="toast-wrap" role="status" aria-live="polite"></div>

<div id="login-screen" hidden></div>

<!-- MAIN APPLICATION -->
<div id="app" class="admin-interface">
  <!-- TOPBAR -->
  <div class="topbar">
    <button class="mobile-menu-button" type="button" aria-controls="admin-navigation" aria-expanded="false" onclick="toggleNavigation()">Menu</button>
    <div class="topbar-logo">
      <div class="topbar-logo-box"><img src="{{ asset('images/anabu-logo.jpg') }}" alt="Barangay Anabu I-G logo"/></div>
      <div class="topbar-brand">Barangay Anabu I-G <span>RESIDENT INFORMATION &amp; SERVICES</span></div>
    </div>
    <div class="topbar-sep"></div>
    <div class="topbar-status">Staff workspace</div>
    <div class="topbar-right">
      <div class="dark-mode-toggle" id="theme-toggle" onclick="toggleTheme()">
        <span id="theme-icon">🌙</span>
        <span id="theme-label">Dark Mode</span>
      </div>
      <div class="topbar-chip">IoT Not Connected</div>
      <div class="topbar-chip notif-badge-wrap" onclick="toggleNotifPanel()" id="notif-chip" style="cursor:pointer;position:relative;">Notifications <span class="notif-count" id="notif-count">0</span></div>
      <div class="topbar-chip" id="clock-display">--:--:--</div>
      <button type="button" class="topbar-avatar" onclick="doLogout()" title="Log out" aria-label="Log out" aria-haspopup="dialog" aria-controls="logout-dialog">JC</button>
    </div>
  </div>

  <div class="app-body">
    <!-- SIDEBAR -->
    <nav class="sidebar" id="admin-navigation" aria-label="Administration">
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
        <div class="nav-item" data-perm="Records" onclick="showScreen('voters',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          Voter Registry
        </div>
        <div class="nav-item" data-perm="Certificates" onclick="showScreen('certificates',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
          Certificates & Clearances
          <span class="nav-badge" id="cert-nav-badge" style="display:none;">0</span>
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
        <div class="nav-item deferred" aria-disabled="true" title="Deferred until the IoT security design is approved">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
          RFID File Tracking <span class="nav-badge">Planned</span>
        </div>
        <div class="nav-item deferred" aria-disabled="true" title="Deferred until the IoT security design is approved">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="2" width="18" height="20" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="16" x2="21" y2="16"/><circle cx="8" cy="5.5" r="1"/><circle cx="8" cy="12.5" r="1"/><circle cx="8" cy="19" r="1"/></svg>
          Smart Cabinet <span class="nav-badge">Planned</span>
        </div>
        <div class="nav-item" data-perm="QR" onclick="showScreen('qr',this)">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h.01"/></svg>
          QR Verification
        </div>
        <div class="nav-item deferred" aria-disabled="true" title="Deferred until the IoT security design is approved">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M6 20v-2a6 6 0 0 1 12 0v2"/></svg>
          Face Recognition <span class="nav-badge">Planned</span>
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
        <div class="nav-item" onclick="window.open('{{ route('home') }}','_blank')" style="margin-top:8px;border-top:1px solid var(--border-color);padding-top:8px;">
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
    </nav>

    <!-- DASHBOARD -->
    <div class="content active" id="screen-dashboard" tabindex="-1">
      <div class="page-header-row">
        <div class="page-header"><h1><span>Dashboard</span></h1><p>Real-time overview — Barangay Anabu I-G, Imus City</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" onclick="refreshDashboardStats()">⟳ Refresh</button>
        </div>
      </div>
      <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-label">Active Residents</div><div class="stat-value" id="dash-stat-residents">0</div><div class="stat-sub" id="dash-sub-residents">Database records</div><div class="stat-icon">👥</div></div>
        <div class="stat-card green"><div class="stat-label">Issued Certificates</div><div class="stat-value" id="dash-stat-issued">0</div><div class="stat-sub">Verified official records</div><div class="stat-icon">📄</div></div>
        <div class="stat-card amber"><div class="stat-label">Pending Requests</div><div class="stat-value" id="dash-stat-pending">0</div><div class="stat-sub" id="dash-sub-pending">No pending requests</div><div class="stat-icon">📋</div></div>
        <div class="stat-card red"><div class="stat-label">Incident Reports</div><div class="stat-value" id="dash-stat-incidents">0</div><div class="stat-sub" id="dash-sub-incidents">No incidents filed</div><div class="stat-icon">🚨</div></div>
      </div>
      <div class="two-col">
        <div class="card">
          <div class="card-header"><div><div class="card-title">Certificate Requests</div><div class="card-sub">Real-time na bilang ng bawat uri ng kahilingan</div></div><span class="badge badge-green">Live</span></div>
          <div id="dash-chart"></div>
        </div>
        <div class="card">
          <div class="card-header"><div><div class="card-title">Recent Activity</div><div class="card-sub">Latest recorded activity</div></div><span class="badge badge-green">Live</span></div>
          <div id="dash-recent-activity">
            <div style="text-align:center;color:var(--text-muted);font-size:11px;padding:18px 0;">No recent activity.</div>
          </div>
        </div>
      </div>
      <div class="three-col">
        <div class="card">
          <div class="card-header"><div class="card-title">Purok Breakdown</div></div>
          <div style="display:flex;flex-direction:column;gap:9px;" id="dash-purok-breakdown"></div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">System Status</div></div>
          <div class="sys-row"><div class="sys-name">Document services</div><a class="btn btn-xs" href="{{ route('admin.document-requests.index') }}">View requests</a></div>
          <div class="sys-row"><div class="sys-name">Account security</div><a class="btn btn-xs" href="{{ route('security.edit') }}">Manage</a></div>
          <div class="sys-row"><div class="sys-name">Hardware integrations</div><span class="badge badge-gray">Not connected</span></div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">Quick Actions</div></div>
          <div style="display:flex;flex-direction:column;gap:7px;">
            <button class="btn btn-green btn-full" onclick="showScreen('records',findNavItem('records'));openAddResident()">➕ Register New Resident</button>
            <button class="btn btn-primary btn-full" onclick="showScreen('certificates',findNavItem('certificates'))">📄 Issue Certificate</button>
            <button class="btn btn-full" onclick="showScreen('incidents',findNavItem('incidents'))">🚨 File Incident Report</button>
          </div>
        </div>
      </div>
    </div>

    <!-- DEMOGRAPHICS -->
    <div class="content" id="screen-demographics">
      <div class="page-header-row">
        <div class="page-header"><h1>Population <span>Demographics</span></h1><p>Census data — Barangay Anabu I-G, Imus City</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-sm" onclick="refreshDemographics()">⟳ Refresh</button>
          <button class="btn btn-green btn-sm" onclick="window.location.href='{{ route('admin.residents.export') }}'">📥 Export Census</button>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-label">Total Population</div><div class="stat-value counter" id="demo-stat-total" data-target="0">0</div><div class="stat-sub">Based on registered residents</div><div class="stat-icon">🏘️</div></div>
        <div class="stat-card green"><div class="stat-label">Male</div><div class="stat-value counter" id="demo-stat-male" data-target="0">0</div><div class="stat-sub" id="demo-sub-male">—</div><div class="stat-icon">👨</div></div>
        <div class="stat-card blue"><div class="stat-label">Female</div><div class="stat-value counter" id="demo-stat-female" data-target="0">0</div><div class="stat-sub" id="demo-sub-female">—</div><div class="stat-icon">👩</div></div>
        <div class="stat-card amber"><div class="stat-label">Senior Citizens</div><div class="stat-value counter" id="demo-stat-households" data-target="0">0</div><div class="stat-sub" id="demo-sub-households">Age 60 and above</div><div class="stat-icon">👴</div></div>
      </div>

      <!-- Purok Population Cards -->
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Population per Purok / Subdivision</div><div class="card-sub">Resident count per zone including special group breakdown</div></div>
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
          <div class="card-header"><div class="card-title">Age Distribution</div><div class="card-sub">Population breakdown by age group</div></div>
          <div style="display:flex;flex-direction:column;gap:12px;" id="age-distribution-list"></div>
        </div>

        <!-- Special Groups -->
        <div class="card">
          <div class="card-header"><div class="card-title">Special Groups</div><div class="card-sub">Classified sectors of the community</div></div>
          <div id="special-groups-container"></div>
        </div>
      </div>

      <!-- Senior Citizens Register -->
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Senior Citizens Register (60+)</div><div class="card-sub">Auto-updated when a resident reaches age 60 based on date of birth</div></div>
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
        <button class="btn btn-primary btn-sm" onclick="exportResidents()">Export CSV</button>
      </div>
      <div class="status-bar">
        <div class="status-pill active" onclick="filterResidentStatus('',this)">All</div>
        <div class="status-pill" onclick="filterResidentStatus('Active',this)">Active</div>
        <div class="status-pill" onclick="filterResidentStatus('Inactive',this)">Inactive</div>
        <div class="status-pill" onclick="filterResidentStatus('Senior',this)">👴 Senior Citizens</div>
        <div class="status-pill" onclick="filterResidentStatus('Archived',this)">Archived</div>
      </div>
      <div class="card">
        <div class="table-scroll"><table class="tbl">
          <thead><tr><th>Resident ID</th><th>Full Name</th><th>Age</th><th>Purok</th><th>Gender</th><th>Civil Status</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="records-tbody"></tbody>
        </table></div>
        <div class="resident-pagination" id="resident-pagination"></div>
      </div>
    </div>

    <!-- VOTER REGISTRY -->
    <div class="content" id="screen-voters">
      <div class="page-header-row">
        <div class="page-header"><h1>Mga <span>Botante</span></h1><p>Mga rehistradong botante ayon sa purok at age eligibility</p></div>
        <div class="voter-header-actions">
          <button class="btn btn-green" onclick="openVoterRegistration()">Add Voter</button>
          <button class="btn btn-primary" onclick="exportVoterRegistry()">Export List</button>
        </div>
      </div>

      <div class="voter-security-note">
        <strong>Age eligibility guide:</strong> Edad 15–17 ay SK voter lamang; edad 18–30 ay maaaring bumoto sa SK at regular elections; edad 31 pataas ay regular voter lamang. Kailangan pa rin ang opisyal na voter registration.
      </div>

      <div class="stats-grid stats-grid-4 voter-stats">
        <div class="stat-card blue"><div class="stat-label">Lahat ng Botante</div><div class="stat-value" id="voter-stat-total">0</div><div class="stat-sub">Aktibong rehistrado</div><div class="stat-icon">🗳️</div></div>
        <div class="stat-card amber"><div class="stat-label">SK Lamang</div><div class="stat-value" id="voter-stat-sk-only">0</div><div class="stat-sub">Edad 15–17</div><div class="stat-icon">🧑</div></div>
        <div class="stat-card green"><div class="stat-label">SK at Regular</div><div class="stat-value" id="voter-stat-sk-regular">0</div><div class="stat-sub">Edad 18–30</div><div class="stat-icon">✅</div></div>
        <div class="stat-card blue"><div class="stat-label">Regular Lamang</div><div class="stat-value" id="voter-stat-regular-only">0</div><div class="stat-sub">Edad 31 pataas</div><div class="stat-icon">👥</div></div>
      </div>

      <div class="search-row voter-search-row">
        <div class="search-wrap"><span class="si">🔍</span><input class="search-input" id="voter-search" placeholder="Maghanap ng pangalan o resident ID..." oninput="filterVoterRegistry()"/></div>
        <select class="form-input voter-purok-filter" id="voter-purok-filter" onchange="filterVoterPurok(this.value)"><option value="">Lahat ng Purok</option></select>
      </div>
      <div class="status-pills voter-filter-pills">
        <div class="status-pill active" onclick="filterVoterEligibility('',this)">Lahat ng Edad</div>
        <div class="status-pill" onclick="filterVoterEligibility('sk_only',this)">SK Lamang (15–17)</div>
        <div class="status-pill" onclick="filterVoterEligibility('sk_and_regular',this)">SK at Regular (18–30)</div>
        <div class="status-pill" onclick="filterVoterEligibility('regular_only',this)">Regular Lamang (31+)</div>
      </div>

      <div class="card voter-table-card">
        <div class="table-scroll"><table class="tbl voter-table">
          <thead><tr><th>Resident ID</th><th>Buong Pangalan</th><th>Edad</th><th>Purok</th><th>Maaaring Iboto</th><th>Precinct</th><th>Cluster</th></tr></thead>
          <tbody id="voter-registry-tbody"><tr><td colspan="7" class="resident-table-message">Loading voter list...</td></tr></tbody>
        </table></div>
        <div class="resident-pagination" id="voter-pagination"></div>
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
        <div class="card-header" style="margin-bottom:10px;"><div class="card-title">Certificate Process Flow</div></div>
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
        <div class="stat-card green"><div class="stat-label">Released Requests</div><div class="stat-value" id="cert-today">0</div><div class="stat-sub">Released / Completed</div><div class="stat-icon">📄</div></div>
        <div class="stat-card amber"><div class="stat-label">Pending / Processing</div><div class="stat-value" id="cert-pending">0</div><div class="stat-sub">In progress</div><div class="stat-icon">⏳</div></div>
        <div class="stat-card blue"><div class="stat-label">Ready to Print</div><div class="stat-value" id="cert-ready">0</div><div class="stat-sub">Awaiting release</div><div class="stat-icon">🖨️</div></div>
        <div class="stat-card green"><div class="stat-label">Online Requests</div><div class="stat-value" id="cert-online">0</div><div class="stat-sub">Via public portal</div><div class="stat-icon">🌐</div></div>
      </div>

      <!-- Kanban Board -->
      <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
          <div><div class="card-title">Request Board</div><div class="card-sub">Status tracking for document requests</div></div>
          <div style="display:flex;gap:6px;">
            <div class="search-wrap" style="width:200px;"><span class="si">🔍</span><input class="search-input" id="cert-search" placeholder="Search requests..." oninput="filterCertBoard(this.value)"/></div>
          </div>
        </div>
        <div class="cert-kanban" id="cert-kanban-board"></div>
      </div>

      <!-- Issued Certificate History -->
      <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
          <div><div class="card-title">Issued Certificate History</div><div class="card-sub">Latest 100 issued documents — available for reprint and verification</div></div>
          <button class="btn btn-sm" type="button" onclick="refreshIssuedCertificates()">⟳ Refresh</button>
        </div>
        <div style="overflow-x:auto;">
          <table class="tbl">
            <thead><tr><th>Certificate No.</th><th>Resident</th><th>Document</th><th>Source</th><th>Fee</th><th>Date Issued</th><th>Issued By</th><th>Actions</th></tr></thead>
            <tbody id="issued-certificates-tbody">
              <tr><td colspan="8" style="text-align:center;color:var(--text-muted);">Loading issued certificates...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Certificate Types Reference -->
      <div class="two-col">
        <div class="card">
          <div class="card-header"><div class="card-title">Available Certificates & Fees</div></div>
          <div id="cert-types-list" style="display:flex;flex-direction:column;gap:7px;"></div>
        </div>
        <div class="card card-green">
          <div class="card-header"><div class="card-title">QR Verification System</div></div>
          <div style="font-size:12px;color:var(--text-secondary);line-height:1.8;">
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>📱</span><span>Every issued document gets a unique embedded QR code</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>🔒</span><span>QR opens a unique, database-backed public verification page</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>✅</span><span>3rd parties (employers, schools) can scan to verify authenticity</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;"><span>🚫</span><span>Unknown or altered verification codes are rejected</span></div>
            <div style="display:flex;gap:8px;align-items:flex-start;"><span>📊</span><span>Manual lookup checks the live certificate database</span></div>
          </div>
          <button class="btn btn-green btn-full" style="margin-top:12px;" onclick="showScreen('qr',findNavItem('qr'))">📷 Open QR Scanner →</button>
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
      <div class="search-row voter-search-row">
        <div class="search-wrap"><span class="si">🔍</span><input class="search-input" id="incident-search" placeholder="Search ID, uri, lokasyon, o pangalan..." oninput="filterIncidents()"/></div>
        <select class="form-input voter-purok-filter" id="incident-status-filter" onchange="filterIncidents()"><option value="">Lahat ng Status</option><option value="pending">Pending</option><option value="under_investigation">Under Investigation</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select>
        <select class="form-input voter-purok-filter" id="incident-severity-filter" onchange="filterIncidents()"><option value="">Lahat ng Severity</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select>
      </div>
      <div class="card">
        <div class="table-scroll"><table class="tbl incident-table">
          <thead><tr><th>Incident ID</th><th>Uri</th><th>Lokasyon</th><th>Petsa</th><th>Nag-report</th><th>Ine-reklamo</th><th>Severity</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="incidents-tbody"><tr><td colspan="9" class="resident-table-message">Loading incident reports...</td></tr></tbody>
        </table></div>
        <div class="resident-pagination" id="incident-pagination"></div>
      </div>
    </div>

    <!-- REQUEST RECORDS -->
    <div class="content" id="screen-request-records">
      <div class="page-header-row">
        <div class="page-header"><h1>Request <span>Eligibility</span></h1><p>Complete document request history per resident — eligibility tracking & audit trail</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" onclick="openModal('modal-eligibility-check')">🔍 Check Eligibility</button>
          <a class="btn btn-green btn-sm" href="{{ route('admin.request-records.export') }}">📥 Export</a>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid stats-grid-4" style="margin-bottom:16px;">
        <div class="stat-card green"><div class="stat-label">Total Requests</div><div class="stat-value" id="rr-total">0</div><div class="stat-icon">📋</div></div>
        <div class="stat-card blue"><div class="stat-label">Completed</div><div class="stat-value" id="rr-completed">0</div><div class="stat-icon">✅</div></div>
        <div class="stat-card amber"><div class="stat-label">Ineligible / Blocked</div><div class="stat-value" id="rr-blocked">0</div><div class="stat-icon">🚫</div></div>
        <div class="stat-card red"><div class="stat-label">Needs Standing Review</div><div class="stat-value" id="rr-review">0</div><div class="stat-icon">⚠️</div></div>
      </div>

      <!-- Eligibility Rules Reference -->
      <div class="card" style="margin-bottom:14px;">
        <div class="card-header" onclick="toggleEligRules()" style="cursor:pointer;">
          <div><div class="card-title">Eligibility Rules Reference</div><div class="card-sub">Click to expand — rules applied per document type</div></div>
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
          <button class="btn btn-sm rr-filter-btn" onclick="filterRRStatus('eligible',this)">✅ Eligible</button>
          <button class="btn btn-sm rr-filter-btn" onclick="filterRRStatus('ineligible',this)">🚫 Needs Review</button>
        </div>
      </div>
      <div id="rr-resident-list" style="display:flex;flex-direction:column;gap:10px;margin-top:4px;"></div>
      <div class="resident-pagination" id="rr-pagination"></div>
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
            🚫 Unknown or altered verification codes <strong style="color:#EF4444;">cannot pass</strong> this verification.
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
              <div class="card-header"><div class="card-title">📷 Document QR Scanner</div><span class="badge badge-green">Phone Ready</span></div>
              <div class="qr-scan-area" id="qr-scan-area" onclick="showToast('Scan the printed QR using any phone camera. It opens the public verification page.','green')">
                <div class="qr-icon">▣</div>
                <div style="font-size:13px;font-weight:600;color:var(--green-500);" id="qr-scan-label">Scan with any phone camera</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">The QR opens the live public verification record—no app or login needed</div>
              </div>
            </div>
            <div class="card">
              <div class="card-header"><div class="card-title">🔍 Manual Code Lookup</div></div>
              <div style="font-size:12px;color:var(--text-secondary);margin-bottom:10px;">Enter the verification code printed on an issued certificate.</div>
              <div class="form-group">
                <div class="form-label">Certificate Verification Code</div>
                <div style="display:flex;gap:8px;">
                  <input class="form-input" id="manual-code" placeholder="e.g. A1B2C3D4E5F6G7H8" style="flex:1;"/>
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
                🔒 QR points to a unique live database record.<br>
                📋 Each verification code is unique per issuance.<br>
                ✅ Certificate details are loaded from the server.<br>
                🚫 Unknown or altered codes return “not found.”<br>
                📱 Verification works with a normal phone camera.
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
              <div class="qr-scan-area" id="qr-status-area" style="border-color:rgba(42,126,211,.4);background:rgba(42,126,211,.06);" onclick="document.getElementById('status-code').focus()">
                <div class="qr-icon" style="color:var(--blue-400);">▣</div>
                <div style="font-size:13px;font-weight:600;color:var(--blue-400);" id="qr-status-label">Check your request reference code</div>
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
        <div class="page-header"><h1>Audit <span>Log</span></h1><p>Latest 500 successful administrative changes. Actions before audit activation are not included.</p></div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span class="live-badge"><span class="pulse-dot"></span>Live Feed</span>
        </div>
      </div>

      <!-- Audit Stats -->
      <div class="audit-stats">
        <div class="audit-stat"><div class="audit-stat-val" id="astat-total">0</div><div class="audit-stat-label">Total Events</div></div>
        <div class="audit-stat"><div class="audit-stat-val" style="color:var(--blue-400);" id="astat-records">0</div><div class="audit-stat-label">Record Changes</div></div>
        <div class="audit-stat"><div class="audit-stat-val" style="color:#F59E0B;" id="astat-certs">0</div><div class="audit-stat-label">Certificates</div></div>
        <div class="audit-stat"><div class="audit-stat-val" style="color:#EF4444;" id="astat-security">0</div><div class="audit-stat-label">Account Changes</div></div>
      </div>

      <div class="card">
        <div class="card-header">
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <input class="form-input" type="date" id="audit-date-filter" aria-label="Filter audit events by date" onchange="renderAuditLog()" style="width:165px;">
            <div class="search-wrap" style="width:220px;"><span class="si">🔍</span><input class="search-input" id="audit-search" placeholder="Search logs..." oninput="filterAuditLog()"/></div>
            <div class="audit-filter-bar" style="margin-bottom:0;">
              <button class="audit-type-btn active" onclick="filterAuditType('all',this)">All</button>
              <button class="audit-type-btn" onclick="filterAuditType('cert',this)">📄 Certificates</button>
              <button class="audit-type-btn" onclick="filterAuditType('record',this)">🧑 Records</button>
              <button class="audit-type-btn" onclick="filterAuditType('incident',this)">🚨 Incidents</button>
              <button class="audit-type-btn" onclick="filterAuditType('security',this)">⚠️ Security</button>
            </div>
          </div>
        </div>
        <!-- Log Table Header -->
        <div class="audit-table-head" style="display:grid;grid-template-columns:50px 130px 1fr 200px 90px;gap:12px;padding:7px 14px;border-bottom:1px solid var(--border);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);background:var(--table-header);border-radius:var(--radius-sm);">
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
        <div class="page-header"><h1>User <span>Management</span></h1><p>Manage authorized accounts and access to barangay records.</p></div>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-primary btn-sm" onclick="exportUsers()">📥 Export</button>
          <button class="btn btn-green" onclick="openAddUser()">➕ Add New User</button>
        </div>
      </div>

      <div class="card"><div class="card-title">Authorized personnel only</div><p class="card-sub">Administrators manage accounts. Staff can manage records and services. View-only accounts have no access to administrative records. Suspend an account to revoke access while preserving its history.</p></div>
      <div class="stats-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
        <div class="stat-card green"><div class="stat-label">Active users</div><div class="stat-value" id="usr-active">0</div></div>
        <div class="stat-card amber"><div class="stat-label">Suspended users</div><div class="stat-value" id="usr-suspended">0</div></div>
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

    </div>

    <!-- SETTINGS -->
    <div class="content" id="screen-settings">
      <div class="page-header"><h1>Account &amp; <span>Settings</span></h1><p>Manage your account, security, and display preferences.</p></div>
      <div class="two-col">
        <div class="card"><div class="card-header"><div class="card-title">Account and security</div></div>
          <div class="setting-row"><div><div class="setting-label">Profile information</div><div class="setting-sub">Update your name and email address.</div></div><a class="btn btn-primary" href="{{ route('profile.edit') }}">Edit profile</a></div>
          <div class="setting-row"><div><div class="setting-label">Password and two-factor authentication</div><div class="setting-sub">Manage your password, authenticator, and recovery codes.</div></div><a class="btn" href="{{ route('security.edit') }}">Security</a></div>
          <div class="setting-row"><div><div class="setting-label">Display theme</div><div class="setting-sub">Saved on this browser.</div></div><button class="btn" onclick="toggleTheme()">Change theme</button></div>
        </div>
        <div class="card"><div class="card-header"><div class="card-title">Barangay services</div></div>
          <div class="setting-row"><div><div class="setting-label">Barangay Anabu I-G</div><div class="setting-sub">City of Imus, Cavite</div></div></div>
          <div class="setting-row"><div><div class="setting-label">Online document requests</div><div class="setting-sub">Submit requests and track their progress.</div></div><a class="btn" href="{{ route('home') }}">Open portal</a></div>
          <div class="setting-row"><div><div class="setting-label">Hardware integrations</div><div class="setting-sub">RFID, smart cabinet, and facial recognition are not connected.</div></div><span class="badge badge-gray">Not connected</span></div>
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
      <button class="btn btn-xs" onclick="toggleNotifPanel();showScreen('audit',findNavItem('audit'))">View all in Audit Log</button>
    </div>
  </div>
  </div><!-- end app-body -->
</div><!-- end app -->

<!-- MODALS -->
<dialog class="modal logout-dialog" id="logout-dialog" aria-labelledby="logout-title" aria-describedby="logout-description" data-logout-url="{{ route('logout') }}" data-login-url="{{ route('login') }}" oncancel="if (logoutPending) event.preventDefault()">
  <button type="button" class="modal-close logout-close" aria-label="Cancel logout" onclick="cancelLogout()">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg>
  </button>
  <div class="logout-symbol" aria-hidden="true">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4M14 8l4 4-4 4M8 12h13"/></svg>
  </div>
  <p class="logout-eyebrow">Staff workspace</p>
  <h2 id="logout-title">Log out of SmartBrgy?</h2>
  <p id="logout-description">You're about to end your staff session. You can sign in again anytime.</p>
  <p class="logout-error" id="logout-error" role="alert" hidden></p>
  <div class="logout-actions">
    <button type="button" class="btn logout-cancel" onclick="cancelLogout()" autofocus>Cancel</button>
    <button type="button" class="btn logout-confirm" id="logout-confirm" onclick="confirmLogout()">Log out</button>
  </div>
</dialog>

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

<!-- Print & Release Documents -->
<div class="modal-overlay" id="modal-print-release">
  <div class="modal">

    <div class="modal-header">
      <div>
        <div class="modal-title">🖨️ Print & Release Certificate</div>
        <div class="modal-sub">Review certificate details before issuance.</div>
      </div>

      <button class="modal-close" onclick="closeModal('modal-print-release')">×</button>
    </div>

    <input type="hidden" id="print-document-request-id">

    <div class="form-group">
      <label>CERTIFICATE TYPE</label>
      <input
        type="text"
        id="print-certificate-type"
        class="form-input"
        readonly
      >
    </div>

    <div class="form-group">
      <label>FULL NAME NG RESIDENT</label>
      <input
        type="text"
        id="print-resident-name"
        class="form-input"
        readonly
      >
    </div>

    <div class="form-group">
      <label>PURPOSE / REASON</label>
      <input
        type="text"
        id="print-purpose"
        class="form-input"
        readonly
      >
    </div>

    <div class="form-group">
      <label>PAYMENT</label>
      <input
        type="text"
        id="print-amount-paid"
        class="form-input"
        readonly
      >
    </div>

    <div style="background:var(--green-dim);border:1px solid var(--border-green);border-radius:var(--radius-sm);padding:10px;font-size:11.5px;color:var(--text-secondary);margin-bottom:14px;">
      ✅ QR verification code will be generated automatically after issuance.
    </div>

    <div class="modal-footer">
      <button
        type="button"
        class="btn"
        onclick="closeModal('modal-print-release')"
      >
        Cancel
      </button>

      <button
        type="button"
        class="btn btn-green"
        id="print-release-submit"
        onclick="confirmPrintRelease()"
      >
        🖨️ Issue & Print
      </button>
    </div>

  </div>
</div>
<!-- Add/Edit Resident -->
<div class="modal-overlay" id="modal-resident">
  <div class="modal">
    <div class="modal-header"><div class="modal-title" id="modal-resident-title">➕ <span>I-register ang Bagong Resident</span></div><div class="modal-close" onclick="closeModal('modal-resident')">✕</div></div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Last Name *</div><input class="form-input" id="res-lastname" placeholder="Santos"/></div>
      <div class="form-group"><div class="form-label">First Name *</div><input class="form-input" id="res-name" placeholder="Maria"/></div>
    </div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Middle Name</div><input class="form-input" id="res-middlename" placeholder="Reyes"/></div>
      <div class="form-group"><div class="form-label">Suffix</div><input class="form-input" id="res-suffix" placeholder="Jr."/></div>
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
    <div class="form-row resident-admin-fields">
      <div class="form-group"><div class="form-label">Record Status</div><select class="form-input" id="res-status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <label class="resident-standing"><input type="checkbox" id="res-good-standing" checked/> Resident is in good standing</label>
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

<!-- Add Voter -->
<div class="modal-overlay" id="modal-voter-registration">
  <div class="modal voter-modal">
    <div class="modal-header">
      <div><div class="modal-title">Add Voter</div><div class="modal-sub">Pumili ng eligible resident na wala pang voter registration.</div></div>
      <button type="button" class="modal-close" onclick="closeModal('modal-voter-registration')">×</button>
    </div>
    <div class="voter-privacy-panel"><strong>Eligibility:</strong> Edad 15–17 ay SK lamang, 18–30 ay SK at regular, at 31 pataas ay regular voter.</div>
    <div class="form-group"><div class="form-label">Resident *</div><select class="form-input" id="voter-resident-id"><option value="">Pumili ng resident edad 15 pataas...</option></select></div>
    <div class="form-group"><div class="form-label">COMELEC / Voter Number *</div><input class="form-input" id="voter-comelec-number" maxlength="30" autocomplete="off" placeholder="Halimbawa: 1234-5678-9012"/></div>
    <div class="form-row">
      <div class="form-group"><div class="form-label">Precinct Number *</div><input class="form-input" id="voter-precinct" maxlength="50" placeholder="Halimbawa: 0123A"/></div>
      <div class="form-group"><div class="form-label">Cluster Number *</div><input class="form-input" id="voter-cluster" maxlength="50" placeholder="Halimbawa: 045"/></div>
    </div>
    <div class="form-group"><div class="form-label">Registration Date *</div><input class="form-input" type="date" id="voter-registration-date"/></div>
    <div class="modal-footer">
      <button type="button" class="btn" onclick="closeModal('modal-voter-registration')">Cancel</button>
      <button type="button" class="btn btn-green" id="voter-save-button" onclick="saveVoterRegistration()">Save Voter</button>
    </div>
  </div>
</div>

<!-- Reject Online Request -->
<div class="modal-overlay" id="modal-reject-request">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <div><div class="modal-title">Reject Online Request</div><div class="modal-sub" id="reject-request-code">The reason will be saved in the request audit record.</div></div>
      <button type="button" class="modal-close" onclick="closeModal('modal-reject-request')">×</button>
    </div>
    <div class="form-group"><div class="form-label">Reason for rejection *</div><textarea class="form-input" id="reject-request-reason" rows="5" minlength="10" maxlength="1000" placeholder="Explain clearly why this request cannot be processed..."></textarea><div class="field-help">Minimum 10 characters. The resident can use this reason to correct their application.</div></div>
    <input type="hidden" id="reject-request-id"/>
    <div class="modal-footer">
      <button type="button" class="btn" onclick="closeModal('modal-reject-request')">Cancel</button>
      <button type="button" class="btn btn-danger" id="reject-request-submit" onclick="confirmRejectRequest()">Reject Request</button>
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
        <div style="font-size:10px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">Email Address</div>
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
    <div class="modal-header"><div class="modal-title">📄 <span>Issue Certificate</span></div><div class="modal-close" onclick="closeModal('modal-cert-issue')">✕</div></div>
    <form id="manual-certificate-form" onsubmit="issueManualCertificate(event)">
      <div class="form-group"><div class="form-label">Certificate Type</div>
        <select class="form-input" id="manual-certificate-type" required onchange="updateManualCertificateFee()">
          <option value="Barangay Clearance">Barangay Clearance</option>
          <option value="Certificate of Residency">Certificate of Residency</option>
          <option value="Certificate of Indigency">Certificate of Indigency</option>
          <option value="Barangay ID">Barangay ID</option>
          <option value="First Time Jobseeker">First Time Jobseeker</option>
          <option value="Business Clearance">Business Clearance</option>
        </select>
      </div>
      <div class="form-group"><div class="form-label">Linked Resident Record (recommended)</div>
        <select class="form-input" id="manual-resident-id" onchange="selectManualResident()"><option value="">Manual / legacy issuance</option></select>
      </div>
      <div class="form-group"><div class="form-label">Full Name ng Resident</div><input class="form-input" id="manual-resident-name" maxlength="255" required placeholder="Type the resident's full name..."/></div>
      <div class="form-group"><div class="form-label">Address ng Resident</div><input class="form-input" id="manual-resident-address" maxlength="1000" row="2" required placeholder="Type the resident's address..."/></div>
      <div class="form-group"><div class="form-label">Purpose / Reason</div><input class="form-input" id="manual-certificate-purpose" maxlength="500" placeholder="Employment, Loan, Scholarship..."/></div>
      <div class="form-group"><div class="form-label">Payment</div><input class="form-input" id="manual-certificate-fee" value="PHP 50.00" readonly/></div>
      <div style="background:var(--green-dim);border:1px solid var(--border-green);border-radius:var(--radius-sm);padding:10px;font-size:11.5px;color:var(--text-secondary);margin-bottom:14px;">✅ The official fee is set by the server. A unique QR verification code is generated automatically.</div>
      <div class="modal-footer">
        <button type="button" class="btn" onclick="closeModal('modal-cert-issue')">Cancel</button>
        <button type="submit" class="btn btn-green" id="manual-certificate-submit">🖨️ Issue & Print</button>
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
      <button class="btn btn-green" onclick="closeModal('modal-qr-verify');showScreen('certificates',findNavItem('certificates'))">✅ Manage certificate</button>
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
    <div class="form-group"><div class="form-label">Severity</div><select class="form-input" id="inc-severity"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option></select></div>
    <div class="form-group" id="inc-status-group" style="display:none;"><div class="form-label">Status *</div><select class="form-input" id="inc-status" onchange="toggleIncidentResolution()"><option value="pending">Pending</option><option value="under_investigation">Under Investigation</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select></div>
    <div class="form-group" id="inc-resolution-group" style="display:none;"><div class="form-label">Resolution Notes *</div><textarea class="form-input" id="inc-resolution-notes" rows="3" placeholder="Ilagay ang resolution o kasunduan..."></textarea></div>
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
  <form class="modal" onsubmit="event.preventDefault();saveNewUser()">
    <div class="modal-header"><div class="modal-title" id="adduser-modal-title">New user</div><button type="button" class="modal-close" onclick="closeModal('modal-adduser')" aria-label="Close user form">&times;</button></div>
    <input type="hidden" id="adduser-edit-id">
    <div class="form-group"><label for="adduser-name" class="form-label">Full name</label><input class="form-input" id="adduser-name" required maxlength="255" autocomplete="name"></div>
    <div class="form-group"><label for="adduser-email" class="form-label">Email address</label><input class="form-input" id="adduser-email" type="email" required maxlength="255" autocomplete="email"></div>
    <div class="form-group"><label for="adduser-role" class="form-label">Access role</label><select class="form-input" id="adduser-role"><option value="staff">Staff</option><option value="admin">Administrator</option><option value="viewer">View only (no administrative records)</option></select></div>
    <div class="form-group"><label for="adduser-status" class="form-label">Account status</label><select class="form-input" id="adduser-status"><option value="active">Active</option><option value="suspended">Suspended</option></select></div>
    <div class="form-group"><label for="adduser-password" class="form-label">Password</label><input class="form-input" id="adduser-password" type="password" minlength="12" autocomplete="new-password"><p class="card-sub" id="adduser-pass-label">At least 12 characters. Leave blank when editing to keep the current password.</p></div>
    <div class="modal-footer"><button class="btn" type="button" onclick="closeModal('modal-adduser')">Cancel</button><button class="btn btn-green" id="adduser-save-btn" type="submit">Save account</button></div>
  </form>
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
        <option value="CTFJ">💼 First Time Jobseeker Certificate</option><option value="BBC">🏪 Business Clearance</option>
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

<script>
    window.LARAVEL_DOCUMENT_REQUESTS = @json($requests);
    window.AUTHENTICATED_USER = {{ Illuminate\Support\Js::from(auth()->user()->only(['id', 'name', 'role'])) }};
</script>

<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
</body>
</html>
