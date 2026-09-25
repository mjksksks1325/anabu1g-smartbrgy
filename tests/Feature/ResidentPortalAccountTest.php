<?php

use App\Models\DocumentRequest;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

function portalRegistrationResident(array $attributes = []): Resident
{
    return Resident::factory()->create([
        'portal_registration_hash' => hash('sha256', 'private-test-activation-code'),
        'portal_registration_expires_at' => now()->addDay(),
        ...$attributes,
    ]);
}

function portalAccountInput(array $attributes = []): array
{
    return ['email' => 'resident@example.test', 'password' => 'ResidentPassword123!', 'password_confirmation' => 'ResidentPassword123!', ...$attributes];
}

function portalRequestFor(Resident $resident, string $code): DocumentRequest
{
    return $resident->documentRequests()->create([
        'reference_code' => $code, 'source' => 'online', 'document_type' => 'Barangay Clearance',
        'full_name' => $resident->full_name, 'address' => $resident->address, 'status' => 'pending',
    ]);
}

test('registration links an existing resident without changing their record or trusting account privileges', function () {
    $resident = portalRegistrationResident();
    $this->post(route('portal.register.verify'), ['resident_number' => strtolower($resident->resident_number), 'activation_code' => 'private-test-activation-code'])
        ->assertRedirect(route('portal.register'));
    $this->get(route('portal.register'))->assertSee('Step 4 of 4');

    $this->post(route('portal.register.store'), portalAccountInput(['role' => 'admin', 'resident_id' => 999, 'name' => 'Someone else', 'is_active' => false]))
        ->assertRedirect(route('portal.login'));

    $this->assertDatabaseCount('residents', 1);
    $account = User::query()->sole();
    expect($account->resident_id)->toBe($resident->id);
    expect($account->role)->toBe('resident');
    expect($account->name)->toBe($resident->full_name);
    expect(Hash::check('ResidentPassword123!', $account->password))->toBeTrue();
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $account->id, 'action' => 'portal.account.registered', 'record' => null]);
    $this->assertGuest();
});

test('unverified clients cannot create an account or choose a resident id', function () {
    $resident = portalRegistrationResident();
    $this->post(route('portal.register.store'), portalAccountInput(['resident_id' => $resident->id]))
        ->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseEmpty('users');
    $this->assertDatabaseCount('residents', 1);
});

test('verification denies unknown inactive archived expired and incorrect codes without revealing identity', function (string $condition) {
    $resident = portalRegistrationResident();
    $number = $resident->resident_number;
    $code = 'private-test-activation-code';
    if ($condition === 'unknown') {
        $number = 'ANB-UNKNOWN';
    }
    if ($condition === 'inactive') {
        $resident->update(['status' => 'inactive']);
    }
    if ($condition === 'archived') {
        $resident->delete();
    }
    if ($condition === 'expired') {
        $resident->forceFill(['portal_registration_expires_at' => now()->subMinute()])->save();
    }
    if ($condition === 'wrong') {
        $code = 'wrong-code';
    }

    $this->post(route('portal.register.verify'), ['resident_number' => $number, 'activation_code' => $code])
        ->assertRedirect(route('portal.registration.denied'))->assertSessionMissing('resident_verification');
    $this->get(route('portal.registration.denied'))->assertSee('Hindi natapos ang registration')
        ->assertSee('Pumunta sa Barangay Anabu I-G Hall')->assertDontSee($resident->full_name)->assertDontSee($resident->date_of_birth->toDateString());
    $this->assertDatabaseEmpty('users');
})->with(['unknown', 'inactive', 'archived', 'expired', 'wrong']);

test('registration rechecks eligibility expiration and code rotation after verification', function (string $condition) {
    $resident = portalRegistrationResident();
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'private-test-activation-code']);
    if ($condition === 'archived') {
        $resident->delete();
    }
    if ($condition === 'inactive') {
        $resident->update(['status' => 'inactive']);
    }
    if ($condition === 'rotated') {
        $resident->forceFill(['portal_registration_hash' => hash('sha256', 'replacement-code')])->save();
    }
    if ($condition === 'expired') {
        $this->travel(11)->minutes();
    }

    $this->post(route('portal.register.store'), portalAccountInput())->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseEmpty('users');
})->with(['archived', 'inactive', 'rotated', 'expired']);

