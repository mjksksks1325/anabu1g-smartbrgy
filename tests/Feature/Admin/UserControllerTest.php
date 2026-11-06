<?php

use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\FileMovementEvent;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;

it('requires authentication and administrator access to account management', function () {
    $this->getJson(route('admin.users.index'))->assertUnauthorized();
    $target = User::factory()->create(['role' => 'staff']);
    $this->deleteJson(route('admin.users.destroy', $target))->assertUnauthorized();

    $this->actingAs(User::factory()->create(['role' => 'staff']))
        ->getJson(route('admin.users.index'))->assertForbidden();
    $this->deleteJson(route('admin.users.destroy', $target))->assertForbidden();
});

it('removes a staff account while preserving history and queuing RPi credential revocation', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $target = User::factory()->create([
        'name' => 'Departing Clerk',
        'email' => 'departing@example.test',
        'role' => 'staff',
        'two_factor_secret' => 'encrypted-secret',
        'two_factor_recovery_codes' => 'encrypted-recovery-codes',
        'two_factor_confirmed_at' => now(),
    ]);
    $access = EmployeeCabinetAccess::factory()->create([
        'user_id' => $target->id,
        'rpi_employee_id' => 'EMP014',
        'is_active' => true,
        'rfid_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'face_enrollment_status' => EmployeeCabinetAccess::ENROLLED,
        'authorization_version' => 4,
    ]);
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'device-token')]);
    $history = FileMovementEvent::factory()->create(['user_id' => $target->id]);
    $incident = Incident::factory()->create(['reported_by' => $target->id]);

    $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $target), supervisorApproval())
        ->assertOk()
        ->assertJsonPath('pending_rpi_revocations', 1)
        ->assertJsonPath('rpi_mapping_missing', false)
        ->assertJsonPath('rpi_delivery_unavailable', false);

    $deletedUser = User::withTrashed()->findOrFail($target->id);
    expect($deletedUser->trashed())->toBeTrue()
        ->and($deletedUser->is_active)->toBeFalse()
        ->and($deletedUser->two_factor_secret)->toBeNull()
        ->and($deletedUser->two_factor_recovery_codes)->toBeNull()
        ->and($deletedUser->two_factor_confirmed_at)->toBeNull()
        ->and($access->fresh()->is_active)->toBeFalse()
        ->and($access->fresh()->authorization_version)->toBe(5);

    $this->assertDatabaseHas('file_movement_events', ['id' => $history->id, 'user_id' => $target->id]);
    expect($history->fresh()->user->name)->toBe('Departing Clerk')
        ->and($incident->fresh()->reporter->name)->toBe('Departing Clerk');
    $this->assertDatabaseHas('administrative_audits', [
        'user_id' => $admin->id,
        'target_user_id' => $target->id,
        'action' => 'admin.users.deleted',
    ]);
    $this->assertDatabaseHas('cabinet_credential_revocations', [
        'cabinet_device_id' => $cabinet->id,
        'rpi_employee_id' => 'EMP014',
        'remove_rfid' => true,
        'remove_face' => true,
        'acknowledged_at' => null,
    ]);

    $this->getJson(route('admin.users.index'))->assertOk()->assertJsonMissing(['email' => $target->email]);
    $this->withHeader('X-Device-Token', 'device-token')
        ->getJson(route('api.iot.cabinets.access-authorizations', $cabinet))
        ->assertExactJson(['authorizations' => []]);
});

it('prevents self deletion and deletion of super admin accounts', function (string $targetType) {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $target = $targetType === 'self'
        ? $admin
        : User::factory()->superAdmin()->create(['role' => 'admin']);

    $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $target), supervisorApproval())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user');

    expect(User::find($target->id))->not->toBeNull();
    $this->assertDatabaseEmpty('cabinet_credential_revocations');
})->with(['self', 'super admin']);

it('creates a persisted account with a hashed password and audit entry', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson(route('admin.users.store'), [
        'name' => 'Records Clerk', 'email' => 'clerk@example.test',
        'password' => 'SecurePassword123!', 'role' => 'staff', 'is_active' => true,
    ])->assertCreated();

    $user = User::findOrFail($response->json('id'));
    expect($user->role)->toBe('staff');
    expect(Hash::check('SecurePassword123!', $user->password))->toBeTrue();
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $admin->id, 'action' => 'admin.users.store']);
});

