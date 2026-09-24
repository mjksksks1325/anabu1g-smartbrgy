<?php

use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('portal home shows community content without the document request form', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertViewIs('portal.index')
        ->assertSee(route('portal.request.create'), false)
        ->assertSee('Request a document')
        ->assertDontSee('id="request-flow"', false)
        ->assertDontSee('id="screen-terms"', false)
        ->assertDontSee('id="resident-details-form"', false);
});

test('public portal shows community information and usable service links', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('id="population"', false)
        ->assertSee('2,345')
        ->assertSee('2024 Census of Population')
        ->assertSee('id="announcements"', false)
        ->assertSee('Wala pang inilalathalang anunsiyo')
        ->assertSee('id="contacts"', false)
        ->assertSee('tel:+63468889911', false)
        ->assertSee('tel:+639985985601', false)
        ->assertSee('tel:+639155283256', false)
        ->assertSee('id="location"', false)
        ->assertSee('id="quick-links"', false)
        ->assertSee(route('portal.request.create'), false)
        ->assertSee(route('portal.information').'#requirements', false);
});

test('document request page renders the terms when a resident is signed in', function () {
    $resident = User::factory()->resident()->create();

    $this->actingAs($resident, 'resident')
        ->get(route('portal.request.create'))
        ->assertOk()
        ->assertViewIs('portal.request')
        ->assertSee('id="request-flow" aria-label="Document request"', false)
        ->assertSee('Terms and Conditions')
        ->assertDontSee('id="population"', false);
});

test('legacy request links redirect to the dedicated request page', function () {
    $resident = User::factory()->resident()->create();

    $this->actingAs($resident, 'resident')
        ->get(route('home', ['request' => 1, 'service' => 'BC']))
        ->assertRedirect(route('portal.request.create', ['service' => 'BC']));
});

test('request buttons reach resident login while an employee remains authenticated', function (string $role) {
    $employee = User::factory()->create(['role' => $role]);

    $this->actingAs($employee)->followingRedirects()->get(route('portal.request.create', ['service' => 'BC']))
        ->assertOk()->assertViewIs('portal.login');
    $this->get(route('portal.login'))->assertViewIs('portal.login');
    $this->get(route('portal.register'))->assertViewIs('portal.register');
    $this->assertAuthenticatedAs($employee);
    $this->assertGuest('resident');
})->with(['admin', 'staff', 'viewer']);

test('guest request buttons reach the separate resident login', function () {
    $this->followingRedirects()->get(route('portal.request.create', ['service' => 'BC']))
        ->assertOk()->assertViewIs('portal.login');
    $this->get(route('portal.login'))->assertViewIs('portal.login');
    $this->get(route('portal.register'))->assertViewIs('portal.register');
    $this->assertGuest();
    $this->assertGuest('resident');
});

test('resident returns to the request page after signing in from a request link', function () {
    $resident = User::factory()->resident()->create();

    $this->get(route('portal.request.create'))->assertRedirect(route('portal.login', ['next' => 'request', 'service' => '']));
    $this->get(route('portal.login', ['next' => 'request']))->assertOk();
    $this->post(route('portal.login.store'), ['email' => $resident->email, 'password' => 'password'])
        ->assertRedirect(route('portal.request.create'));
});

test('ineligible resident cannot open the document request page', function () {
    $resident = User::factory()->resident()->create();
    $resident->resident->update(['status' => 'inactive']);

    $this->actingAs($resident, 'resident')
        ->get(route('portal.request.create'))
        ->assertForbidden()
        ->assertSee('Please visit Barangay');
});

test('authenticated employees can use resident registration without ending their employee session', function () {
    $employee = User::factory()->create(['role' => 'staff']);
    $resident = Resident::factory()->create([
        'portal_registration_hash' => hash('sha256', 'private-test-activation-code'),
        'portal_registration_expires_at' => now()->addDay(),
    ]);

    $this->actingAs($employee)->post(route('portal.register.verify'), [
        'resident_number' => $resident->resident_number,
        'activation_code' => 'private-test-activation-code',
    ])->assertRedirect(route('portal.register'));
    $this->post(route('portal.register.store'), [
        'email' => 'resident@example.test',
        'password' => 'ResidentPassword123!',
        'password_confirmation' => 'ResidentPassword123!',
    ])->assertRedirect(route('portal.login'));

    $this->assertAuthenticatedAs($employee);
    $this->assertGuest('resident');
    $this->assertDatabaseCount('users', 2);
});

test('an authenticated resident stays in the resident portal when opening account entry links', function () {
    $resident = User::factory()->resident()->create();

    $this->actingAs($resident, 'resident');
    $this->get(route('portal.login'))->assertRedirect(route('home'));
    $this->get(route('portal.register'))->assertRedirect(route('portal.account'));
});

test('employee and resident accounts can be authenticated at the same time', function () {
    $employee = User::factory()->create(['role' => 'admin']);
    $resident = User::factory()->resident()->create();

    $this->actingAs($employee)->post(route('portal.login.store'), [
        'email' => $resident->email,
        'password' => 'password',
    ])->assertRedirect(route('portal.account'));

    $this->assertAuthenticatedAs($employee, 'web');
    $this->assertAuthenticatedAs($resident, 'resident');
    $this->get(route('admin.dashboard'))->assertOk();
    $this->get(route('portal.account'))->assertOk();
});

test('resident logout preserves the employee session', function () {
    $employee = User::factory()->create(['role' => 'admin']);
    $resident = User::factory()->resident()->create();

    $this->actingAs($employee)->actingAs($resident, 'resident')
        ->post(route('portal.logout'))->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($employee, 'web');
    $this->assertGuest('resident');
    Auth::shouldUse('web');
    $this->get(route('admin.dashboard'))->assertOk();
});

test('employee logout preserves the resident session', function () {
    $employee = User::factory()->create(['role' => 'admin']);
    $resident = User::factory()->resident()->create();

    $this->actingAs($employee)->actingAs($resident, 'resident');
    Auth::shouldUse('web');
    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest('web');
    $this->assertAuthenticatedAs($resident, 'resident');
    $this->get(route('portal.account'))->assertOk();
});

test('staff login rejects resident credentials and resident login rejects staff credentials', function () {
    $employee = User::factory()->create(['role' => 'staff']);
    $resident = User::factory()->resident()->create();

    $this->post(route('login.store'), ['email' => $resident->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->post(route('portal.login.store'), ['email' => $employee->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('web');
    $this->assertGuest('resident');
});

test('simultaneous employee access does not widen resident request status access', function () {
    $employee = User::factory()->create(['role' => 'admin']);
    $resident = User::factory()->resident()->create();
    $otherResident = Resident::factory()->create();
    $otherRequest = $otherResident->documentRequests()->create([
        'reference_code' => 'REQ-OTHER-RESIDENT',
        'source' => 'online',
        'document_type' => 'Barangay Clearance',
        'full_name' => $otherResident->full_name,
        'address' => $otherResident->address,
        'status' => 'pending',
    ]);

    $this->actingAs($employee)->actingAs($resident, 'resident')
        ->getJson(route('portal.request.status', $otherRequest->reference_code))->assertNotFound();

    Auth::shouldUse('web');
    $this->get(route('admin.document-requests.show', $otherRequest))
        ->assertOk()->assertSee('REQ-OTHER-RESIDENT');
});
