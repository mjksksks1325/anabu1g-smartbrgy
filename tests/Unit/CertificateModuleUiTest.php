<?php

test('manual certificate modal submits real issuance fields and opens the generated print page', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain('id="manual-certificate-form"')
        ->toContain('onsubmit="issueManualCertificate(event)"')
        ->toContain('id="manual-certificate-type"')
        ->toContain('id="manual-resident-id"')
        ->toContain('id="manual-resident-name"')
        ->toContain('id="manual-certificate-submit"')
        ->not->toContain("showToast('Certificate issued!','green');closeModal('modal-cert-issue')");
    expect($adminScript)
        ->toContain("fetch('/admin/issued-certificates'")
        ->toContain("resident_id: Number(document.getElementById('manual-resident-id')?.value) || null")
        ->toContain("window.open('about:blank', '_blank')")
        ->toContain('printWindow.location.href = data.print_url;');
});

test('certificates retain public verification without the old staff QR scanner', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain('Issued Certificate History')
        ->not->toContain('id="screen-qr"')
        ->not->toContain('Open QR Scanner');
    expect($adminScript)
        ->toContain("row.querySelector('.issued-verify')")
        ->not->toContain('async function verifyCertCode(code)');
});

test('issued certificate history supports reprinting and verification after issuance', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($dashboard)
        ->toContain('Issued Certificate History')
        ->toContain('id="issued-certificates-tbody"');
    expect($adminScript)
        ->toContain("fetch('/admin/issued-certificates'")
        ->toContain("row.querySelector('.issued-reprint')")
        ->toContain("row.querySelector('.issued-verify')");
});

test('certificate processing uses the current csrf cookie for authenticated requests', function () {
    $adminScript = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');

    expect($adminScript)
        ->toContain("readCookie('XSRF-TOKEN')")
        ->toContain("{ 'X-XSRF-TOKEN': xsrfToken }")
        ->toContain("credentials: 'same-origin'")
        ->toContain('...csrfRequestHeaders()');
});
