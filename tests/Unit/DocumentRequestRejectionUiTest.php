<?php

test('online request cards use rejection with a required reason and released cards have no destructive action', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');
    $statusScript = file_get_contents(dirname(__DIR__, 2).'/public/js/status-checker.js');

    expect($dashboard)
        ->toContain('id="modal-reject-request"')
        ->toContain('id="reject-request-reason"')
        ->toContain('onclick="confirmRejectRequest()"');
    expect($adminScript)
        ->toContain("lane.id !== 'completed' && r.via === 'Online'")
        ->toContain("body: JSON.stringify({ status: 'rejected', rejection_reason: reason })")
        ->toContain('Enter a clear rejection reason of at least 10 characters.')
        ->not->toContain('hideCertRequest(')
        ->not->toContain('🗑️ Alisin');
    expect($statusScript)
        ->toContain('data.rejection_reason')
        ->toContain('escapeStatusText(data.rejection_reason)');
});