it('rejects missing fields invalid roles and duplicate emails', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson(route('admin.users.store'), [
        'name' => '', 'email' => $admin->email, 'password' => 'short', 'role' => 'superuser',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password', 'role', 'is_active']);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('administrative_audits', 0);
});

it('prevents staff from changing accounts', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $target = User::factory()->create();

    $this->actingAs($staff)->patchJson(route('admin.users.update', $target), [
        'name' => 'Changed', 'email' => $target->email, 'role' => 'admin', 'is_active' => false,
    ])->assertForbidden();
    expect($target->fresh()->name)->toBe($target->name);
});

it('updates accounts without replacing a blank password', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $target = User::factory()->create();
    $password = $target->password;

    $this->actingAs($admin)->patchJson(route('admin.users.update', $target), [
        'name' => 'Updated Clerk', 'email' => $target->email,
        'role' => 'staff', 'is_active' => false, 'password' => null,
    ])->assertOk();

    expect($target->fresh())->name->toBe('Updated Clerk')->is_active->toBeFalse()->password->toBe($password);
});

it('refuses a Captain any change to a Super Admin account and records the attempt', function (array $changes) {
    $superAdmin = User::factory()->superAdmin()->create(['role' => 'admin', 'email' => 'owner@example.test']);
    $captain = User::factory()->create(['role' => 'captain']);
    $original = $superAdmin->only(['name', 'email', 'password']);

    $this->actingAs($captain)->patchJson(route('admin.users.update', $superAdmin), [
        'name' => $superAdmin->name, 'email' => $superAdmin->email, 'role' => 'admin', 'is_active' => true, ...$changes,
    ])->assertForbidden();

    expect($superAdmin->fresh()->only(['name', 'email', 'password']))->toBe($original);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'security.super-admin-account.change-denied', 'user_id' => $captain->id, 'record' => (string) $superAdmin->id]);
    $this->assertDatabaseMissing('administrative_audits', ['action' => 'admin.users.update']);
})->with([
    'email and password' => [['email' => 'taken-over@example.test', 'password' => 'Takeover-Password-2026']],
    'password only' => [['password' => 'Takeover-Password-2026']],
    'name only' => [['name' => 'Renamed by captain']],
]);

it('lets a Super Admin manage their own account but not another Super Admin credentials', function () {
    $superAdmin = User::factory()->superAdmin()->create(['role' => 'admin', 'email' => 'owner@example.test']);
    $other = User::factory()->superAdmin()->create(['role' => 'admin', 'email' => 'second@example.test']);

    $this->actingAs($superAdmin)->patchJson(route('admin.users.update', $superAdmin), [
        'name' => 'Owner Renamed', 'email' => 'owner.new@example.test', 'role' => 'admin', 'is_active' => true, 'password' => 'Owner-New-Password-2026',
    ])->assertOk();
    expect($superAdmin->fresh())->email->toBe('owner.new@example.test')
        ->and(Hash::check('Owner-New-Password-2026', $superAdmin->fresh()->password))->toBeTrue();

    $before = $other->only(['email', 'password']);
    $this->patchJson(route('admin.users.update', $other), [
        'name' => $other->name, 'email' => 'hijack@example.test', 'role' => 'admin', 'is_active' => true, 'password' => 'Hijack-Password-2026',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    expect($other->fresh()->only(['email', 'password']))->toBe($before);

    $this->patchJson(route('admin.users.update', $other), [
        'name' => 'Second Admin', 'email' => $other->email, 'role' => 'admin', 'is_active' => true,
    ])->assertOk();
    expect($other->fresh()->name)->toBe('Second Admin');
});

it('still lets a Captain manage ordinary staff accounts and keeps staff out', function () {
    $captain = User::factory()->create(['role' => 'captain']);
    $staff = User::factory()->create(['role' => 'staff']);
    $superAdmin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $this->actingAs($captain)->patchJson(route('admin.users.update', $staff), [
        'name' => 'Clerk Updated', 'email' => $staff->email, 'role' => 'staff', 'is_active' => true,
    ])->assertOk();
    expect($staff->fresh()->name)->toBe('Clerk Updated');

    $this->actingAs($staff)->patchJson(route('admin.users.update', $superAdmin), [
        'name' => $superAdmin->name, 'email' => 'staff-takeover@example.test', 'role' => 'admin', 'is_active' => true, 'password' => 'Takeover-Password-2026',
    ])->assertForbidden();
    expect($superAdmin->fresh()->email)->not->toBe('staff-takeover@example.test');
});

it('prevents administrators from removing their own access', function (array $changes) {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);

    $this->actingAs($admin)->patchJson(route('admin.users.update', $admin), [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin', 'is_active' => true, ...$changes,
    ])->assertUnprocessable()->assertJsonValidationErrors('role');

    expect($admin->fresh())->role->toBe('admin')->is_active->toBeTrue();
})->with(['suspend self' => [['is_active' => false]], 'demote self' => [['role' => 'staff']]]);

it('does not expose authentication secrets in the account list', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin', 'two_factor_secret' => 'private-secret']);

    $this->actingAs($admin)->getJson(route('admin.users.index'))
        ->assertOk()->assertJsonPath('users.0.email', $admin->email)
        ->assertJsonMissingPath('users.0.password')->assertJsonMissingPath('users.0.two_factor_secret');
});

