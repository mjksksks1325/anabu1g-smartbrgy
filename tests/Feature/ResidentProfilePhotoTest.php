<?php

use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('a resident without a photo can upload it from profile into the admin resident record', function () {
    Storage::fake('local');
    Storage::fake('public');
    $account = User::factory()->resident()->create();
    $this->actingAs($account, 'resident')->get(route('portal.profile'))->assertOk()
        ->assertSee('Wala pang resident photo')->assertSee('Upload Photo')
        ->assertSee('enctype="multipart/form-data"', false);
    $this->get(route('portal.profile.photo'))->assertNotFound();

    $this->post(route('portal.profile.photo.store'), ['photo' => UploadedFile::fake()->image('profile.jpg')])
        ->assertRedirect(route('portal.profile'))->assertSessionHas('status');
    $photoPath = $account->resident->fresh()->photo_path;
    Storage::disk('local')->assertExists($photoPath);
    Storage::disk('public')->assertMissing($photoPath);
    $this->actingAs($account->fresh(), 'resident')->get(route('portal.profile'))->assertOk()->assertSee('Update Photo')->assertSee(route('portal.profile.photo'));
    $this->get(route('portal.profile.photo'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs(User::factory()->assignedOperations()->create(), 'web')->get(route('staff.residents.photo', $account->resident))->assertOk();
    $this->assertDatabaseHas('residents', ['id' => $account->resident_id, 'photo_path' => $photoPath]);
});

test('photo upload and preview are scoped to the signed-in resident despite supplied resident identifiers', function () {
    Storage::fake('local');
    $account = User::factory()->resident()->create();
    $other = Resident::factory()->create(['photo_path' => 'resident-photos/other.jpg']);
    Storage::disk('local')->put($other->photo_path, 'private-other-photo');
    $this->actingAs($account, 'resident')->post(route('portal.profile.photo.store'), [
        'photo' => UploadedFile::fake()->image('own.jpg'), 'resident_id' => $other->id,
        'photo_path' => $other->photo_path, 'first_name' => 'Changed',
    ])->assertRedirect(route('portal.profile'));
    $firstPhoto = $account->resident->fresh()->photo_path;
    $this->post(route('portal.profile.photo.store'), ['photo' => UploadedFile::fake()->image('updated.png')])
        ->assertRedirect(route('portal.profile'));
    $updatedResident = $account->resident->fresh();
    expect($updatedResident->photo_path)->not->toBe($firstPhoto);
    expect($updatedResident->first_name)->toBe($account->resident->first_name);
    expect($other->fresh()->photo_path)->toBe('resident-photos/other.jpg');
    expect(Storage::disk('local')->get($other->photo_path))->toBe('private-other-photo');
    $response = $this->get(route('portal.profile.photo', ['resident_id' => $other->id]))->assertOk();
    expect($response->streamedContent())->toBe(Storage::disk('local')->get($updatedResident->photo_path));
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('invalid profile uploads preserve the existing resident photo', function (string $kind) {
    Storage::fake('local');
    $account = User::factory()->resident()->create();
    $account->resident->update(['photo_path' => 'resident-photos/existing.jpg']);
    Storage::disk('local')->put('resident-photos/existing.jpg', 'existing-photo');
    $photo = match ($kind) {
        'missing' => null,
        'type' => UploadedFile::fake()->create('document.svg', 1, 'image/svg+xml'),
        'size' => UploadedFile::fake()->image('large.jpg')->size(5121),
        'dimensions' => UploadedFile::fake()->image('wide.jpg', 6001, 1),
    };
    $this->actingAs($account, 'resident')->post(route('portal.profile.photo.store'), ['photo' => $photo])
        ->assertSessionHasErrors('photo');
    expect($account->resident->fresh()->photo_path)->toBe('resident-photos/existing.jpg');
    expect(Storage::disk('local')->allFiles())->toBe(['resident-photos/existing.jpg']);
})->with(['missing', 'type', 'size', 'dimensions']);

test('guests and staff sessions cannot use resident profile photo endpoints', function (string $condition) {
    Storage::fake('local');
    if ($condition === 'staff') {
        $this->actingAs(User::factory()->assignedOperations()->create(), 'web');
    }
    $this->get(route('portal.profile.photo'))->assertRedirect(route('portal.login'));
    $this->post(route('portal.profile.photo.store'), ['photo' => UploadedFile::fake()->image('photo.jpg')])
        ->assertRedirect(route('portal.login'));
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with(['guest', 'staff']);

test('unavailable resident accounts cannot view or update profile photos', function (string $condition) {
    Storage::fake('local');
    $account = User::factory()->resident()->create();
    match ($condition) {
        'inactive' => $account->resident->update(['status' => 'inactive']),
        'archived' => $account->resident->delete(),
        'suspended' => $account->forceFill(['is_active' => false])->save(),
        'unlinked' => $account->forceFill(['resident_id' => null])->save(),
    };
    if ($condition === 'suspended') {
        $this->actingAs($account->fresh(), 'resident')->get(route('portal.profile.photo'))
            ->assertRedirect(route('portal.login'))->assertSessionHasErrors('email');
        $this->post(route('portal.profile.photo.store'), ['photo' => UploadedFile::fake()->image('photo.jpg')])
            ->assertRedirect(route('portal.login'));
        expect(Storage::disk('local')->allFiles())->toBeEmpty();

        return;
    }
    $this->actingAs($account->fresh(), 'resident')->get(route('portal.profile.photo'))->assertForbidden();
    $this->post(route('portal.profile.photo.store'), ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertForbidden();
    expect(Storage::disk('local')->allFiles())->toBeEmpty();
})->with(['inactive', 'archived', 'suspended', 'unlinked']);