test('an already linked record offers login and recovery without exposing its email', function () {
    $resident = portalRegistrationResident();
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'private-test-activation-code']);
    $this->post(route('portal.register.store'), portalAccountInput())->assertRedirect(route('portal.login'));
    $account = User::query()->sole();
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'private-test-activation-code'])
        ->assertRedirect(route('portal.registration.denied'))->assertSessionHas('existing_account', true);
    $this->get(route('portal.registration.denied'))->assertSee('May online account na para sa resident record na ito')
        ->assertSee(route('portal.login'))->assertSee(route('password.request'))->assertDontSee($account->email);
    $this->assertDatabaseCount('users', 1);
});

test('the database rejects a second account even when application checks are bypassed', function () {
    $account = User::factory()->resident()->create();
    expect(fn () => User::factory()->resident()->create(['resident_id' => $account->resident_id]))
        ->toThrow(UniqueConstraintViolationException::class);
    $this->assertDatabaseCount('users', 1);
});

test('verification proof cannot be reused after a successful registration', function () {
    $resident = portalRegistrationResident();
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'private-test-activation-code']);
    $proof = session('resident_verification');
    $this->post(route('portal.register.store'), portalAccountInput())->assertRedirect(route('portal.login'));

    $this->withSession(['resident_verification' => $proof])->post(route('portal.register.store'), portalAccountInput(['email' => 'another@example.test']))
        ->assertRedirect(route('portal.registration.denied'));
    $this->assertDatabaseCount('users', 1);
});

test('public verification attempts are rate limited', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('portal.register.verify'), ['resident_number' => 'UNKNOWN', 'activation_code' => 'unknown'])->assertRedirect();
    }
    $this->post(route('portal.register.verify'), ['resident_number' => 'UNKNOWN', 'activation_code' => 'unknown'])->assertTooManyRequests();
});

test('staff can issue a private expiring code but no activation secrets appear in resident feeds or audits', function () {
    $resident = Resident::factory()->create();
    $this->actingAs(User::factory()->create(['role' => 'staff']));
    $response = $this->postJson(route('admin.residents.portal-activation', $resident))->assertOk();
    $code = $response->json('activation_code');
    expect($resident->fresh()->portal_registration_hash)->toBe(hash('sha256', $code));
    $this->getJson(route('admin.residents.index'))->assertDontSee($code)->assertDontSee(hash('sha256', $code));
    $this->getJson(route('admin.residents.show', $resident))->assertJsonPath('portal_account.registered', false)->assertDontSee(hash('sha256', $code));
    $this->getJson(route('admin.audit.index'))->assertForbidden();
    $this->assertDatabaseMissing('administrative_audits', ['record' => $code]);
    $this->assertDatabaseCount('residents', 1);
});

test('viewer and resident accounts cannot issue activation codes', function (string $role) {
    $resident = Resident::factory()->create();
    $user = $role === 'resident' ? User::factory()->resident()->create() : User::factory()->create(['role' => $role]);
    $response = $this->actingAs($user, $role === 'resident' ? 'resident' : 'web')
        ->postJson(route('admin.residents.portal-activation', $resident));
    $response->assertForbidden();
    expect($resident->fresh()->portal_registration_hash)->toBeNull();
})->with(['viewer', 'resident']);

test('only administrators can suspend portal accounts and staff cannot convert them into employees', function () {
    $residentUser = User::factory()->resident()->create();
    $this->actingAs(User::factory()->create(['role' => 'staff']))->patchJson(route('admin.residents.portal-account', $residentUser->resident), ['is_active' => false])->assertForbidden();
    $this->actingAs(User::factory()->superAdmin()->create())->patchJson(route('admin.residents.portal-account', $residentUser->resident), ['is_active' => false])->assertOk();
    expect($residentUser->fresh()->is_active)->toBeFalse();
    $this->patchJson(route('admin.users.update', $residentUser), ['name' => $residentUser->name, 'email' => $residentUser->email, 'role' => 'admin', 'is_active' => true])->assertForbidden();
    expect($residentUser->fresh()->role)->toBe('resident');
});

