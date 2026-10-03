<?php

use App\Models\Resident;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('authorized staff stores and replaces resident photos privately', function () {
    Storage::fake('local');
    Storage::fake('public');
    $resident = Resident::factory()->create();
    $this->actingAs(User::factory()->create())->postJson(route('admin.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertOk();
    $oldPath = $resident->fresh()->photo_path;
    Storage::disk('local')->assertExists($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    expect($resident->fresh()->toArray())->not->toHaveKey('photo_path');
    $this->get(route('admin.residents.photo', $resident))->assertOk();
    $this->postJson(route('admin.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('new.png')])->assertOk();
    Storage::disk('local')->assertMissing($oldPath);
    Storage::disk('local')->assertExists($resident->fresh()->photo_path);
});

test('untrusted resident photos are rejected', function (string $kind) {
    Storage::fake('local');
    $file = match ($kind) {
        'type' => UploadedFile::fake()->create('file.svg', 1, 'image/svg+xml'),
        'size' => UploadedFile::fake()->image('large.jpg')->size(5121),
        'dimensions' => UploadedFile::fake()->image('wide.jpg', 6001, 1),
    };
    $resident = Resident::factory()->create();
    $this->actingAs(User::factory()->create())->postJson(route('admin.residents.photo.store', $resident), ['photo' => $file])->assertUnprocessable()->assertJsonValidationErrors('photo');
    expect($resident->fresh()->photo_path)->toBeNull();
})->with(['type', 'size', 'dimensions']);

test('resident and viewer accounts cannot read or manage private resident photos', function (string $role) {
    $resident = Resident::factory()->create();
    $user = $role === 'resident' ? User::factory()->resident()->create() : User::factory()->create(['role' => 'viewer']);
    $this->actingAs($user)->get(route('admin.residents.photo', $resident))->assertForbidden();
    $this->postJson(route('admin.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('photo.jpg')])->assertForbidden();
})->with(['resident', 'viewer']);
