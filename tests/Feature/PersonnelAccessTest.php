<?php

use App\Models\Incident;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('staff entry requires personnel login and legacy login links still work', function () {
    $this->get('/staff')->assertRedirect('/staff/login');
    $this->get('/login')->assertRedirect('/staff/login');
    $this->get('/staff/login')->assertOk()->assertSee('action="'.url('/staff/login').'"', false);
});

test('personnel login lands at the role home and discards unsafe intended urls', function (string $role, bool $privileged, string $destination, string $intended) {
    $user = User::factory()->create(['role' => $role, 'is_super_admin' => $privileged]);

    $this->withSession(['url.intended' => $intended])->post('/staff/login', [
        'email' => $user->email, 'password' => 'password',
    ])->assertRedirect($destination)->assertSessionMissing('url.intended');

    $this->assertAuthenticatedAs($user, 'web');
    $this->assertGuest('resident');
})->with([
    ['staff', false, '/staff/access-pending', '/admin/users'],
    ['staff', false, '/staff/access-pending', 'https://evil.example/staff'],
    ['staff', false, '/staff/access-pending', '/portal/account'],
    ['admin', false, '/staff/access-pending', '/admin/users'],
    ['admin', true, '/admin', '/staff/residents'],
]);

test('resident and unmapped accounts cannot establish personnel sessions or two factor challenges', function (string $role) {
    $user = User::factory()->withTwoFactor()->create(['role' => $role]);

    $this->post('/staff/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email')->assertSessionMissing('login.id');

    $this->assertGuest('web');
    $this->assertDatabaseEmpty('administrative_audits');
})->with(['resident', 'viewer', 'secretary', 'unknown']);

test('personnel login rejects suspended and resident linked staff accounts', function (array $attributes) {
    $user = User::factory()->create($attributes);

    $this->post('/staff/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');

    $this->assertGuest('web');
})->with([
    [['is_active' => false]],
    [fn () => ['resident_id' => Resident::factory()->create()->id]],
]);

