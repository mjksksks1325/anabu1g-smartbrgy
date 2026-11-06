<?php

use App\Models\Resident;

test('Staff shows a solid rounded orange rail even without menu overflow', function () {
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/government.css');

    expect($styles)->toContain('body[data-personnel-super-admin="false"] #app.admin-interface .sidebar-scroll::-webkit-scrollbar-track,')
        ->toContain('body[data-personnel-super-admin="false"] .side-nav-scroll::-webkit-scrollbar-track,')
        ->toContain('body.civic-settings[data-personnel-super-admin="false"] .staff-settings-navigation::-webkit-scrollbar-track { margin: 4px 0; border: 2px solid var(--staff-sidebar); border-radius: 999px; background: #b45309; }')
        ->toContain('body.civic-settings[data-personnel-super-admin="false"] .staff-settings-navigation { scrollbar-color: #b45309 #b45309; }');
});

test('Super Admin keeps a transparent scrollbar track across personnel layouts', function () {
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/government.css');

    expect($styles)->toContain('body[data-personnel-super-admin="true"] #app.admin-interface .sidebar-scroll::-webkit-scrollbar-track,')
        ->toContain('body[data-personnel-super-admin="true"] .side-nav-scroll::-webkit-scrollbar-track,')
        ->toContain('body.civic-settings[data-personnel-super-admin="true"] .staff-settings-navigation::-webkit-scrollbar-track { background: transparent; }')
        ->toContain('body.civic-settings[data-personnel-super-admin="true"] .staff-settings-navigation { scrollbar-color: #d97706 transparent; }');
});

test('workspace navigation does not outline the entire focused content panel', function () {
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/figma-admin.css');

    expect($styles)->toContain('#app.admin-interface .content[tabindex="-1"]:focus { outline: none; }')
        ->toContain(':where(button, a, input, select, textarea, [role="button"]):focus-visible { outline: 3px solid #0b6ad0;');
});

test('all personnel sidebars use an orange thumb and visible orange track', function (string $file, string $selector) {
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/'.$file);
    preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]+)\}/', $styles, $matches);

    expect($matches[1])->toContain('overflow-y: scroll;')
        ->toContain('scrollbar-gutter: stable;')
        ->toContain('scrollbar-color: #d97706 rgba(217, 119, 6, .4);');
    expect($styles)->toContain('::-webkit-scrollbar-track { border-radius: 999px; background: rgba(217, 119, 6, .4); }')
        ->toContain('::-webkit-scrollbar-button { display: none; width: 0; height: 0; }')
        ->toContain($selector.' { scrollbar-width: auto; scrollbar-color: auto; }');
})->with([
    'workspace' => ['figma-admin.css', '#app.admin-interface .sidebar-scroll'],
    'iot' => ['figma-iot.css', '.side-nav-scroll'],
    'account settings' => ['government.css', 'body.civic-settings .staff-settings-navigation'],
]);

test('dashboard and incident screens use authenticated database endpoints', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain('id="dash-stat-issued"')
        ->toContain('id="incidents-tbody"')
        ->toContain('id="incident-pagination"')
        ->toContain('id="inc-resolution-notes"')
        ->toContain("onclick=\"openModal('modal-hardware-status')\"")
        ->not->toContain('IoT Not Connected')
        ->toContain('<x-staff.nav variant="workspace"')
        ->not->toContain('id="screen-cabinet"')
        ->not->toContain('id="screen-face"');
    // The workspace menu comes from the shared staff navigation component.
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/components/staff/nav.blade.php'))
        ->toContain("'route' => 'staff.rfid-files.index'")
        ->toContain("'route' => 'home', 'external' => true")
        ->toContain('class="nav-item portal-link staff-settings-portal"')
        ->toContain('target="_blank" rel="noopener"');
    expect($adminScript)
        ->toContain("fetch('/staff/dashboard-summary'")
        ->toContain('fetch(`/staff/incidents?${query}`')
        ->toContain("formData.append('_method', 'PATCH')")
        ->toContain('async function archiveIncident(id)')
        ->not->toContain("const newId = 'INC-2025-");
});

test('request eligibility uses server history and a real csv export', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain("route('staff.request-records.export')")
        ->toContain('id="rr-pagination"')
        ->toContain('Needs Standing Review')
        ->not->toContain('With Blotter Record');
    expect($adminScript)
        ->toContain('fetch(`/staff/request-records?${query}`')
        ->toContain("query.set('eligibility', rrCurrentStatusFilter)")
        ->toContain('requestRecordResidents = payload.data || []')
        ->toContain('openEligibilityForRequestResident');
});