it('denies suspended accounts even when they already have a session', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->actingAs($user)->getJson(route('staff.dashboard.summary'))->assertUnauthorized();
    $this->assertGuest();
});

it('does not authenticate a suspended account with a correct password', function () {
    $user = User::factory()->create(['is_active' => false]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->assertDatabaseEmpty('administrative_audits');
});

it('exports personnel accounts as a branded excel file for administrators only', function () {
    $staff = User::factory()->create(['role' => 'staff', 'name' => 'Export Staff']);
    $this->actingAs($staff)->get(route('admin.users.export'))->assertForbidden();

    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)->get(route('admin.users.export'))
        ->assertOk()
        ->assertDownload('barangay-users-'.today()->toDateString().'.xlsx');

    expect(spreadsheetSheetXml($response))->toContain('Barangay Users')->toContain('Export Staff')->toContain($staff->email);
});

it('records suspension, reactivation, a password set by an administrator and cabinet access as separate security events without secrets', function () {
    $captain = User::factory()->create(['role' => 'captain']);
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['dashboard.view']]);
    $payload = fn (array $changes = []) => ['name' => $staff->name, 'email' => $staff->email, 'role' => 'staff', 'is_active' => true, 'staff_permissions' => ['dashboard.view'], ...$changes];
    $this->actingAs($captain);

    $this->patchJson(route('admin.users.update', $staff), $payload(['is_active' => false]))->assertOk();
    $this->patchJson(route('admin.users.update', $staff), $payload(['password' => 'Changed-By-Admin-2026']))->assertOk();
    $this->patchJson(route('admin.users.update', $staff), $payload(['smart_cabinet_access' => true, 'rpi_employee_id' => 'EMP-77']))->assertOk();
    $this->patchJson(route('admin.users.update', $staff), $payload(['smart_cabinet_access' => false]))->assertOk();
    $this->patchJson(route('admin.users.update', $staff), $payload(['name' => 'Renamed Only']))->assertOk();

    $events = DB::table('administrative_audits')->where('target_user_id', $staff->id)->where('user_id', $captain->id)->orderBy('id')->pluck('action')->all();
    expect($events)->toBe(['admin.users.suspended', 'admin.users.reactivated', 'admin.users.password-set', 'admin.users.cabinet-access-enabled', 'admin.users.cabinet-access-disabled']);
    expect(DB::table('administrative_audits')->get()->toJson())->not->toContain('Changed-By-Admin-2026');
});

it('emails the owner a reset link the administrator never sees, refuses the wrong targets and audits it', function () {
    Notification::fake();
    $captain = User::factory()->create(['role' => 'captain']);
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['dashboard.view']]);

    $this->actingAs($captain)->postJson(route('admin.users.password-reset-link', $staff))->assertOk()
        ->assertJsonPath('message', 'A password reset link was sent to the account owner\'s email address. It expires in 60 minutes.')
        ->assertJsonMissingPath('token');
    Notification::assertSentTo($staff, ResetPassword::class);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.users.password-reset-link', 'user_id' => $captain->id, 'record' => (string) $staff->id]);
    expect(DB::table('administrative_audits')->get()->toJson())->not->toContain(DB::table('password_reset_tokens')->value('token'));
    expect(Hash::check('password', $staff->fresh()->password))->toBeTrue();

    $this->postJson(route('admin.users.password-reset-link', $staff))->assertUnprocessable()
        ->assertJsonPath('errors.user.0', 'A reset link was sent to this account recently. Wait a minute before sending another.');
    $this->postJson(route('admin.users.password-reset-link', User::factory()->superAdmin()->create()))->assertForbidden();
    $this->postJson(route('admin.users.password-reset-link', User::factory()->resident()->create()))->assertForbidden();
    $this->postJson(route('admin.users.password-reset-link', User::factory()->create(['role' => 'staff', 'is_active' => false])))->assertUnprocessable()
        ->assertJsonPath('errors.user.0', 'Reactivate this account before sending a password reset link.');
    $this->actingAs($staff)->postJson(route('admin.users.password-reset-link', $captain))->assertForbidden();
});

