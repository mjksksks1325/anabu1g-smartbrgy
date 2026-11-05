<?php

use App\Models\User;
use App\StaffPermissions;

/**
 * Staff navigation links rendered on a page, in order: [label, href].
 *
 * @return list<array{0: string, 1: string}>
 */
function staffNavigationLinks(string $content, string $container): array
{
    $document = new DOMDocument;
    @$document->loadHTML($content);
    $xpath = new DOMXPath($document);
    $links = $xpath->query($container.'//a[contains(concat(" ", normalize-space(@class), " "), " nav-item ")]');

    // The visible label only: no counter badge, no screen-reader-only suffix.
    return collect($links)->map(fn (DOMElement $link): array => [
        trim(preg_replace('/\s+/', ' ', collect($xpath->query('.//text()[not(ancestor::*[contains(@class, "nav-badge") or contains(@class, "sr-only")])]', $link))
            ->map(fn (DOMNode $text): string => $text->nodeValue)->implode(''))),
        $link->getAttribute('href'),
    ])->all();
}

/**
 * The links a role must see, by label. Route names resolve per user (Dashboard and the account Settings differ).
 *
 * @param  list<string>  $labels
 * @return list<array{0: string, 1: string}>
 */
function expectedStaffLinks(User $user, array $labels): array
{
    $routes = [
        'Dashboard' => $user->hasAdministrativeAccess() ? 'admin.dashboard' : 'staff.dashboard',
        'Demographics' => 'staff.demographics',
        'Households' => 'staff.households.page',
        'Resident Records' => 'staff.residents.index',
        'Voters' => 'staff.voters',
        'Certificates & Clearances' => 'staff.document-requests.index',
        'Request Eligibility' => 'staff.request-eligibility',
        'Incident Reports' => 'staff.incidents.index',
        'Announcements' => 'staff.announcements.index',
        'RFID File Tracking' => 'staff.rfid-files.index',
        'Smart Cabinet' => 'admin.smart-cabinet.index',
        'Employee Cabinet Access' => 'admin.cabinet-access.index',
        'Audit Log' => 'admin.audit',
        'User Management' => 'admin.users.index',
        'Settings' => $user->hasAdministrativeAccess() ? 'admin.settings' : 'profile.edit',
        'Resident Portal' => 'home',
    ];

    return array_map(fn (string $label): array => [$label, route($routes[$label])], $labels);
}

$administration = ['Dashboard', 'Demographics', 'Resident Records', 'Voters', 'Certificates & Clearances', 'Request Eligibility', 'Incident Reports', 'Announcements', 'RFID File Tracking', 'Smart Cabinet', 'Employee Cabinet Access', 'Audit Log', 'User Management', 'Settings', 'Resident Portal'];

dataset('staff navigation roles', [
    'super admin' => [fn (): User => User::factory()->superAdmin()->create(), $administration],
    'barangay captain' => [fn (): User => User::factory()->create(['role' => 'captain']), $administration],
    'secretary' => [fn (): User => User::factory()->create(['staff_permissions' => StaffPermissions::presets()['Secretary / Assistant']]),
        ['Dashboard', 'Demographics', 'Resident Records', 'Voters', 'Certificates & Clearances', 'Request Eligibility', 'Announcements', 'RFID File Tracking', 'Settings', 'Resident Portal']],
    'records clerk' => [fn (): User => User::factory()->create(['staff_permissions' => StaffPermissions::presets()['Records Clerk']]),
        ['Dashboard', 'Demographics', 'Resident Records', 'Settings', 'Resident Portal']],
    'documents officer' => [fn (): User => User::factory()->create(['staff_permissions' => StaffPermissions::presets()['Documents Officer']]),
        ['Dashboard', 'Certificates & Clearances', 'Request Eligibility', 'Settings', 'Resident Portal']],
    'kagawad' => [fn (): User => User::factory()->create(['staff_permissions' => StaffPermissions::presets()['Kagawad — Ordinary Incidents']]),
        ['Dashboard', 'Demographics', 'Incident Reports', 'RFID File Tracking', 'Settings', 'Resident Portal']],
    'vaw desk' => [fn (): User => User::factory()->create(['staff_permissions' => StaffPermissions::presets()['VAW Desk — Restricted Cases']]),
        ['Dashboard', 'Incident Reports', 'Settings', 'Resident Portal']],
    'tanod' => [fn (): User => User::factory()->create(['staff_permissions' => StaffPermissions::presets()['Tanod']]),
        ['Incident Reports', 'Settings', 'Resident Portal']],
    'households only' => [fn (): User => User::factory()->create(['staff_permissions' => ['households.view']]),
        ['Households', 'Settings', 'Resident Portal']],
]);

it('shows each role the same permitted links in every staff layout', function (Closure $makeUser, array $labels) {
    $user = $makeUser();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
    $expected = expectedStaffLinks($user, $labels);

    $pages = [
        'settings' => [route('profile.edit'), '//nav[contains(@class, "staff-settings-navigation")]'],
        'page layout' => [route('staff.access-pending'), '//nav[contains(@class, "side-nav")]'],
    ];
    if ($user->hasAnyPermission(['dashboard.view'])) {
        $pages['workspace'] = [route($user->hasAdministrativeAccess() ? 'admin.dashboard' : 'staff.dashboard'), '//nav[@id="admin-navigation"]'];
    }

    foreach ($pages as $layout => [$url, $container]) {
        expect(staffNavigationLinks($this->get($url)->assertOk()->getContent(), $container))->toBe($expected, $layout);
    }
})->with('staff navigation roles');

it('keeps backend authorization for pages a role cannot see in its navigation', function () {
    $clerk = User::factory()->create(['staff_permissions' => StaffPermissions::presets()['Records Clerk']]);

    $this->actingAs($clerk)->get(route('admin.smart-cabinet.index'))->assertForbidden();
    $this->get(route('admin.cabinet-access.index'))->assertForbidden();
    $this->get(route('staff.rfid-files.index'))->assertForbidden();
    $this->get(route('staff.document-requests.list'))->assertForbidden();
    $this->get(route('staff.announcements.index'))->assertForbidden();
});

it('shows only account links to a viewer account on the settings pages', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);

    $content = $this->actingAs($viewer)->withSession(['auth.password_confirmed_at' => time()])->get(route('profile.edit'))->assertOk()->getContent();

    expect(staffNavigationLinks($content, '//nav[contains(@class, "staff-settings-navigation")]'))
        ->toBe([['Settings', route('profile.edit')], ['Resident Portal', route('home')]]);
});

it('marks the current page in the navigation', function (string $route, string $container, string $current) {
    $this->actingAs(User::factory()->superAdmin()->create())->withSession(['auth.password_confirmed_at' => time()]);
    $document = new DOMDocument;
    @$document->loadHTML($this->get(route($route))->assertOk()->getContent());

    $currentLinks = collect((new DOMXPath($document))->query($container.'//a[@aria-current="page"]'))
        ->map(fn (DOMElement $link): string => trim(preg_replace('/\s+/', ' ', $link->textContent)))->all();

    expect($currentLinks)->toBe([$current]);
})->with([
    'workspace screen' => ['staff.voters', '//nav[@id="admin-navigation"]', 'Voters'],
    'page layout' => ['staff.announcements.index', '//nav[contains(@class, "side-nav")]', 'Announcements'],
    'resident applications under Resident Records' => ['staff.resident-applications.index', '//nav[contains(@class, "side-nav")]', 'Resident Records'],
    'settings' => ['profile.edit', '//nav[contains(@class, "staff-settings-navigation")]', 'Settings'],
]);