test('guest and employee accounts cannot submit resident portal requests', function () {
    $this->postJson(route('portal.request.store'), [])->assertUnauthorized();
    foreach (['admin', 'staff', 'viewer'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))->postJson(route('portal.request.store'), [])->assertUnauthorized();
    }
    $this->assertDatabaseEmpty('document_requests');
});

test('a resident login uses the portal and preserves login auditing', function () {
    $account = User::factory()->resident()->create();
    $this->post(route('portal.login.store'), ['email' => $account->email, 'password' => 'password'])
        ->assertRedirect(route('portal.account'));
    $this->assertAuthenticatedAs($account, 'resident');
    $this->get(route('portal.account'))->assertOk()->assertSee('My requests');
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $account->id, 'action' => 'auth.login', 'record' => 'resident_portal']);
});

test('resident accounts cannot read administrative data', function (string $route) {
    $this->actingAs(User::factory()->resident()->create(), 'resident')->getJson(route($route))->assertForbidden();
})->with(['admin.dashboard', 'admin.dashboard.summary', 'admin.residents.index', 'admin.users.index', 'admin.voter-registrations.index', 'admin.incidents.index', 'admin.audit.index', 'admin.issued-certificates.index', 'admin.document-requests.index', 'admin.puroks.index']);

test('request history and status are scoped to the authenticated resident', function () {
    $user = User::factory()->resident()->create();
    $own = portalRequestFor($user->resident, 'REQ-OWN');
    $other = portalRequestFor(Resident::factory()->create(), 'REQ-OTHER');
    $response = $this->actingAs($user, 'resident')->get(route('portal.account', ['resident_id' => $other->resident_id]));
    $response->assertSee('REQ-OWN')->assertDontSee('REQ-OTHER');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson(route('portal.request.status', $own->reference_code))->assertOk();
    $this->getJson(route('portal.request.status', $other->reference_code))->assertNotFound();
});

test('request history explains each status, rejection reason, and collection steps', function () {
    $user = User::factory()->resident()->create();
    portalRequestFor($user->resident, 'REQ-READY')->update(['status' => 'ready_for_release']);
    portalRequestFor($user->resident, 'REQ-REJECTED')->update(['status' => 'rejected', 'rejection_reason' => 'Kulang ang <b>detalye</b> ng request.']);
    portalRequestFor($user->resident, 'REQ-PENDING');

    $this->actingAs($user, 'resident')->get(route('portal.account'))
        ->assertOk()
        ->assertSeeInOrder(['REQ-READY', 'Ready for release', 'Paano kunin', 'Dalhin ang valid ID at ang reference number na REQ-READY'])
        ->assertSeeInOrder(['REQ-REJECTED', 'Not approved', 'Dahilan', 'Kulang ang &lt;b&gt;detalye&lt;/b&gt; ng request.'], false)
        ->assertSeeInOrder(['REQ-PENDING', 'Received', 'Hinihintay pa ang review ng barangay staff'])
        ->assertDontSee('<b>detalye</b>', false);
});

test('a rejected login shows its message beside the email field', function () {
    $this->from(route('portal.login'))
        ->post(route('portal.login.store'), ['email' => 'nobody@example.test', 'password' => 'wrong-password'])
        ->assertRedirect(route('portal.login'));

    $this->get(route('portal.login'))
        ->assertSee('aria-invalid="true" aria-describedby="resident-email-error"', false)
        ->assertSee('<p class="field-error" id="resident-email-error">', false);
});

test('request history marks requests that need resident attention', function () {
    $user = User::factory()->resident()->create();
    portalRequestFor($user->resident, 'REQ-READY')->update(['status' => 'ready_for_release']);
    portalRequestFor($user->resident, 'REQ-REJECTED')->update(['status' => 'rejected']);
    portalRequestFor($user->resident, 'REQ-RELEASED')->update(['status' => 'released']);

    $this->actingAs($user, 'resident')->get(route('portal.account'))
        ->assertOk()
        ->assertSeeInOrder(['class="request-item is-ready"', 'REQ-READY', 'status status-ready'], false)
        ->assertSeeInOrder(['class="request-item is-rejected"', 'REQ-REJECTED', 'status status-rejected'], false)
        ->assertSeeInOrder(['REQ-RELEASED', 'status status-done'], false);
});

test('request history offers a first request when the resident has none', function () {
    $this->actingAs(User::factory()->resident()->create(), 'resident')->get(route('portal.account'))
        ->assertOk()->assertSee('Wala pang request')->assertDontSee('class="request-list"', false);
});