test('resident login uses its separate guard while personnel login does not change it', function () {
    $resident = User::factory()->resident()->create();
    $staff = User::factory()->create();

    $this->post('/portal/login', ['email' => $resident->email, 'password' => 'password'])->assertRedirect('/portal/account');
    $this->assertAuthenticatedAs($resident, 'resident');
    $this->assertGuest('web');
    $this->post('/staff/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect('/staff/access-pending');
    $this->assertAuthenticatedAs($staff, 'web');
    $this->assertAuthenticatedAs($resident, 'resident');
    $this->post(route('logout'))->assertRedirect('/staff/login');
    $this->assertGuest('web');
    $this->assertAuthenticatedAs($resident, 'resident');
});

test('resident guard alone cannot authenticate personnel routes', function () {
    $this->actingAs(User::factory()->resident()->create(), 'resident')
        ->get('/staff/residents')->assertRedirect('/staff/login');
});

test('resident web sessions are forbidden on personnel reads and writes', function (string $method, string $path) {
    $this->actingAs(User::factory()->resident()->create(), 'web')
        ->json($method, $path)->assertForbidden();
    $this->assertDatabaseMissing('puroks', ['name' => 'Purok Legacy', 'color' => '#123456']);
})->with([
    ['GET', '/staff'], ['GET', '/staff/residents'], ['POST', '/staff/puroks'],
    ['POST', '/staff/issued-certificates'], ['POST', '/staff/incidents'],
    ['GET', '/admin/residents'], ['POST', '/admin/puroks'], ['POST', '/admin/users'],
]);

test('staff and ordinary admins retain existing privileged denials', function (string $role, string $method, string $path) {
    $this->actingAs(User::factory()->create(['role' => $role]))->json($method, $path)->assertForbidden();
    $this->assertDatabaseCount('users', 1);
})->with(['staff', 'admin'])->with([
    ['GET', '/admin/audit'], ['GET', '/admin/audit-log'], ['GET', '/admin/users'],
    ['GET', '/admin/settings'], ['POST', '/admin/users'], ['GET', '/admin/smart-cabinet'],
]);

test('legacy operational paths redirect authorized personnel with query parameters', function (string $role, string $path) {
    $this->actingAs($role === 'admin' ? User::factory()->superAdmin()->create() : User::factory()->assignedOperations()->create())
        ->get('/admin/'.$path.'?search=Santos&page=2&filter%5Bstatus%5D=active')
        ->assertRedirect('/staff/'.$path.'?filter%5Bstatus%5D=active&page=2&search=Santos');
})->with(['staff', 'admin'])->with(['residents', 'document-requests', 'incidents', 'voters']);

test('legacy action redirects preserve method and do not execute the operation', function () {
    $user = User::factory()->assignedOperations()->create();

    $this->actingAs($user)->post('/admin/puroks?source=old', ['name' => 'Purok Legacy', 'color' => '#123456'])
        ->assertStatus(307)->assertRedirect('/staff/puroks?source=old');
    $this->assertDatabaseMissing('puroks', ['name' => 'Purok Legacy', 'color' => '#123456']);
    $this->postJson('/staff/puroks?source=old', ['name' => 'Purok Legacy', 'color' => '#123456'])->assertCreated();
    $this->assertDatabaseHas('puroks', ['name' => 'Purok Legacy', 'color' => '#123456']);
});

test('staff legacy dashboard links retain filters and reject privileged screens', function () {
    $this->actingAs(User::factory()->assignedOperations()->create())->get('/admin?screen=records&search=Santos&page=2')
        ->assertRedirect('/staff/residents?search=Santos&page=2');
    $this->get('/admin?screen=users')->assertForbidden();
    $this->get('/staff?screen=settings')->assertForbidden();
    $this->get('/admin')->assertRedirect('/staff');
});

test('admins retain operational writes and privileged accounts retain management', function (bool $privileged) {
    $admin = User::factory()->create(['role' => 'admin', 'is_super_admin' => $privileged]);

    if (! $privileged) {
        $this->actingAs($admin)->get('/admin')->assertRedirect('/staff/access-pending');
        $this->get('/staff/residents')->assertForbidden();
        $this->postJson('/staff/puroks', ['name' => 'Admin Operations', 'color' => '#123456'])->assertForbidden();

        return;
    }
    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->get('/staff/residents')->assertOk();
    $this->postJson('/staff/puroks', ['name' => 'Admin Operations', 'color' => '#123456'])->assertCreated();
    $this->assertDatabaseHas('puroks', ['name' => 'Admin Operations', 'color' => '#123456']);
})->with([false, true]);

test('two factor login rechecks account eligibility before creating a session', function () {
    $user = User::factory()->withTwoFactor()->create();
    $this->post('/staff/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
    $user->forceFill(['role' => 'resident'])->save();

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])
        ->assertRedirect('/staff/login')->assertSessionMissing('login.id');
    $this->assertGuest('web');
    $this->assertDatabaseEmpty('administrative_audits');
});

test('personnel login remains throttled', function () {
    $user = User::factory()->create();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/staff/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    }
    $this->post('/staff/login', ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
    $this->assertGuest('web');
    RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
});

test('resident sessions cannot update or delete operational records', function (string $method, string $suffix) {
    $resident = Resident::factory()->create();
    $originalName = $resident->first_name;

    $this->actingAs(User::factory()->resident()->create(), 'web')
        ->json($method, '/staff/residents/'.$resident->id.$suffix, ['first_name' => 'Unauthorized'])
        ->assertForbidden();

    $this->assertDatabaseHas('residents', ['id' => $resident->id, 'first_name' => $originalName, 'deleted_at' => null]);
})->with([['PATCH', ''], ['DELETE', ''], ['PATCH', '/restore'], ['POST', '/portal-activation']]);

