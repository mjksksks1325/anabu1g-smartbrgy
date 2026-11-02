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
    'kagawad' => [StaffPermissions::presets()['Authorized Kagawad'], ['staff.demographics', 'staff.incidents.index']],
    'secretary' => [StaffPermissions::presets()['Secretary / Assistant'], ['staff.residents.index', 'staff.document-requests.index', 'staff.document-requests.index']],
]);

it('marks reversible archive actions with the outline danger style and keeps delete solid', function () {
    $script = file_get_contents(public_path('js/admin.js'));

    expect($script)->toContain('class="btn btn-xs btn-danger-outline" onclick="archiveIncident(')
        ->toContain('class="btn btn-xs btn-danger-outline" onclick="deleteResident(\'${resident.id}\')">Archive')
        ->toContain('class="btn btn-danger" onclick="event.stopPropagation();deleteUser(');
});