test('inactive archived suspended and unlinked resident accounts cannot submit requests', function (string $condition) {
    $user = User::factory()->resident()->create();
    if ($condition === 'inactive') {
        $user->resident->update(['status' => 'inactive']);
    }
    if ($condition === 'archived') {
        $user->resident->delete();
    }
    if ($condition === 'suspended') {
        $user->forceFill(['is_active' => false])->save();
    }
    if ($condition === 'unlinked') {
        $user->forceFill(['resident_id' => null])->save();
    }
    $response = $this->actingAs($user->fresh(), 'resident')->postJson(route('portal.request.store'), ['document_type' => 'Barangay Clearance', 'purpose' => 'Employment']);
    if ($condition === 'suspended') {
        $response->assertUnauthorized();
    } else {
        $response->assertForbidden();
    }
    $this->assertDatabaseEmpty('document_requests');
})->with(['inactive', 'archived', 'suspended', 'unlinked']);

test('archive and restore retain linked accounts and history while enforcing current eligibility', function () {
    $account = User::factory()->resident()->create();
    $resident = $account->resident;
    portalRequestFor($resident, 'REQ-RETAINED');
    $staff = User::factory()->create(['role' => 'staff']);
    $this->actingAs($staff)->deleteJson(route('admin.residents.destroy', $resident))->assertOk();
    $this->getJson(route('admin.residents.index'))->assertJsonCount(0, 'data');
    $this->getJson(route('admin.residents.index', ['status' => 'archived']))->assertJsonPath('data.0.id', $resident->id);
    $this->actingAs($account->fresh(), 'resident')->get(route('portal.account'))->assertForbidden()->assertSee('Please visit Barangay');
    $this->actingAs($staff)->patchJson(route('admin.residents.restore', $resident->id))->assertOk();
    $this->actingAs($account->fresh(), 'resident')->get(route('portal.account'))->assertOk()->assertSee('REQ-RETAINED');
    $this->assertDatabaseCount('document_requests', 1);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.residents.restore']);
});

test('logout invalidates resident access and private pages cannot be cached', function () {
    $user = User::factory()->resident()->create();
    $this->actingAs($user, 'resident')->get(route('portal.profile'))->assertSee('data-resident-private', false);
    $this->post(route('portal.logout'))->assertRedirect(route('home'));
    $this->assertGuest('resident');
    $this->getJson(route('portal.identity'))->assertUnauthorized();
    $this->get(route('portal.account'))->assertRedirect(route('portal.login'));
});

test('public services derive supported fees from CertificateType and request links lead to resident login', function () {
    $this->get(route('portal.information'))->assertOk()->assertSee('Barangay officials')->assertSee('Hinihintay pa ang opisyal na listahan');
    $this->get(route('portal.request.create', ['service' => 'BC']))->assertRedirect(route('portal.login', ['next' => 'request', 'service' => 'BC']));
});

test('resident accounts cannot use employee settings or change their master record', function () {
    $user = User::factory()->resident()->create();
    $this->actingAs($user, 'resident')->get(route('profile.edit'))->assertForbidden();
    $this->get(route('appearance.edit'))->assertForbidden();
    $this->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertForbidden();
    $this->patchJson(route('admin.residents.update', $user->resident), ['first_name' => 'Forged'])->assertForbidden();
    expect($user->resident->fresh()->first_name)->not->toBe('Forged');
});

test('registration validates credentials without flashing verification codes or passwords', function () {
    $resident = portalRegistrationResident();
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => str_repeat('secret', 30)])
        ->assertSessionHasErrors('activation_code')->assertSessionMissing('_old_input.activation_code')->assertSessionMissing('_old_input.resident_number');
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'private-test-activation-code']);
    $this->post(route('portal.register.store'), portalAccountInput(['password' => 'short', 'password_confirmation' => 'different']))
        ->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
    $this->assertDatabaseEmpty('users');
});

test('malformed registration identity fields return validation errors', function () {
    $this->postJson(route('portal.register.verify'), ['resident_number' => ['not-a-string'], 'activation_code' => ['not-a-code']])
        ->assertUnprocessable()->assertJsonValidationErrors(['resident_number', 'activation_code']);
    $this->postJson(route('portal.register.store'), portalAccountInput(['email' => ['not-an-email']]))
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertDatabaseEmpty('users');
});

