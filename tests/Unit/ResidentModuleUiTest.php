<?php

test('resident dashboard uses the persistent API and recoverable archive controls', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain('id="app" class="admin-interface"')
        ->toContain('id="resident-pagination"')
        ->toContain("filterResidentStatus('Archived',this)")
        ->toContain('id="res-middlename"')
        ->toContain('id="res-good-standing"');
    expect($dashboard)->toContain('onclick="exportResidents()"');
    expect($dashboard)->toContain('onclick="refreshDemographics()"');
    expect($adminScript)
        ->toContain('fetch(`/admin/residents?${query}`')
        ->toContain("method = resident ? 'PATCH' : 'POST'")
        ->toContain("changeResidentArchiveState(`/admin/residents/\${resident.databaseId}`, 'DELETE')")
        ->toContain('async function refreshDemographics()')
        ->toContain("if (id === 'demographics') void loadPuroks();")
        ->toContain("await loadResidents(resident ? residentCurrentPage : 1);\n    await loadPuroks();")
        ->toContain("await loadResidents(1);\n    await loadPuroks();")
        ->toContain('escapeText(resident.name)')
        ->not->toContain('Admin@1234!');
});

test('resident interface includes mobile table and modal handling', function () {
    $adminStyles = file_get_contents(dirname(__DIR__, 2).'/public/css/admin.css');

    expect($adminStyles)
        ->toContain('/* Professional admin shell */')
        ->toContain('.admin-interface .sidebar { width:238px; }')
        ->toContain('--bg-base:#d9e2ea;')
        ->toContain('--border:#9eafbf;')
        ->toContain('border-bottom-color:#cbd6e0;')
        ->toContain('body.light-mode #app.admin-interface .sidebar {')
        ->toContain('background:#edf2f6;')
        ->toContain('.admin-interface .stats-grid { grid-template-columns:1fr; }')
        ->toContain('.table-scroll { overflow-x:auto;')
        ->toContain('@media (max-width: 700px)')
        ->toContain('#screen-records .tbl { min-width:860px;')
        ->toContain('.resident-detail-grid { grid-template-columns:1fr; }');
});

test('authenticated admin avoids legacy background animations and uses cacheable assets', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain("filemtime(public_path('css/admin.css'))")
        ->toContain("filemtime(public_path('js/admin.js'))")
        ->not->toContain('?v={{ time() }}');
    expect($adminScript)
        ->toContain('if (window.AUTHENTICATED_USER) return;')
        ->toContain('if (clockTimer === null) clockTimer = setInterval(tick, 1000);')
        ->toContain('if (auditRefreshTimer !== null) return;');
});
