<?php

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('publishes multiple images in their original formats on both resident pages', function () {
    Storage::fake('public');
    $jpeg = UploadedFile::fake()->image('notice.jpeg');
    $png = UploadedFile::fake()->image('map.png');
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('staff.announcements.store'), [
            'title' => 'Community event', 'body' => 'Join us.', 'status' => 'published', 'images' => [$jpeg, $png],
        ])->assertRedirect()->assertSessionHasNoErrors();

    $announcement = Announcement::query()->sole();
    expect($announcement->imagePaths())->toHaveCount(2)
        ->and($announcement->imagePaths()[0])->toEndWith('.jpeg')
        ->and($announcement->imagePaths()[1])->toEndWith('.png');
    foreach ($announcement->imagePaths() as $path) {
        Storage::disk('public')->assertExists($path);
    }
    expect(Storage::disk('public')->get($announcement->imagePaths()[0]))->toBe(file_get_contents($jpeg->getPathname()));
    foreach ([route('home'), route('portal.announcements.index')] as $url) {
        $response = $this->get($url)->assertOk();
        foreach ($announcement->imageUrls() as $imageUrl) {
            $response->assertSee($imageUrl, false);
        }
    }
    $this->get(route('staff.announcements.index'))->assertOk()
        ->assertSee('name="images[]"', false)->assertSee('multiple', false)->assertSee('name="remove_images[]"', false);

});

it('keeps legacy images and appends new images while selectively removing the chosen ones', function () {
    Storage::fake('public');
    Storage::disk('public')->put('announcements/legacy.jpg', 'legacy');
    $announcement = Announcement::query()->create(['title' => 'Notice', 'body' => 'Details', 'status' => 'draft', 'image_path' => 'announcements/legacy.jpg']);
    expect($announcement->imagePaths())->toBe(['announcements/legacy.jpg']);
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->patch(route('staff.announcements.update', $announcement), [
        'title' => 'Notice', 'body' => 'Details', 'images' => [UploadedFile::fake()->image('new.png')],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $paths = $announcement->fresh()->imagePaths();
    expect($paths)->toHaveCount(2)->and($paths[0])->toBe('announcements/legacy.jpg');
    $this->patch(route('staff.announcements.update', $announcement), [
        'title' => 'Edited', 'body' => 'Details', 'remove_images' => [$paths[0]],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($announcement->fresh()->imagePaths())->toBe([$paths[1]])
        ->and($announcement->fresh()->image_path)->toBe($paths[1]);
    Storage::disk('public')->assertMissing($paths[0]);
    Storage::disk('public')->assertExists($paths[1]);
    $this->patch(route('staff.announcements.update', $announcement), [
        'title' => 'Edited', 'body' => 'Details', 'remove_images' => [$paths[1]],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($announcement->fresh()->imagePaths())->toBe([])->and($announcement->fresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($paths[1]);
});

it('rejects an entire batch containing a non-image without saving any upload', function (string $name, string $mime) {
    Storage::fake('public');
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.announcements.store'), [
        'title' => 'Notice', 'body' => 'Details', 'status' => 'draft',
        'images' => [UploadedFile::fake()->image('valid.jpg'), UploadedFile::fake()->create($name, 20, $mime)],
    ])->assertSessionHasErrors('images.1');
    $this->assertDatabaseCount('announcements', 0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    ['file.pdf', 'application/pdf'], ['file.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pretend.jpg', 'application/pdf'],
]);

it('rejects oversized images and more than ten images', function (bool $oversized) {
    Storage::fake('public');
    $images = $oversized ? [UploadedFile::fake()->image('large.jpg')->size(5121)]
        : array_map(fn () => UploadedFile::fake()->image('notice.jpg'), range(1, 11));
    $this->actingAs(User::factory()->superAdmin()->create())->post(route('staff.announcements.store'), [
        'title' => 'Notice', 'body' => 'Details', 'status' => 'draft', 'images' => $images,
    ])->assertSessionHasErrors($oversized ? 'images.0' : 'images');
    expect(Storage::disk('public')->allFiles())->toBe([]);
})->with([true, false]);

it('limits the combined retained and new images and permits replacing a removed image at the limit', function () {
    Storage::fake('public');
    $paths = array_map(fn ($number) => "announcements/{$number}.jpg", range(1, 10));
    foreach ($paths as $path) {
        Storage::disk('public')->put($path, 'existing');
    }
    $announcement = Announcement::query()->create(['title' => 'Notice', 'body' => 'Details', 'status' => 'draft', 'image_paths' => $paths, 'image_path' => $paths[0]]);
    $this->actingAs(User::factory()->superAdmin()->create());
    $this->patch(route('staff.announcements.update', $announcement), [
        'title' => 'Notice', 'body' => 'Details', 'images' => [UploadedFile::fake()->image('extra.jpg')],
    ])->assertSessionHasErrors('images');
    expect($announcement->fresh()->imagePaths())->toBe($paths)->and(Storage::disk('public')->allFiles())->toHaveCount(10);
    $this->patch(route('staff.announcements.update', $announcement), [
        'title' => 'Notice', 'body' => 'Details', 'remove_images' => [$paths[0]], 'images' => [UploadedFile::fake()->image('replacement.png')],
    ])->assertSessionHasNoErrors();
    expect($announcement->fresh()->imagePaths())->toHaveCount(10);
    Storage::disk('public')->assertMissing($paths[0]);
});

it('cannot remove an image belonging to another announcement', function () {
    Storage::fake('public');
    Storage::disk('public')->put('announcements/other.jpg', 'other');
    $announcement = Announcement::query()->create(['title' => 'Notice', 'body' => 'Details', 'status' => 'draft']);
    $this->actingAs(User::factory()->superAdmin()->create())->patch(route('staff.announcements.update', $announcement), [
        'title' => 'Notice', 'body' => 'Details', 'remove_images' => ['announcements/other.jpg'],
    ])->assertSessionHasErrors('remove_images.0');
    Storage::disk('public')->assertExists('announcements/other.jpg');
});

it('does not accept uploads from staff without announcement creation permission', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => 'staff', 'staff_permissions' => ['announcements.view']]))
        ->post(route('staff.announcements.store'), [
            'title' => 'Notice', 'body' => 'Details', 'status' => 'draft', 'images' => [UploadedFile::fake()->image('notice.jpg')],
        ])->assertForbidden();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
