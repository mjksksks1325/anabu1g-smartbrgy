<?php

test('voter page supports adding voters and separates the list by purok and age eligibility', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');
    $adminStyles = file_get_contents(dirname(__DIR__, 2).'/public/css/admin.css');

    expect($dashboard)
        ->toContain("showScreen('voters',this)")
        ->toContain('id="screen-voters"')
        ->toContain('<h1>Voters</h1>')
        ->toContain('Para sa barangay personnel:')
        ->toContain('Existing Voter Registration Date')
        ->toContain('hindi ito pagpaparehistro sa COMELEC')
        ->not->toContain('Voter Registry')
        ->toContain('id="voter-purok-filter"')
        ->toContain('SK Lamang (15–17)')
        ->toContain('SK at Regular (18–30)')
        ->toContain('Regular Lamang (31+)')
        ->toContain('onclick="openVoterRegistration()"')
        ->toContain('id="modal-voter-registration"')
        ->toContain('id="voter-registry-tbody"');
    expect($adminScript)
        ->toContain('fetch(`/admin/voter-registrations?${query}`')
        ->toContain('window.location.href = `/admin/voter-registrations-export?${query}`')
        ->toContain("query.set('eligibility', voterEligibilityFilter)")
        ->toContain("query.set('purok', voterPurokFilter)")
        ->toContain("fetch('/admin/voter-registrations', {")
        ->toContain("method: 'POST'")
        ->toContain('registration.voter_eligibility_label')
        ->toContain("document.getElementById('voter-registration-date').value = '';");
    expect($adminStyles)
        ->toContain('.voter-table { min-width:860px; }')
        ->toContain('@media (max-width: 700px)')
        ->toContain('.voter-filter-pills { overflow-x:auto;');
});

test('purok selector and demographics load from the authenticated database api', function () {
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($adminScript)
        ->toContain("fetch('/admin/puroks'")
        ->toContain('PUROK_DATA.splice(0, PUROK_DATA.length')
        ->toContain('DEMOGRAPHIC_SUMMARY = payload.demographics')
        ->toContain('await loadPuroks();');
});
