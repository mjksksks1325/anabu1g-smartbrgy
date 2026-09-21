<?php

test('resident portal uses the restrained civic design system', function () {
    $portal = file_get_contents(dirname(__DIR__, 2).'/resources/views/portal/index.blade.php');
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/portal.css');

    expect($portal)
        ->toContain("asset('css/portal.css')")
        ->toContain('class="portal-intro"')
        ->toContain('Resident Services')
        ->toContain('Request barangay documents online')
        ->toContain('Services online')
        ->not->toContain('<h2>📋 Mga Tuntunin at Kundisyon</h2>');
    expect($styles)
        ->toContain('/* 2026 civic portal refresh */')
        ->toContain('.cert-grid { grid-template-columns:repeat(3, 1fr);')
        ->toContain('background:#f5f7f9;')
        ->toContain('@media (max-width:500px)');
});

test('admin interface is light first with consistent professional surfaces', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $script = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/admin.css');

    expect($dashboard)
        ->toContain('<body class="light-mode">')
        ->toContain('<span id="theme-label">Dark Mode</span>')
        ->toContain('<div class="card-title">Recent Activity</div>')
        ->not->toContain('<div class="card-title">🔔 Recent Activity</div>');
    expect($script)->toContain('let isLightMode = true;');
    expect($styles)
        ->toContain('/* 2026 civic interface refresh */')
        ->toContain('.admin-interface .stat-icon { display:none; }')
        ->toContain('--bg-card:#ffffff;')
        ->toContain('box-shadow:inset 3px 0 0 #2d6f9f');
});
