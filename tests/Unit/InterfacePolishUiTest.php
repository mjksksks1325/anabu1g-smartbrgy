<?php

test('resident portal presents its current services design', function () {
    $portal = file_get_contents(dirname(__DIR__, 2).'/resources/views/portal/index.blade.php');
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/portal.blade.php');
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css');

    expect($layout)->toContain("asset('css/figma-portal.css')");
    expect($portal)
        ->toContain("@extends('layouts.portal')")
        ->toContain('class="portal-hero"')
        ->toContain('class="portal-service-grid"')
        ->toContain("class=\"btn btn-green\" href=\"{{ route('portal.request.create') }}\"")
        ->toContain("asset('images/barangay-hall-anabu-1g.jpg')")
        ->toContain('Request a document')
        ->toContain('My requests')
        ->toContain('Requirements &amp; fees')
        ->toContain('Get help')
        ->not->toContain('Report an incident')
        ->not->toContain('<h2>ðŸ“‹ Mga Tuntunin at Kundisyon</h2>');
    expect($styles)
        ->toContain('.portal-hero {')
        ->toContain('.portal-service-card {')
        ->toContain('.portal-service-grid { grid-template-columns: 1fr; }')
        ->toContain('var(--civic-blue)');
});

test('admin interface is light first with consistent professional surfaces', function () {
    $dashboard = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');
    $script = file_get_contents(dirname(__DIR__, 2).'/public/js/admin.js');
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/admin.css');

    expect($dashboard)
        ->toContain('<body class="light-mode">')
        ->toContain('<span id="theme-label">Dark Mode</span>')
        ->toContain('<div class="card-title">Recent Activity</div>')
        ->not->toContain('<div class="card-title">ðŸ”” Recent Activity</div>');
    expect($script)->toContain("document.body?.classList?.contains('light-mode') ?? true")
        ->toContain("localStorage.setItem('smartbrgy_theme'");
    expect($styles)
        ->toContain('/* 2026 civic interface refresh */')
        ->toContain('.admin-interface .stat-icon { display:none; }')
        ->toContain('--bg-card:#ffffff;')
        ->toContain('box-shadow:inset 3px 0 0 #2d6f9f');
});

test('staff dashboard, iot pages, and account settings share one staff palette', function () {
    $root = dirname(__DIR__, 2);
    $shared = file_get_contents($root.'/public/css/government.css');
    $dashboard = file_get_contents($root.'/public/css/figma-admin.css');
    $iot = file_get_contents($root.'/public/css/figma-iot.css');

    expect($shared)
        ->toContain('html[data-theme=dark], html.dark, body:not(.light-mode) #app.admin-interface {')
        ->toContain('--staff-bg: oklch(17% .025 240);')
        ->toContain('.dark body.civic-settings .civic-settings-main { background: var(--staff-bg); }');
    expect($dashboard)
        ->toContain('--bg-base: var(--staff-bg);')
        ->toContain('--sidebar-bg: var(--staff-sidebar);');
    expect($iot)
        ->toContain('html[data-theme=dark] .main-content { background: var(--staff-bg); }')
        ->toContain('html[data-theme=dark] .eyebrow, html[data-theme=dark] .workspace-title span')
        ->not->toContain('html[data-theme=dark] { --civic-ink: #e6f0f8;');
});

test('staff sidebars always show the orange scrollbar', function () {
    $root = dirname(__DIR__, 2);

    foreach (['public/css/figma-admin.css', 'public/css/figma-iot.css', 'public/css/government.css'] as $path) {
        expect(file_get_contents($root.'/'.$path))
            ->toContain('scrollbar-color: #d97706 transparent; scrollbar-width: thin;')
            ->not->toContain('scrollbar-color: transparent transparent');
    }

    expect(file_get_contents($root.'/resources/views/layouts/admin-iot.blade.php'))
        ->toContain('<div class="side-nav-scroll staff-settings-navigation">')
        ->toContain('<div class="sidebar-label">IoT Security</div>');
});

test('resident portal uses the staff workspace background color', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('body:not(.dark-mode) { --bg: #d9e6ef; background: #d9e6ef; }');
});

test('resident portal service cards use an svg arrow instead of a text glyph', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->not->toContain('content: "→"')
        ->toContain("%3Cpath d='M5 12h14M13 6l6 6-6 6'/%3E");
});

test('resident portal hero photo is full bleed with the copy overlapping it', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('.portal-hero-photo, body.dark-mode .portal-hero-photo { position: absolute; inset: 0;')
        ->toContain('.portal-hero { position: relative; z-index: 1;');
});

test('resident portal office card and map stretch to the same height', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('.office-section { align-items: stretch; }')
        ->toContain('.office-map .map-panel iframe { flex: 1;');
});

test('resident portal service cards are spaced away from the hero photo', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('.portal-band + .container .digital-services { margin-top: 40px; }');
});

test('resident portal keeps the round seal and green line in dark mode', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('body.dark-mode .gov-bar { border-bottom: 3px solid #087b40; }')
        ->toContain('body.dark-mode .site-brand img { padding: 4px; border: 2px solid #068445; border-radius: 50%;');
});

test('resident portal dims the hero photo slightly in dark mode', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('body.dark-mode .portal-hero-photo img { filter: brightness(.72); }');
});

test('resident portal hero photo fades between light and dark mode', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('.portal-hero-photo img { transition: filter .7s ease; }');
});

test('resident portal request flow spans the full content width', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('.request-layout { max-width: none; }');
});

test('resident portal stacks the unconfirmed notice under the requirements panel', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/figma-portal.css'))
        ->toContain('.requirements-intro { grid-template-columns: 1fr; gap: 14px; }');
});
