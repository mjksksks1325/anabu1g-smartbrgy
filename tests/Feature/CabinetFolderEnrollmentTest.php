<?php

use App\Models\CabinetDevice;
use App\Models\CabinetFile;
use App\Models\CabinetFileEnrollmentRequest;
use App\Models\CabinetReader;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** @return array{CabinetDevice, CabinetFile, CabinetReader, CabinetFileEnrollmentRequest|null} */
function folderEnrollmentFixture(bool $pending = true, array $fileAttributes = []): array
{
    $cabinet = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'folder-device-token')]);
    $file = CabinetFile::factory()->create(['cabinet_device_id' => $cabinet->id, 'file_reference' => 'F-005', 'drawer_reference' => 'drawer_1', ...$fileAttributes]);
    $reader = CabinetReader::factory()->create(['cabinet_device_id' => $cabinet->id, 'drawer_reference' => 'drawer_1', 'reader_identifier' => 'reader-one']);
    $enrollment = $pending ? CabinetFileEnrollmentRequest::factory()->create([
        'cabinet_device_id' => $cabinet->id, 'cabinet_file_id' => $file->id, 'cabinet_reader_id' => $reader->id,
    ]) : null;

    return [$cabinet, $file, $reader, $enrollment];
}

/** @return array<string, string> */
function folderTagPayload(array $overrides = []): array
{
    return array_replace(['rfid_epc' => 'E280689400005031A1C4A97C', 'reader_identifier' => 'reader-one'], $overrides);
}

it('lists only valid active enrolled folders belonging to the authenticated cabinet', function () {
    [$cabinet, $file] = folderEnrollmentFixture(false, ['rfid_epc' => 'e280689400005031a1c4a97c']);
    CabinetFile::factory()->create(['cabinet_device_id' => $cabinet->id]);
    CabinetFile::factory()->create(['cabinet_device_id' => $cabinet->id, 'rfid_epc' => 'AA', 'is_active' => false]);
    CabinetFile::factory()->create(['cabinet_device_id' => $cabinet->id, 'rfid_epc' => 'NOTHEX']);
    CabinetFile::factory()->create(['rfid_epc' => 'BB']);

    $this->withHeader('X-Device-Token', 'folder-device-token')->getJson(route('api.iot.cabinets.folder-registry', $cabinet))
        ->assertOk()->assertExactJson(['folders' => [[
            'file_reference' => $file->file_reference, 'file_name' => $file->file_name, 'drawer_reference' => 'drawer_1',
            'rfid_epc' => 'E280689400005031A1C4A97C',
        ]]]);
});

it('returns the exact pending contract and expires requests at ten minutes', function () {
    $this->freezeTime();
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $this->withHeader('X-Device-Token', 'folder-device-token')->getJson(route('api.iot.cabinets.folder-enrollment.pending', $cabinet))
        ->assertOk()->assertExactJson(['pending' => true, 'folder' => [
            'request_id' => $enrollment->request_id, 'file_reference' => $file->file_reference,
            'file_name' => $file->file_name, 'drawer_reference' => 'drawer_1', 'reader_identifier' => $reader->reader_identifier,
        ]]);
    $this->travel(10)->minutes();
    $this->getJson(route('api.iot.cabinets.folder-enrollment.pending', $cabinet))->assertOk()->assertExactJson(['pending' => false, 'folder' => null]);
    expect($enrollment->fresh()->status)->toBe('expired');
});

it('does not leak another cabinet pending enrollment and requires device authentication', function () {
    [$cabinet] = folderEnrollmentFixture();
    $other = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'other-token')]);
    $this->getJson(route('api.iot.cabinets.folder-registry', $cabinet))->assertUnauthorized();
    $this->withHeader('X-Device-Token', 'other-token')->getJson(route('api.iot.cabinets.folder-enrollment.pending', $cabinet))->assertUnauthorized();
    $this->getJson(route('api.iot.cabinets.folder-enrollment.pending', $other))->assertOk()->assertExactJson(['pending' => false, 'folder' => null]);
});

