<?php

use App\Models\Resident;
use App\Models\User;

test('clean workspace routes render the existing layout with the matching active navigation and screen', function (string $path, string $screen) {
    $response = $this->actingAs(User::factory()->superAdmin()->create())->get($path)
        ->assertOk()->assertViewIs('admin.dashboard')->assertViewHas('activeScreen', $screen);
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//a[@data-screen="'.$screen.'" and @aria-current="page"]')->length)->toBe(1);
    expect($xpath->query('//div[@id="screen-'.$screen.'" and contains(concat(" ", normalize-space(@class), " "), " active ")]')->length)->toBe(1);
    expect($xpath->query('//a[contains(@href, "screen=")]')->length)->toBe(0);
    $response->assertSee('href="'.url('/admin/residents').'"', false)->assertSee('href="'.url('/admin/audit').'"', false);
})->with([
    ['/admin', 'dashboard'], ['/admin/demographics', 'demographics'], ['/admin/residents', 'records'],
    ['/admin/voters', 'voters'], ['/admin/document-requests', 'certificates'], ['/admin/request-eligibility', 'request-records'],
    ['/admin/incidents', 'incidents'], ['/admin/audit', 'audit'], ['/admin/users', 'users'], ['/admin/settings', 'settings'],
]);

test('ordinary administrators retain normal workspace access and are denied administration screens', function (string $path) {
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path)->assertForbidden();
})->with(['/admin/audit', '/admin/users', '/admin/settings', '/admin?screen=audit']);

test('staff can open normal clean workspace screens', function (string $path) {
    $this->actingAs(User::factory()->create(['role' => 'staff']))->get($path)->assertOk()->assertViewIs('admin.dashboard');
})->with(['/admin', '/admin/residents', '/admin/demographics', '/admin/document-requests', '/admin/request-eligibility', '/admin/voters', '/admin/incidents']);

test('resident users cannot enter any clean workspace screen', function (string $path) {
    $this->actingAs(User::factory()->resident()->create())->get($path)->assertForbidden();
})->with(['/admin', '/admin/audit', '/admin/residents', '/admin/demographics', '/admin/document-requests', '/admin/voters', '/admin/incidents', '/admin/request-eligibility', '/admin/users', '/admin/settings']);

test('unauthenticated visitors must sign in before opening clean workspace pages', function () {
    $this->get('/admin/residents')->assertRedirect(route('login'));
    $this->get('/admin/audit')->assertRedirect(route('login'));
});

test('legacy screens redirect to fixed clean destinations while preserving search filters and pagination', function (string $screen, string $destination) {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin?'.http_build_query([
        'screen' => $screen, 'search' => 'Santos', 'page' => 2, 'date' => '2026-10-01',
    ]))->assertRedirect(url($destination).'?'.http_build_query(['search' => 'Santos', 'page' => 2, 'date' => '2026-10-01']));
})->with([
    ['audit', '/admin/audit'], ['records', '/admin/residents'], ['demographics', '/admin/demographics'],
    ['certificates', '/admin/document-requests'], ['voters', '/admin/voters'], ['request-records', '/admin/request-eligibility'],
    ['incidents', '/admin/incidents'], ['users', '/admin/users'], ['settings', '/admin/settings'], ['dashboard', '/admin'],
]);

test('invalid legacy screen values return safely to the dashboard', function (string $query) {
    $this->actingAs(User::factory()->create())->get('/admin?'.$query)->assertRedirect(route('admin.dashboard'));
})->with(['screen=unknown', 'screen=https%3A%2F%2Fevil.example', 'screen%5B%5D=audit']);

test('resident data requests retain filtering pagination archive and JSON responses on the shared clean URL', function () {
    Resident::factory()->count(3)->create(['last_name' => 'Santos', 'status' => 'active']);
    Resident::factory()->create(['last_name' => 'Santos', 'status' => 'inactive']);
    $archived = Resident::factory()->create(['last_name' => 'Santos']);
    $archived->delete();
    $this->actingAs(User::factory()->create())->getJson('/admin/residents?search=Santos&status=active&per_page=2&page=2')
        ->assertOk()->assertJsonPath('total', 3)->assertJsonPath('current_page', 2)->assertJsonCount(1, 'data');
    $this->getJson('/admin/residents?search=Santos&status=archived')->assertOk()->assertJsonPath('data.0.id', $archived->id);
    $this->get('/admin/residents?search=Santos&status=active&page=2')->assertOk()->assertViewHas('activeScreen', 'records');
});

test('audit pages accept legitimate filter queries and retain the protected JSON feed', function () {
    $this->actingAs(User::factory()->superAdmin()->create())->get('/admin/audit?search=juan&date=2026-10-01')
        ->assertOk()->assertViewHas('activeScreen', 'audit');
    $this->getJson(route('admin.audit.index'))->assertOk()->assertJsonStructure(['events']);
    $this->actingAs(User::factory()->create(['role' => 'admin']))->getJson(route('admin.audit.index'))->assertForbidden();
});

test('the existing standalone request list remains available without redesigning its view', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.document-requests.list'))
        ->assertOk()->assertViewIs('admin.document-requests.index');
    $this->actingAs(User::factory()->resident()->create())->get(route('admin.document-requests.list'))->assertForbidden();
});
