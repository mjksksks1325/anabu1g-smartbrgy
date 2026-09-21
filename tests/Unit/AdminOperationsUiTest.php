<?php

test('dashboard and incident screens use authenticated database endpoints', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain('id="dash-stat-issued"')
        ->toContain('id="incidents-tbody"')
        ->toContain('id="incident-pagination"')
        ->toContain('id="inc-resolution-notes"')
        ->toContain('IoT Not Connected')
        ->toContain('class="nav-item deferred"')
        ->toContain("window.open('{{ route('home') }}','_blank')");
    expect($adminScript)
        ->toContain("fetch('/admin/dashboard-summary'")
        ->toContain('fetch(`/admin/incidents?${query}`')
        ->toContain("formData.append('_method', 'PATCH')")
        ->toContain('async function archiveIncident(id)')
        ->not->toContain("const newId = 'INC-2025-");
});

test('request eligibility uses server history and a real csv export', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain("route('admin.request-records.export')")
        ->toContain('id="rr-pagination"')
        ->toContain('Needs Standing Review')
        ->not->toContain('With Blotter Record');
    expect($adminScript)
        ->toContain('fetch(`/admin/request-records?${query}`')
        ->toContain("query.set('eligibility', rrCurrentStatusFilter)")
        ->toContain('requestRecordResidents = payload.data || []')
        ->toContain('openEligibilityForRequestResident');
});