it('confirms a lowercase tag once and returns an identical response on retry', function () {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $url = route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $enrollment->request_id]);
    $payload = folderTagPayload(['rfid_epc' => 'e280689400005031a1c4a97c']);
    $first = $this->withHeader('X-Device-Token', 'folder-device-token')->postJson($url, $payload)->assertOk()->assertExactJson([
        'status' => 'enrolled', 'request_id' => $enrollment->request_id,
        'folder' => ['file_reference' => $file->file_reference, 'file_name' => $file->file_name, 'drawer_reference' => 'drawer_1', 'rfid_epc' => 'E280689400005031A1C4A97C'],
    ])->json();
    $this->postJson($url, $payload)->assertOk()->assertExactJson($first);
    expect($file->fresh()->rfid_epc)->toBe('E280689400005031A1C4A97C');
    expect($enrollment->fresh())->status->toBe('enrolled')->completed_at->not->toBeNull();
    expect(DB::table('administrative_audits')->where('action', 'cabinet.folder-enrollment.confirmed')->count())->toBe(1);
    $this->postJson($url, folderTagPayload(['rfid_epc' => 'AA']))->assertConflict();
});

it('never steals another folder tag even when its owner is archived or in another cabinet', function () {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $owner = CabinetFile::factory()->create(['rfid_epc' => 'aa', 'is_active' => false]);
    $this->withHeader('X-Device-Token', 'folder-device-token')->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $enrollment->request_id]), folderTagPayload(['rfid_epc' => 'AA']))->assertConflict();
    expect($file->fresh()->rfid_epc)->toBeNull();
    expect($owner->fresh()->rfid_epc)->toBe('aa');
    expect($enrollment->fresh()->status)->toBe('pending');
});

it('rejects cancelled expired and timed out confirmations', function (string $status) {
    $this->freezeTime();
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    if ($status === 'timed out') {
        $this->travel(10)->minutes();
    } else {
        $enrollment->update(['status' => $status]);
    }
    $this->withHeader('X-Device-Token', 'folder-device-token')->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $enrollment->request_id]), folderTagPayload())->assertConflict();
    expect($file->fresh()->rfid_epc)->toBeNull();
    expect($enrollment->fresh()->status)->toBe($status === 'timed out' ? 'expired' : $status);
})->with(['cancelled', 'expired', 'timed out']);

it('rejects invalid scanned tags extra fields and the wrong reader', function (array $changes, string $field) {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $this->withHeader('X-Device-Token', 'folder-device-token')->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $enrollment->request_id]), folderTagPayload($changes))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($file->fresh()->rfid_epc)->toBeNull();
})->with([
    'odd length' => [['rfid_epc' => 'ABC'], 'rfid_epc'],
    'too long' => [['rfid_epc' => str_repeat('AB', 32)], 'rfid_epc'],
    'nonhex' => [['rfid_epc' => 'ZZ'], 'rfid_epc'],
    'extra fields' => [['user_id' => '1'], 'payload'],
    'wrong reader' => [['reader_identifier' => 'reader-two'], 'reader_identifier'],
]);

it('returns 404 for another cabinet request or an unknown request', function () {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $other = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'other-token')]);
    $this->withHeader('X-Device-Token', 'other-token')->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$other, $enrollment->request_id]), folderTagPayload())->assertNotFound();
    $this->withHeader('X-Device-Token', 'folder-device-token')->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, '00000000-0000-4000-8000-000000000000']), folderTagPayload())->assertNotFound();
    expect($file->fresh()->rfid_epc)->toBeNull();
});

