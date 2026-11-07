<?php

use App\Actions\RecordResidentVerificationChange;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function verificationAuditPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Maria', 'middle_name' => 'Reyes', 'last_name' => 'Santos', 'suffix' => null,
        'date_of_birth' => '1990-05-14', 'gender' => 'Female', 'civil_status' => 'Married',
        'purok' => 'Purok 1 - Sampaguita', 'address' => 'Block 1, Lot 2, Anabu I-G', 'contact_number' => '09171234567',
        'residency_type' => 'Homeowner', 'special_groups' => [], 'status' => 'active',
        'is_in_good_standing' => true, 'is_verified_indigent' => false, 'nationality' => null,
        ...$overrides,
    ];
}

function verificationAudits(): array
{
    return DB::table('administrative_audits')->where('action', RecordResidentVerificationChange::ACTION)->orderBy('id')->get()
        ->map(fn (object $row): array => ['user_id' => $row->user_id, 'record' => $row->record, 'changes' => json_decode($row->changed_fields, true)])->all();
}

test('changing indigency verification or good standing records who changed it, from what, to what', function () {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create(verificationAuditPayload(['date_of_birth' => '1990-05-14']));

    $this->actingAs($staff)->patchJson(route('staff.residents.update', $resident), verificationAuditPayload(['is_verified_indigent' => true, 'is_in_good_standing' => false]))->assertOk();

    expect(verificationAudits())->toBe([[
        'user_id' => $staff->id, 'record' => (string) $resident->id,
        'changes' => ['is_verified_indigent' => ['from' => false, 'to' => true], 'is_in_good_standing' => ['from' => true, 'to' => false]],
    ]]);
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.residents.update', 'record' => (string) $resident->id]);
});

test('nationality and identity changes are recorded by field name only, never their values', function () {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create(verificationAuditPayload(['date_of_birth' => '1990-05-14']));
    $this->actingAs($staff);

    $this->patchJson(route('staff.residents.update', $resident), verificationAuditPayload(['nationality' => 'Testlandian', 'last_name' => 'Delacruz']))->assertOk();
    $this->patchJson(route('staff.residents.update', $resident), verificationAuditPayload(['nationality' => null, 'last_name' => 'Delacruz']))->assertOk();
    $this->patchJson(route('staff.residents.update', $resident), verificationAuditPayload(['last_name' => 'Delacruz', 'contact_number' => '09181234567']))->assertOk();

    expect(array_column(verificationAudits(), 'changes'))->toBe([
        ['nationality' => 'recorded', 'last_name' => 'changed'],
        ['nationality' => 'cleared'],
    ]);
    $stored = DB::table('administrative_audits')->where('action', RecordResidentVerificationChange::ACTION)->pluck('changed_fields')->implode(' ');
    expect($stored)->not->toContain('Testlandian')->not->toContain('Delacruz')->not->toContain('Santos')->not->toContain('0918');
});

test('a new resident record notes a recorded nationality and verified indigency, and nothing when both are absent', function () {
    $this->actingAs(User::factory()->assignedOperations()->create());

    $this->postJson(route('staff.residents.store'), verificationAuditPayload())->assertCreated();
    expect(verificationAudits())->toBe([]);

    $this->postJson(route('staff.residents.store'), verificationAuditPayload(['first_name' => 'Ana', 'nationality' => 'Testlandian', 'is_verified_indigent' => true]))->assertCreated();
    expect(array_column(verificationAudits(), 'changes'))->toBe([
        ['is_verified_indigent' => ['from' => null, 'to' => true], 'nationality' => 'recorded'],
    ]);
});

test('a resident photo upload or replacement is recorded without the stored file path', function () {
    Storage::fake('local');
    $resident = Resident::factory()->create(['photo_path' => null]);
    $this->actingAs(User::factory()->assignedOperations()->create());

    $this->postJson(route('staff.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertOk();
    $this->postJson(route('staff.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('new.png')])->assertOk();

    expect(array_column(verificationAudits(), 'changes'))->toBe([['photo_path' => 'recorded'], ['photo_path' => 'changed']]);
    expect(DB::table('administrative_audits')->where('action', RecordResidentVerificationChange::ACTION)->pluck('changed_fields')->implode(' '))
        ->not->toContain('resident-photos');
});

test('the audit log describes the change to administrators only', function () {
    $staff = User::factory()->assignedOperations()->create();
    $resident = Resident::factory()->create(verificationAuditPayload(['date_of_birth' => '1990-05-14']));
    $this->actingAs($staff)->patchJson(route('staff.residents.update', $resident), verificationAuditPayload(['is_verified_indigent' => true, 'nationality' => 'Testlandian']))->assertOk();

    $this->actingAs($staff)->getJson(route('admin.audit.index'))->assertForbidden();
    $events = $this->actingAs(User::factory()->superAdmin()->create())->getJson(route('admin.audit.index'))->assertOk()->json('events');

    expect(collect($events)->firstWhere('action', 'residents / verification changed')['detail'])
        ->toBe("Resident #{$resident->id} - Indigency verified: no → yes; Nationality: recorded");
});