test('workspace tables start-align text, end-align numbers and actions, and audit rows share the four column layout', function () {
    $adminStyles = file_get_contents(dirname(__DIR__, 2).'/public/css/figma-admin.css');
    $iotStyles = file_get_contents(dirname(__DIR__, 2).'/public/css/figma-iot.css');
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $client = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($adminStyles)
        ->toContain('.tbl th,')
        ->toContain('#app.admin-interface .tbl td { text-align: start; vertical-align: middle; }')
        ->toContain(':is(#app.admin-interface, .modal-overlay) .tbl[data-num-cols~="3"] :is(th, td):nth-child(3):not([colspan])')
        ->toContain('{ text-align: end; font-variant-numeric: tabular-nums; }')
        ->toContain(':is(#app.admin-interface, .modal-overlay) .tbl[data-actions] :is(th, td):last-child:not([colspan]) { text-align: end; }')
        ->toContain('.tbl td.resident-actions { display: table-cell; }')
        ->not->toContain('#app.admin-interface .tbl td { text-align: center; vertical-align: middle; }')
        ->toContain('.audit-table-head,')
        ->toContain('.audit-log-row {')
        ->toContain('grid-template-columns: minmax(110px, .8fr) minmax(135px, 1fr) minmax(0, 2fr) minmax(110px, 1fr);')
        ->toContain('#app.admin-interface .audit-category-badge { justify-self: start; }')
        ->toContain('@media (min-width: 761px)');
    // Tables outside the migrated workspace keep their alignment.
    expect($iotStyles)->toContain('.main-content table th,')
        ->toContain('.main-content table td { text-align: center; vertical-align: middle; }');
    expect($dashboard)->toContain('class="audit-log-table-scroll"')
        ->toContain('<table class="tbl" data-num-cols="3" data-actions>')
        ->toContain('<table class="tbl voter-table" data-num-cols="3" data-actions>')
        ->toContain('<table class="tbl issued-history-table" data-num-cols="5" data-actions>')
        ->toContain('<table class="tbl incident-table" data-actions>')
        ->not->toContain('grid-template-columns:50px 130px 1fr 200px 90px');
    expect($client)->toContain('<table class="tbl" data-num-cols="3" data-actions><thead><tr><th>Household</th><th>Head / Address</th><th>Members</th><th>Actions</th>')
        ->toContain('<table class="tbl cert-fees-table" data-num-cols="3">');
});

test('every workspace modal has the upper-right X, and only confirmations add a Cancel button', function (string $view, array $confirmations) {
    $markup = file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$view);
    preg_match_all('/<div class="modal-overlay[^"]*"[^>]*id="([\w-]+)"/', $markup, $modals);

    expect($modals[1])->not->toBeEmpty();
    foreach ($modals[1] as $modal) {
        expect(substr_count($markup, 'class="modal-close" onclick="closeModal(\''.$modal.'\')" aria-label="Close">&times;</button>'))->toBe(1, $modal);
        $cancelButtons = preg_match_all('/<button(?=[^>]*class="btn")[^>]*onclick="closeModal\(\''.preg_quote($modal, '/').'\'\)"[^>]*>\s*(Close|Cancel|Isara)\s*<\/button>/', $markup);
        expect($cancelButtons)->toBe(in_array($modal, $confirmations, true) ? 1 : 0, $modal);
    }
})->with([
    'workspace' => ['admin/dashboard.blade.php', ['modal-confirm-action', 'modal-reject-request']],
    'case management' => ['admin/partials/case-management.blade.php', ['modal-lift-restriction']],
]);