it('tells the administrator when the reset email could not be sent', function () {
    Exceptions::fake();
    useFailingMailer();
    $captain = User::factory()->create(['role' => 'captain']);
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['dashboard.view']]);

    $this->actingAs($captain)->postJson(route('admin.users.password-reset-link', $staff))->assertUnprocessable()
        ->assertJsonPath('errors.user.0', 'The reset link could not be sent. Check the mail settings and try again.');
    Exceptions::assertReported(TransportException::class);
});

it('lets a Super Admin send a reset link only to their own Super Admin account', function () {
    Notification::fake();
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)->postJson(route('admin.users.password-reset-link', $super))->assertOk();
    $this->postJson(route('admin.users.password-reset-link', User::factory()->superAdmin()->create()))->assertForbidden();
    Notification::assertSentToTimes($super, ResetPassword::class, 1);
});

it('records a completed reset and keeps two-factor authentication in place', function () {
    $staff = User::factory()->withTwoFactor()->create(['role' => 'staff', 'staff_permissions' => ['dashboard.view']]);
    $secret = $staff->two_factor_secret;
    $token = Password::broker()->createToken($staff);

    $this->post(route('password.update'), ['token' => $token, 'email' => $staff->email, 'password' => 'Owner-Chosen-2026!', 'password_confirmation' => 'Owner-Chosen-2026!'])->assertSessionHasNoErrors();

    expect(Hash::check('Owner-Chosen-2026!', $staff->fresh()->password))->toBeTrue()
        ->and($staff->fresh()->two_factor_secret)->toBe($secret);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'auth.password-reset-completed', 'user_id' => $staff->id]);
    expect(DB::table('administrative_audits')->get()->toJson())->not->toContain('Owner-Chosen-2026!')->not->toContain($token);
});

it('describes the staff password rule the server enforces: twelve characters, with stronger choices only recommended', function () {
    $admin = User::factory()->superAdmin()->create(['role' => 'admin']);
    $content = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML($content);
    $xpath = new DOMXPath($document);

    // The new-account wording only; the edit wording is set by admin.js when an account is opened.
    expect(trim($xpath->query('//*[@id="adduser-pass-label"]')->item(0)->textContent))->toBe('Required. At least 12 characters.')
        ->and($content)->not->toContain('Leave blank when editing')
        ->and(collect($xpath->query('//*[@id="adduser-password-guidance"]/li[@data-required]'))->map(fn (DOMElement $rule): string => $rule->getAttribute('data-password-rule'))->all())->toBe(['length'])
        ->and(collect($xpath->query('//*[@id="adduser-password-guidance"]/li[not(@data-required)]'))->map(fn (DOMElement $rule): string => $rule->getAttribute('data-password-rule'))->all())->toBe(['uppercase', 'lowercase', 'number', 'symbol'])
        ->and($xpath->query('//input[@id="adduser-password" and @minlength="12" and contains(@aria-describedby, "adduser-password-guidance")]'))->toHaveCount(1);

    // The guidance matches the policy: eleven characters are refused; twelve lowercase letters are accepted.
    $this->postJson(route('admin.users.store'), ['name' => 'Short', 'email' => 'short@example.test', 'password' => 'abcdefghijk', 'role' => 'staff', 'is_active' => true])
        ->assertUnprocessable()->assertJsonValidationErrors(['password']);
    $this->postJson(route('admin.users.store'), ['name' => 'Long', 'email' => 'long@example.test', 'password' => 'abcdefghijkl', 'role' => 'staff', 'is_active' => true])
        ->assertCreated();
});