test('case variants of an existing account email cannot register a second user', function () {
    User::factory()->create(['email' => 'Already@Example.test']);
    $resident = portalRegistrationResident();
    $this->post(route('portal.register.verify'), ['resident_number' => $resident->resident_number, 'activation_code' => 'private-test-activation-code']);
    $this->post(route('portal.register.store'), portalAccountInput(['email' => '  ALREADY@example.TEST  ']))->assertSessionHasErrors('email');
    $this->assertDatabaseCount('users', 1);
    expect($resident->portalAccount()->exists())->toBeFalse();
});

test('a selected public service survives resident login and the authenticated session is regenerated', function () {
    $account = User::factory()->resident()->create();
    $this->get(route('portal.login', ['service' => 'BBC']))->assertOk();
    $sessionId = session()->getId();
    $this->post(route('portal.login.store'), ['email' => strtoupper($account->email), 'password' => 'password'])
        ->assertRedirect(route('portal.request.create', ['service' => 'BBC']));
    expect(session()->getId())->not->toBe($sessionId);
});

test('suspended resident login fails without recording a successful login', function () {
    $account = User::factory()->resident()->create(['is_active' => false]);
    $this->post(route('portal.login.store'), ['email' => $account->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest('resident');
    $this->assertDatabaseMissing('administrative_audits', ['action' => 'auth.login']);
    $this->actingAs($account, 'resident')->get(route('portal.account'))->assertRedirect(route('portal.login'));
    $this->assertGuest('resident');
});

test('portal account suspension and reactivation have distinct safe audit events', function () {
    $account = User::factory()->resident()->create();
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->patchJson(route('admin.residents.portal-account', $account->resident), ['is_active' => false])->assertOk();
    $this->patchJson(route('admin.residents.portal-account', $account->resident), ['is_active' => true])->assertOk();
    foreach (['suspended', 'reactivated'] as $action) {
        $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.residents.portal-account.'.$action, 'type' => 'security', 'record' => (string) $account->resident_id]);
    }
    $this->getJson(route('admin.users.index'))->assertDontSee($account->email);
    $this->getJson(route('admin.residents.show', $account->resident))
        ->assertJsonPath('portal_account.registered', true)->assertJsonPath('portal_account.is_active', true)
        ->assertDontSee($account->email)->assertDontSee($account->password)->assertDontSee($account->remember_token);
});

test('resident password recovery reuses Fortify without creating another account', function () {
    Notification::fake();
    $account = User::factory()->resident()->create();
    $this->post(route('password.email'), ['email' => $account->email])->assertSessionHasNoErrors();
    Notification::assertSentTo($account, ResetPassword::class, function ($notification) use ($account) {
        $this->post(route('password.update'), [
            'email' => $account->email, 'token' => $notification->token,
            'password' => 'RecoveredPassword123!', 'password_confirmation' => 'RecoveredPassword123!',
        ])->assertSessionHasNoErrors();

        return true;
    });
    expect(Hash::check('RecoveredPassword123!', $account->fresh()->password))->toBeTrue();
    expect($account->fresh()->resident_id)->toBe($account->resident_id);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('residents', 1);
});

test('activation code rotation invalidates the previous code and pending proof', function () {
    $resident = portalRegistrationResident();
    $staff = User::factory()->create(['role' => 'staff']);
    $oldHash = $resident->portal_registration_hash;
    $response = $this->actingAs($staff)->postJson(route('admin.residents.portal-activation', $resident))->assertOk();
    expect($resident->fresh()->portal_registration_hash)->not->toBe($oldHash);
    expect($resident->fresh()->portal_registration_hash)->toBe(hash('sha256', $response->json('activation_code')));
    expect($resident->fresh()->portal_registration_expires_at->isFuture())->toBeTrue();
});

test('guests cannot retrieve status or protected request attachments', function () {
    $request = portalRequestFor(Resident::factory()->create(), 'REQ-PRIVATE');
    $this->getJson(route('portal.request.status', $request->reference_code))->assertUnauthorized();
    $this->getJson(route('admin.document-requests.attachment', $request))->assertUnauthorized();
});