test('demographics, voters and certificates KPI cards each use their own color', function (string $grid, int $count, array $expected) {
    $markup = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/figma-admin.css');
    preg_match('/id="'.$grid.'"[^>]*>(.*?)\n\s*<\/div>\n/s', $markup, $section);
    preg_match_all('/class="stat-card demo-kpi (demo-kpi-[a-z]+)"><div class="stat-label">([^<]+)</', $section[1], $cards);

    expect(array_combine($cards[2], $cards[1]))->toMatchArray($expected)
        ->and($cards[1])->toHaveCount($count)
        ->and(array_unique($cards[1]))->toHaveCount($count);
    foreach ($cards[1] as $color) {
        expect($styles)->toContain(":is(#demographics-stats-grid, #voter-stats-grid, #cert-stats-grid) .{$color} { --kpi:");
    }
    expect($styles)->toContain('#app.admin-interface .demo-purok-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }');
})->with([
    'demographics' => ['demographics-stats-grid', 6, ['Total Residents' => 'demo-kpi-green', 'Senior Citizens' => 'demo-kpi-yellow']],
    'voters' => ['voter-stats-grid', 4, ['Lahat ng Botante' => 'demo-kpi-blue', 'SK Lamang' => 'demo-kpi-yellow']],
    'certificates' => ['cert-stats-grid', 4, ['Released Requests' => 'demo-kpi-green', 'Online Requests' => 'demo-kpi-purple']],
]);

test('certificates page uses a stepper, paginated filtered history and one fees modal', function () {
    $markup = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $script = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect(substr_count($markup, '<li class="cert-flow-step">'))->toBe(5)
        ->and($markup)->not->toContain('class="process-steps"')
        ->toContain('<form class="issued-history-filters" role="search" aria-label="Filter issued certificates"')
        ->toContain('id="issued-certificates-pagination"')
        ->toContain('onclick="openCertificateFees()">Certificates &amp; Fees</button>')
        ->toContain('<div class="modal-overlay" id="modal-cert-issue">')
        ->toContain('id="cert-issue-title">Certificates &amp; Fees</div>')
        ->not->toContain('id="modal-cert-fees"')
        ->not->toContain('Available Certificates &amp; Fees')
        ->and(substr_count($markup, 'id="cert-types-list"'))->toBe(1)
        ->and($script)->toContain('<span class="cert-lane-title" style="--lane-color:${lane.color};">')
        ->toContain("function openCertificateFees() {\n  renderCertTypesList();\n  openModal('modal-cert-issue');");
});

test('voter actions sit beside the search field instead of the page title', function () {
    $markup = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    expect($markup)->toMatch('/<div class="search-row voter-search-row">\s*<div class="search-wrap">[^\n]*id="voter-search"[^\n]*\n\s*<select[^\n]*id="voter-purok-filter"[^\n]*\n\s*<button[^\n]*openVoterRegistration\(\)[^\n]*Add Voter<\/button>\n\s*<button[^\n]*openVotersImport\(\)[^\n]*Import CSV<\/button>\n\s*<button[^\n]*exportVoterRegistry\(\)[^\n]*Export List<\/button>\n\s*<\/div>/')
        ->not->toContain('voter-header-actions');
});

test('resident records order the toolbar and filter special groups with one dropdown', function () {
    $markup = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    expect($markup)->toMatch('/<div class="search-row resident-records-toolbar">\s*<div class="search-wrap">.*id="residents-search".*\n\s*<button[^\n]*openAddResident\(\)[^\n]*\n\s*<button[^\n]*openHouseholdManagement\(\)[^\n]*\n\s*<button[^\n]*openHouseholdProfilingImport\(\)[^\n]*\n\s*<button[^\n]*exportResidents\(\)/')
        ->toMatch('/<button type="button" class="status-pill" aria-pressed="false" data-resident-status="archived" onclick="filterResidentStatus\(\'Archived\',this\)">Archived<\/button>\s*<label class="resident-group-select" for="resident-group-filter">/')
        ->toContain('<select class="form-input" id="resident-group-filter" onchange="filterResidentSpecialGroup(this.value)">')
        ->not->toContain('data-resident-group=');
    foreach (Resident::SPECIAL_GROUPS as $group) {
        expect($markup)->toContain("'{$group}' =>");
    }
});

test('staff confirmations use the workspace modal instead of browser dialogs', function () {
    $markup = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    expect($markup)->toContain('<div class="modal-overlay" id="modal-confirm-action">')
        ->toContain('id="res-contact" type="tel" inputmode="tel" autocomplete="tel" maxlength="13"')
        ->not->toContain('res-household-pagination');
    foreach (['admin.js', 'case-management.js', 'household-profiling.js'] as $script) {
        $source = file_get_contents(dirname(__DIR__, 2).'/public/js/'.$script);
        expect(preg_match('/(?<![\w.])(confirm|alert|prompt)\(/', $source))->toBe(0, $script);
    }
});
