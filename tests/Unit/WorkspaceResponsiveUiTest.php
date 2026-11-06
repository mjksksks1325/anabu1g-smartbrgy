<?php

/*
 * Tablet layout for the legacy staff layouts. Below 1024px the menu is a drawer, as in the Flux account settings
 * layout; KPI rows follow the width of the screen's content rather than the window.
 */

test('tablets get the staff menu as a drawer opened by Menu in both legacy layouts', function () {
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/staff-theme.css');

    expect($styles)
        ->toContain('@media (min-width: 761px) and (max-width: 1023px) {')
        ->toContain('body #app.admin-interface.admin-interface .mobile-menu-button { display: inline-flex;')
        ->toContain('body #app.admin-interface.admin-interface .sidebar { display: none; position: fixed;')
        ->toContain('body #app.admin-interface.admin-interface .sidebar.is-open { display: flex; }')
        ->toContain('@media (min-width: 651px) and (max-width: 1023px) {')
        ->toContain('body[data-personnel-layout="iot"] .side-nav:not(.is-open) { display: none; }')
        ->toContain('body[data-personnel-layout="iot"] .workspace { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto minmax(0, 1fr); }');
});

test('workspace KPI rows go two by two when their screen has under 900px of content', function () {
    $styles = file_get_contents(dirname(__DIR__, 2).'/public/css/staff-theme.css');

    expect($styles)
        ->toContain('body #app.admin-interface.admin-interface .content { container: workspace-content / inline-size; }')
        ->toContain("@container workspace-content (max-width: 900px) {\n  body #app.admin-interface.admin-interface .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }")
        ->toContain("@container workspace-content (max-width: 520px) {\n  body #app.admin-interface.admin-interface .stats-grid { grid-template-columns: minmax(0, 1fr); }");
});

test('icons inside workspace buttons use the button text colour', function () {
    expect(file_get_contents(dirname(__DIR__, 2).'/public/css/staff-theme.css'))
        ->toContain('body #app.admin-interface.admin-interface .btn > svg.nav-icon { color: currentColor; stroke: currentColor; opacity: 1; }');
});

test('returning to the Dashboard never paints the legacy full-screen login overlay', function () {
    $root = dirname(__DIR__, 2);
    $dashboard = file_get_contents($root.'/resources/views/admin/dashboard.blade.php');

    expect(file_get_contents($root.'/public/css/admin.css'))->toContain("#login-screen {\n  position: fixed; inset: 0;")
        ->and(file_get_contents($root.'/public/css/staff-theme.css'))->toContain('#login-screen[hidden] { display: none !important; }')
        ->and($dashboard)->toContain('<div id="login-screen" hidden></div>')
        ->and($dashboard)->toContain('<div id="app" class="admin-interface visible">');
});

test('versioned staff scripts are tracked so an open tab reloads after an update instead of keeping old code', function () {
    foreach (['resources/views/admin/dashboard.blade.php', 'resources/views/layouts/admin-iot.blade.php', 'resources/views/layouts/app/sidebar.blade.php'] as $layout) {
        preg_match_all('/<script[^>]*filemtime[^>]*>/', file_get_contents(dirname(__DIR__, 2).'/'.$layout), $scripts);

        expect($scripts[0])->not->toBeEmpty();
        foreach ($scripts[0] as $script) {
            expect($script)->toContain('data-navigate-track');
        }
    }
});
