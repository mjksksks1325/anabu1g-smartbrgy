<?php

use App\Models\User;
use App\StaffPermissions;

it('loads the shared staff theme last on every staff layout', function (string $route, bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->superAdmin()->create())->withSession(['auth.password_confirmed_at' => time()]);
    }

    $content = $this->get(route($route))->assertOk()->getContent();
    $head = substr($content, 0, strpos($content, '</head>'));

    preg_match_all('/<link rel="stylesheet" href="[^"]*\/css\/([a-z0-9-]+)\.css/', $head, $sheets);
    expect(end($sheets[1]))->toBe('staff-theme');
})->with([
    'workspace' => ['staff.dashboard', true],
    'iot pages' => ['staff.announcements.index', true],
    'account settings' => ['profile.edit', true],
    'sign in' => ['login', false],
]);

it('links each dashboard summary card only to a module the role may open', function (array $permissions, array $links) {
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => $permissions]);
    $content = $this->actingAs($staff)->get(route('staff.dashboard'))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($content);
    $xpath = new DOMXPath($document);

    $visible = collect($xpath->query('//div[contains(@class, "dashboard-stats-grid")]/div[contains(@class, "stat-card")][not(@hidden)]//a[@class="stat-link"]'))
        ->map(fn (DOMElement $link): string => $link->getAttribute('href'))->all();

    expect($visible)->toBe(array_map(fn (string $route): string => route($route), $links));
})->with([
    'kagawad' => [StaffPermissions::presets()['Kagawad — Ordinary Incidents'], ['staff.demographics', 'staff.incidents.index']],
    'secretary' => [StaffPermissions::presets()['Secretary / Assistant'], ['staff.residents.index', 'staff.document-requests.index', 'staff.document-requests.index']],
]);

it('shows Add Purok only to staff whose permission lets them save a purok', function (array $permissions, bool $visible) {
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => $permissions]);
    $document = new DOMDocument;
    @$document->loadHTML($this->actingAs($staff)->get(route('staff.demographics'))->assertOk()->getContent());
    $button = (new DOMXPath($document))->query('//button[@onclick="openAddPurok()"]')->item(0);

    expect($button->getAttribute('data-action-permission'))->toBe('records.create')
        ->and($button->hasAttribute('hidden'))->toBe(! $visible);
    $this->postJson(route('staff.puroks.store'), ['name' => 'Purok Probe', 'color' => '#22c55e'])->assertStatus($visible ? 201 : 403);
})->with([
    'kagawad (demographics only)' => [StaffPermissions::presets()['Kagawad — Ordinary Incidents'], false],
    'secretary (can register residents)' => [StaffPermissions::presets()['Secretary / Assistant'], true],
]);

it('marks reversible archive actions with the outline danger style and keeps delete solid', function () {
    $script = file_get_contents(public_path('js/admin.js'));

    expect($script)->toContain('class="btn btn-xs btn-danger-outline" onclick="archiveIncident(')
        ->toContain('class="btn btn-xs btn-danger-outline" onclick="deleteResident(\'${resident.id}\')">Archive')
        ->toContain('class="btn btn-danger" onclick="event.stopPropagation();deleteUser(');
});

it('links the staff UI bundle after the legacy sheets and enables it only on pages that opt in', function (string $route, bool $linked, bool $optedIn) {
    $this->actingAs(User::factory()->superAdmin()->create())->withSession(['auth.password_confirmed_at' => time()]);

    $content = $this->get(route($route))->assertOk()->getContent();
    $head = substr($content, 0, strpos($content, '</head>'));
    preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $head, $sheets);
    $sheetNames = array_map(fn (string $href): string => basename(parse_url($href, PHP_URL_PATH)), $sheets[1]);
    $staffUi = array_key_last(array_filter($sheetNames, fn (string $name): bool => str_starts_with($name, 'staff-ui')));
    preg_match('/<body[^>]*>/', $content, $body);

    expect($staffUi !== null)->toBe($linked)
        ->and(str_contains($body[0], 'data-staff-ui'))->toBe($optedIn);
    if ($linked) {
        expect($staffUi)->toBe(array_key_last($sheetNames))
            ->and($content)->toContain('/flux/flux');
    }
})->with([
    'workspace (not migrated)' => ['staff.dashboard', true, false],
    'announcements (pilot)' => ['staff.announcements.index', true, true],
    'resident applications (migrated in Work Group 2)' => ['staff.resident-applications.index', true, true],
    'access pending (not migrated)' => ['staff.access-pending', true, false],
    'account settings (own Flux bundle)' => ['profile.edit', false, false],
]);

it('applies the saved staff theme in the head before any workspace stylesheet', function (string $route) {
    $this->actingAs(User::factory()->superAdmin()->create());

    $content = $this->get(route($route))->assertOk()->getContent();
    $boot = strpos($content, "localStorage.getItem('smartbrgy_theme')");

    expect($boot)->not->toBeFalse()
        ->and($boot)->toBeLessThan(strpos($content, '<link rel="stylesheet"'))
        ->and($boot)->toBeLessThan(strpos($content, '</head>'))
        ->and($content)->toContain("localStorage.setItem('flux.appearance', theme)");
})->with(['staff.dashboard', 'staff.announcements.index']);