it('queues one ten minute enrollment per cabinet and audits the requesting administrator', function () {
    $this->freezeTime();
    [$cabinet, $file, $reader] = folderEnrollmentFixture(false);
    $otherFile = CabinetFile::factory()->create(['cabinet_device_id' => $cabinet->id, 'drawer_reference' => 'drawer_1']);
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin)->post(route('admin.smart-cabinet.folders.rfid-enrollment', $file))->assertRedirect()->assertSessionHasNoErrors();
    $enrollment = CabinetFileEnrollmentRequest::query()->sole();
    expect($enrollment)->requested_by->toBe($admin->id)->status->toBe('pending')->reader_identifier->toBe('reader-one');
    expect($enrollment->expires_at->toDateTimeString())->toBe(now()->addMinutes(10)->toDateTimeString());
    expect($enrollment->request_id)->toMatch('/^[0-9a-f-]{36}$/');
    $this->post(route('admin.smart-cabinet.folders.rfid-enrollment', $otherFile))->assertSessionHasErrors('rfid_enrollment');
    $this->assertDatabaseCount('cabinet_file_enrollment_requests', 1);
    $this->assertDatabaseHas('administrative_audits', ['user_id' => $admin->id, 'action' => 'admin.cabinet-files.enrollment-requested']);
    $this->get(route('admin.smart-cabinet.index'))->assertOk()->assertSee('Waiting for tag')->assertSee('Cancel enrollment');
    $this->travel(10)->minutes();
    $this->post(route('admin.smart-cabinet.folders.rfid-enrollment', $otherFile))->assertSessionHasNoErrors();
    expect($enrollment->fresh()->status)->toBe('expired');
    $this->assertDatabaseCount('cabinet_file_enrollment_requests', 2);
});

it('cancels a pending request while retaining the currently enrolled tag', function () {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture(true, ['rfid_epc' => 'AA']);
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('admin.smart-cabinet.folders.rfid-cancel', $file), ['request_id' => $enrollment->request_id])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($enrollment->fresh()->status)->toBe('cancelled');
    expect($file->fresh()->rfid_epc)->toBe('AA');
    $this->assertDatabaseHas('administrative_audits', ['action' => 'admin.cabinet-files.enrollment-cancelled']);
});

it('requires explicit replacement and keeps old confirmations replayable after a later tag change', function () {
    [$cabinet, $file, $reader] = folderEnrollmentFixture(false, ['rfid_epc' => 'AA']);
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->post(route('admin.smart-cabinet.folders.rfid-enrollment', $file))->assertSessionHasErrors('rfid_enrollment');
    $this->post(route('admin.smart-cabinet.folders.rfid-replace', $file))->assertSessionHasNoErrors();
    expect($file->fresh()->rfid_epc)->toBe('AA');
    $request = CabinetFileEnrollmentRequest::query()->sole();
    $url = route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $request->request_id]);
    $first = $this->withHeader('X-Device-Token', 'folder-device-token')->postJson($url, folderTagPayload())->assertOk()->json();
    $this->post(route('admin.smart-cabinet.folders.rfid-replace', $file))->assertSessionHasNoErrors();
    $next = CabinetFileEnrollmentRequest::query()->latest('id')->firstOrFail();
    $this->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $next->request_id]), folderTagPayload(['rfid_epc' => 'BB']))->assertOk();
    $this->postJson($url, folderTagPayload())->assertOk()->assertExactJson($first);
    expect($file->fresh()->rfid_epc)->toBe('BB');
});

it('refuses enrollment with an inactive folder or missing active reader', function (string $problem) {
    [$cabinet, $file, $reader] = folderEnrollmentFixture(false);
    if ($problem === 'folder') {
        $file->update(['is_active' => false]);
    } else {
        $reader->update(['is_active' => false]);
    }
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('admin.smart-cabinet.folders.rfid-enrollment', $file))->assertSessionHasErrors('rfid_enrollment');
    $this->assertDatabaseCount('cabinet_file_enrollment_requests', 0);
})->with(['folder', 'reader']);

it('denies enrollment administration to ordinary staff accounts', function () {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $this->actingAs(User::factory()->assignedOperations()->create());
    foreach (['rfid-enrollment', 'rfid-replace', 'rfid-cancel'] as $action) {
        $this->post(route('admin.smart-cabinet.folders.'.$action, $file), ['request_id' => $enrollment->request_id])->assertForbidden();
    }
    expect($enrollment->fresh()->status)->toBe('pending');
});

it('blocks folder edits and reader remapping until pending enrollment is cancelled', function () {
    [$cabinet, $file, $reader] = folderEnrollmentFixture();
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->patch(route('admin.smart-cabinet.folders.update', $file), ['file_name' => 'Changed', 'cabinet_device_id' => $cabinet->id, 'drawer_reference' => 'drawer_2'])->assertSessionHasErrors('rfid_enrollment');
    $this->patch(route('admin.smart-cabinet.readers.update', $cabinet), ['drawer_1_reader_identifier' => 'new-one', 'drawer_2_reader_identifier' => 'reader-two'])->assertSessionHasErrors('rfid_enrollment');
    expect($reader->fresh()->reader_identifier)->toBe('reader-one');
    expect($file->fresh()->drawer_reference)->toBe('drawer_1');
});

