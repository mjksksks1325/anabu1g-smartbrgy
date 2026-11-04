<?php

use App\Models\BarangayProtectionOrder;
use App\Models\Incident;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** One restricted VAWC case (with a BPO and an attachment) and one ordinary case, both linked to the same resident. */
function vawcCaseFixture(): array
{
    Storage::fake('local');
    Storage::disk('local')->put('incident-attachments/vawc.pdf', 'confidential evidence');
    $resident = Resident::factory()->create();
    $restricted = Incident::factory()->create(['incident_type' => 'VAWC', 'is_sensitive' => true, 'status' => 'open', 'respondent_resident_id' => $resident->id,
        'attachments' => [['path' => 'incident-attachments/vawc.pdf', 'name' => 'evidence.pdf', 'mime' => 'application/pdf', 'size' => 21]]]);
    $order = BarangayProtectionOrder::factory()->create(['incident_id' => $restricted->id]);
    $ordinary = Incident::factory()->create(['incident_type' => 'Noise Complaint', 'is_sensitive' => false, 'status' => 'open', 'respondent_resident_id' => $resident->id]);

    return [$restricted, $order, $ordinary, $resident];
}

function vawcAccountPayload(User $user, array $permissions, string $role = 'staff'): array
{
    return ['name' => $user->name, 'email' => $user->email, 'role' => $role, 'is_active' => true, 'staff_permissions' => $permissions];
}

it('keeps the current behaviour while explicit VAWC assignment is off', function (string $role) {
    config(['access.vawc_explicit_assignment' => false]);
    [$restricted] = vawcCaseFixture();
    $administrator = $role === 'super admin' ? User::factory()->superAdmin()->create() : User::factory()->create(['role' => 'captain']);

    $this->actingAs($administrator)->getJson(route('staff.incidents.show', $restricted))->assertOk();
    expect($administrator->hasPermission('vawc.view'))->toBeTrue();
})->with(['super admin', 'captain']);

it('hides every trace of restricted cases from administrators without an explicit assignment once it is on', function (string $role) {
    config(['access.vawc_explicit_assignment' => true]);
    [$restricted, $order, $ordinary, $resident] = vawcCaseFixture();
    $administrator = $role === 'super admin' ? User::factory()->superAdmin()->create() : User::factory()->create(['role' => 'captain']);
    $this->actingAs($administrator);

    $this->getJson(route('staff.incidents.index'))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $ordinary->id)->assertJsonMissingPath('summary.protection_orders');
    $this->getJson(route('staff.incidents.index', ['resident_id' => $resident->id]))->assertOk()->assertJsonPath('total', 1)->assertDontSee($restricted->incident_number);
    $this->getJson(route('staff.incidents.index', ['search' => $restricted->incident_number]))->assertOk()->assertJsonPath('total', 0);
    $this->getJson(route('staff.incidents.show', $restricted))->assertForbidden();
    $this->get(route('staff.incidents.attachments.show', [$restricted, 0]))->assertForbidden()->assertDontSee('confidential evidence');
    $this->patchJson(route('staff.incidents.update', $restricted), ['status' => 'resolved'])->assertForbidden();
    $this->getJson(route('staff.protection-orders.index'))->assertForbidden();
    $this->getJson(route('staff.protection-orders.show', $order))->assertForbidden();
    $this->getJson(route('staff.dashboard.summary'))->assertOk()->assertDontSee($restricted->incident_number);
    preg_match('/window\.PERSONNEL_PERMISSIONS = (.+);/', $this->get(route('staff.incidents.index'))->assertOk()->getContent(), $menuPermissions);
    expect($menuPermissions[1])->toContain('incidents.view')->not->toContain('vawc.');
    // Ordinary work and administration are unaffected.
    $this->getJson(route('staff.incidents.show', $ordinary))->assertOk();
    $this->getJson(route('admin.users.index'))->assertOk();
})->with(['super admin', 'captain']);

it('lets a designated Captain and VAW desk staff handle restricted cases once it is on', function () {
    config(['access.vawc_explicit_assignment' => true]);
    [$restricted, $order] = vawcCaseFixture();
    $captain = User::factory()->create(['role' => 'captain', 'staff_permissions' => ['vawc.view', 'vawc.update']]);
    $desk = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['vawc.view']]);

    foreach ([$captain, $desk] as $designated) {
        $this->actingAs($designated)->getJson(route('staff.incidents.show', $restricted))->assertOk();
        $this->get(route('staff.incidents.attachments.show', [$restricted, 0]))->assertOk();
        $this->getJson(route('staff.protection-orders.show', $order))->assertOk();
    }
    expect($captain->hasPermission('vawc.archive'))->toBeFalse()
        ->and($captain->hasPermission('records.archive'))->toBeTrue();
});

it('lets only the Super Admin grant or remove VAWC access, never on their own account, and audits it', function () {
    $super = User::factory()->superAdmin()->create();
    $captain = User::factory()->create(['role' => 'captain']);
    $staff = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['incidents.view']]);

    $this->actingAs($captain)->patchJson(route('admin.users.update', $staff), vawcAccountPayload($staff, ['incidents.view', 'vawc.view']))
        ->assertUnprocessable()->assertJsonPath('errors.staff_permissions.0', 'Only the Super Admin can grant or remove VAWC/BPO access.');
    $this->patchJson(route('admin.users.update', $captain), vawcAccountPayload($captain, ['vawc.view'], 'captain'))
        ->assertUnprocessable()->assertJsonPath('errors.staff_permissions.0', 'Only the Super Admin can grant or remove VAWC/BPO access.');
    expect($staff->fresh()->staff_permissions)->toBe(['incidents.view']);

    $this->actingAs($super)->patchJson(route('admin.users.update', $captain), vawcAccountPayload($captain, ['vawc.view', 'vawc.update'], 'captain'))->assertOk();
    expect($captain->fresh()->staff_permissions)->toBe(['vawc.update', 'vawc.view']);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.users.vawc-access-changed', 'user_id' => $super->id, 'target_user_id' => $captain->id, 'after_assignments' => json_encode(['vawc.update', 'vawc.view'])]);

    $this->patchJson(route('admin.users.update', $super), [...vawcAccountPayload($super, ['vawc.view'], 'admin')])
        ->assertUnprocessable()->assertJsonPath('errors.staff_permissions.0', 'You cannot change your own VAWC/BPO access.');
    $this->patchJson(route('admin.users.update', $captain), vawcAccountPayload($captain, ['vawc.view', 'records.view'], 'captain'))
        ->assertUnprocessable()->assertJsonPath('errors.staff_permissions.0', 'A Captain account already has every other module; only VAWC/BPO access can be assigned to it.');
});

it('lets a Captain keep editing staff who already hold VAWC access without changing it', function () {
    $captain = User::factory()->create(['role' => 'captain']);
    $desk = User::factory()->create(['role' => 'staff', 'staff_permissions' => ['dashboard.view', 'vawc.view', 'vawc.update']]);

    $this->actingAs($captain)->patchJson(route('admin.users.update', $desk), [...vawcAccountPayload($desk, ['dashboard.view', 'demographics.view', 'vawc.view', 'vawc.update']), 'name' => 'Renamed Desk Officer'])->assertOk();

    expect($desk->fresh())->name->toBe('Renamed Desk Officer')->staff_permissions->toBe(['dashboard.view', 'demographics.view', 'vawc.update', 'vawc.view']);
    expect(DB::table('administrative_audits')->where('action', 'admin.users.vawc-access-changed')->exists())->toBeFalse();
});