test('staff cannot change privileged users or resident account status', function () {
    $target = User::factory()->superAdmin()->create();
    $resident = User::factory()->resident()->create();
    $staff = User::factory()->create();

    $this->actingAs($staff)->patchJson('/admin/users/'.$target->id, ['is_active' => false])->assertForbidden();
    $this->patchJson('/admin/residents/'.$resident->resident_id.'/portal-account', ['is_active' => false])->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => true, 'is_super_admin' => true]);
    $this->assertDatabaseHas('users', ['id' => $resident->id, 'is_active' => true]);
});

test('unauthenticated action requests receive 401 and never write records', function (string $path) {
    $this->postJson($path, ['name' => 'Unauthorized'])->assertUnauthorized();

    $this->assertDatabaseMissing('puroks', ['name' => 'Unauthorized']);
})->with(['/staff/puroks', '/admin/puroks', '/admin/users']);

test('legacy account settings links redirect to personnel preferences', function () {
    $this->actingAs(User::factory()->create())->get('/settings/profile')->assertRedirect('/staff/settings/profile');
    $this->get('/staff/settings/profile')->assertOk();
});

test('successful personnel authentication regenerates the session', function () {
    $user = User::factory()->create();
    $this->withSession(['session_marker' => 'before-login']);
    $originalSessionId = session()->getId();

    $this->post('/staff/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/staff/access-pending');

    expect(session()->getId())->not->toBe($originalSessionId);
});

test('legacy logout preserves resident authentication and returns to personnel login', function () {
    $resident = User::factory()->resident()->create();
    $staff = User::factory()->create();
    $this->actingAs($resident, 'resident')->actingAs($staff, 'web');

    $this->post('/logout')->assertRedirect('/staff/login');

    $this->assertGuest('web');
    $this->assertAuthenticatedAs($resident, 'resident');
});

test('legacy login forms redirect with credentials without authenticating before personnel checks', function () {
    $resident = User::factory()->resident()->create();

    $this->post('/login', ['email' => $resident->email, 'password' => 'password'])
        ->assertStatus(307)->assertRedirect('/staff/login');

    $this->assertGuest('web');
    $this->assertDatabaseEmpty('administrative_audits');
});

test('legacy routes reject staff before redirecting actions reserved for admins', function () {
    $incident = Incident::factory()->create();

    $this->actingAs(User::factory()->create())->post('/admin/request-restrictions')->assertForbidden();
    $this->delete('/admin/incidents/'.$incident->id)->assertForbidden();
    $this->get('/admin/protection-orders')->assertForbidden();

    $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'deleted_at' => null]);
    $this->assertDatabaseEmpty('resident_request_restrictions');
});

test('top personnel avatar has no logout action while bottom logout retains confirmation', function (string $role, bool $isSuperAdmin, string $path) {
    $user = User::factory()->assignedOperations()->create(['role' => $role, 'is_super_admin' => $isSuperAdmin]);

    $response = $this->actingAs($user)->get($path)->assertOk();

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $avatars = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " staff-initials ") or contains(concat(" ", normalize-space(@class), " "), " topbar-avatar ")]');
    expect($avatars->length)->toBe(1);
    $avatar = $avatars->item(0);
    expect($avatar->nodeName)->toBe('span');
    expect($avatar->getAttribute('onclick'))->toBe('');
    expect($xpath->query('ancestor::form', $avatar)->length)->toBe(0);
    $response->assertSee('data-personnel-logout', false)->assertSee('id="logout-dialog"', false);
    $this->assertAuthenticatedAs($user);
})->with([
    ['staff', false, '/staff'],
    ['staff', false, '/staff/rfid-file-tracking'],
    ['staff', false, '/staff/incidents/create'],
    ['admin', true, '/admin'],
    ['admin', true, '/staff/rfid-file-tracking'],
    ['admin', true, '/admin/smart-cabinet'],
]);
