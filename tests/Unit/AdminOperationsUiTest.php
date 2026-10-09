<?php

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
        ->toContain('IoT status')
        ->not->toContain('IoT Not Connected')
        ->toContain("route('staff.rfid-files.index')")
        ->not->toContain('id="screen-cabinet"')
        ->not->toContain('id="screen-face"')
        ->toContain("window.open('{{ route('home') }}','_blank')");
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

test('personnel tables center cells and audit headers share the five column row layout', function () {
    $adminStyles = file_get_contents(dirname(__DIR__, 2).'/public/css/figma-admin.css');
    $iotStyles = file_get_contents(dirname(__DIR__, 2).'/public/css/figma-iot.css');
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    expect($adminStyles)
        ->toContain('.tbl th,')
        ->toContain('.tbl td { text-align: center; vertical-align: middle; }')
        ->toContain('.tbl div.resident-actions,')
        ->toContain('justify-content: center;')
        ->toContain('.tbl td.resident-actions { display: table-cell; }')
        ->toContain('.audit-table-head,')
        ->toContain('.audit-log-row {')
        ->toContain('grid-template-columns: 50px minmax(135px, 1fr) minmax(0, 2fr) minmax(110px, 1fr) 90px;')
        ->toContain('@media (min-width: 761px)');
    expect($iotStyles)->toContain('.main-content table th,')
        ->toContain('.main-content table td { text-align: center; vertical-align: middle; }');
    expect($dashboard)->toContain('class="audit-log-table-scroll"')
        ->not->toContain('grid-template-columns:50px 130px 1fr 200px 90px');
});