it('allows 300 authenticated device requests per minute and returns Retry After when throttled', function () {
    [$cabinet] = folderEnrollmentFixture(false);
    $this->freezeTime();
    $this->withHeader('X-Device-Token', 'folder-device-token');
    $url = route('api.iot.cabinets.folder-registry', $cabinet);
    for ($index = 0; $index < 300; $index++) {
        $this->getJson($url)->assertOk();
    }
    $this->getJson(route('api.iot.cabinets.folder-enrollment.pending', $cabinet))->assertTooManyRequests()->assertHeader('Retry-After');
    $other = CabinetDevice::factory()->create(['api_token_hash' => hash('sha256', 'folder-device-token')]);
    $this->getJson(route('api.iot.cabinets.folder-registry', $other))->assertOk();
});

it('cancels pending scans when a folder is archived and refuses delayed confirmation', function () {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $this->actingAs(User::factory()->superAdmin()->create())->postJson(route('admin.smart-cabinet.folders.archive', $file), ['super_admin_password' => 'password'])->assertOk();
    expect($file->fresh()->is_active)->toBeFalse();
    expect($enrollment->fresh()->status)->toBe('cancelled');
    $this->withHeader('X-Device-Token', 'folder-device-token')->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $enrollment->request_id]), folderTagPayload())->assertConflict();
    expect($file->fresh()->rfid_epc)->toBeNull();
});

it('rejects scans when the assigned reader has been disabled or moved to another drawer', function (string $problem) {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture(true, ['rfid_epc' => 'AA']);
    $reader->update($problem === 'disabled' ? ['is_active' => false] : ['drawer_reference' => 'drawer_2']);
    $this->withHeader('X-Device-Token', 'folder-device-token')->getJson(route('api.iot.cabinets.folder-enrollment.pending', $cabinet))->assertExactJson(['pending' => false, 'folder' => null]);
    $this->postJson(route('api.iot.cabinets.folder-enrollment.confirm', [$cabinet, $enrollment->request_id]), folderTagPayload())->assertConflict();
    expect($file->fresh()->rfid_epc)->toBe('AA');
})->with(['disabled', 'moved']);

it('allows a captain to queue enrollment and rejects administrator supplied tag values', function () {
    [$cabinet, $file] = folderEnrollmentFixture(false);
    $this->actingAs(User::factory()->create(['role' => 'captain']));
    $this->patch(route('admin.smart-cabinet.folders.update', $file), [
        'file_name' => 'Folder', 'cabinet_device_id' => $cabinet->id, 'drawer_reference' => 'drawer_1', 'rfid_epc' => 'AA',
    ])->assertSessionHasErrors('rfid_epc');
    $this->post(route('admin.smart-cabinet.folders.rfid-enrollment', $file))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('cabinet_file_enrollment_requests', 1);
    expect($file->fresh()->rfid_epc)->toBeNull();
});

it('can cancel a request repeatedly without duplicating audits or cancelling a later enrollment', function () {
    [$cabinet, $file, $reader, $enrollment] = folderEnrollmentFixture();
    $this->actingAs(User::factory()->superAdmin()->create());
    $url = route('admin.smart-cabinet.folders.rfid-cancel', $file);
    $this->post($url, ['request_id' => $enrollment->request_id])->assertSessionHasNoErrors();
    $this->post(route('admin.smart-cabinet.folders.rfid-enrollment', $file))->assertSessionHasNoErrors();
    $this->post($url, ['request_id' => $enrollment->request_id])->assertSessionHasNoErrors();
    expect(CabinetFileEnrollmentRequest::query()->latest('id')->firstOrFail()->status)->toBe('pending');
    expect(DB::table('administrative_audits')->where('action', 'admin.cabinet-files.enrollment-cancelled')->count())->toBe(1);
});
