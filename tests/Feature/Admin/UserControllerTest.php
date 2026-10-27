<?php

use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\FileMovementEvent;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

    $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $target))
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

    $this->actingAs($admin)->deleteJson(route('admin.users.destroy', $target))
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
