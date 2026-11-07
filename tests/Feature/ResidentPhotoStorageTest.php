<?php

use App\Actions\StoreResidentPhoto;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentRegistrationApplication;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function photoApplicationPayload(UploadedFile $photo): array
{
    $purok = Purok::factory()->create(['is_active' => true]);

    return ['first_name' => 'Maria', 'middle_name' => 'Cruz', 'last_name' => 'Photoapplicant', 'date_of_birth' => '1996-02-10',
        'gender' => 'Female', 'civil_status' => 'Single', 'residency_type' => 'Renter', 'purok' => $purok->name,
        'address' => 'Block 1, Anabu I-G', 'email' => 'photo-applicant@example.test', 'photo' => $photo,
        'contact_number' => '0917 123 4567', 'privacy_notice_accepted' => '1'];
}

/**
 * A 40×20 JPEG, red on the left and blue on the right, whose EXIF data says "rotate 90° clockwise to display".
 */
function cameraPhotoNeedingRotation(): string
{
    $image = imagecreatetruecolor(40, 20);
    imagefilledrectangle($image, 0, 0, 19, 19, (int) imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 20, 0, 39, 19, (int) imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = (string) ob_get_clean();

    $tiff = 'II*'."\0".pack('V', 8).pack('v', 1).pack('vvV', 0x0112, 3, 1).pack('vv', 6, 0).pack('V', 0);
    $app1 = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

function storedImageSize(string $path): array
{
    return array_slice(getimagesizefromstring(Storage::disk('local')->get($path)), 0, 2);
}

test('a large application photo is stored privately as a JPEG no larger than 2400 pixels on its longest side', function () {
    Storage::fake('local');

    $this->post(route('portal.new-resident.store'), photoApplicationPayload(UploadedFile::fake()->image('phone.png', 3200, 2400)))
        ->assertSessionHasNoErrors();

    $path = ResidentRegistrationApplication::query()->sole()->photo_path;
    expect($path)->toStartWith('resident-photos/')->toEndWith('.jpg')
        ->and(storedImageSize($path))->toBe([StoreResidentPhoto::MAX_DIMENSION, 1800]);
    Storage::disk('public')->assertMissing($path);
});

test('a camera photo is turned upright and stored without its camera metadata', function () {
    Storage::fake('local');
    $resident = Resident::factory()->create(['photo_path' => null]);
    $photo = UploadedFile::fake()->createWithContent('camera.jpg', cameraPhotoNeedingRotation());
    expect(exif_read_data($photo->getRealPath())['Orientation'])->toBe(6);

    $this->actingAs(User::factory()->assignedOperations()->create())
        ->postJson(route('staff.residents.photo.store', $resident), ['photo' => $photo])->assertOk();

    $stored = Storage::disk('local')->get($resident->fresh()->photo_path);
    $image = imagecreatefromstring($stored);
    $top = imagecolorsforindex($image, imagecolorat($image, 10, 5));
    $bottom = imagecolorsforindex($image, imagecolorat($image, 10, 35));
    expect([imagesx($image), imagesy($image)])->toBe([20, 40])
        ->and($top['red'])->toBeGreaterThan(200)->and($top['blue'])->toBeLessThan(60)
        ->and($bottom['blue'])->toBeGreaterThan(200)->and($bottom['red'])->toBeLessThan(60)
        ->and($stored)->not->toContain('Exif');
});

test('image types that cannot be optimised are kept exactly as uploaded', function () {
    Storage::fake('local');
    $resident = Resident::factory()->create(['photo_path' => null]);
    $photo = UploadedFile::fake()->image('resident.gif', 300, 300);

    $this->actingAs(User::factory()->assignedOperations()->create())
        ->postJson(route('staff.residents.photo.store', $resident), ['photo' => $photo])->assertOk();

    $path = $resident->fresh()->photo_path;
    expect($path)->toEndWith('.gif')
        ->and(Storage::disk('local')->get($path))->toBe(file_get_contents($photo->getRealPath()));
});

test('resident photos up to 10 MB are accepted and larger ones refused', function () {
    Storage::fake('local');
    $resident = Resident::factory()->create();
    $this->actingAs(User::factory()->assignedOperations()->create());

    $this->postJson(route('staff.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('ten.jpg')->size(10240)])->assertOk();
    $this->postJson(route('staff.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->image('eleven.jpg')->size(10241)])
        ->assertUnprocessable()->assertJsonValidationErrors(['photo' => 'The photo must be 10 MB or smaller. Choose a smaller photo and try again.']);

    auth()->logout();
    $this->post(route('portal.new-resident.store'), photoApplicationPayload(UploadedFile::fake()->image('ten.jpg')->size(10240)))->assertSessionHasNoErrors();
});

test('a form larger than PHP accepts gets a page explaining what to do, not a generic error', function () {
    $this->call('POST', route('portal.new-resident.store'), server: ['CONTENT_LENGTH' => (string) (64 * 1024 * 1024)])
        ->assertStatus(413)
        ->assertSee('The file you sent is too large.')
        ->assertSee('Resident photos can be up to 10 MB.')
        ->assertSee('Return to resident services');
});

test('documents disguised as photos are still refused', function () {
    Storage::fake('local');
    $resident = Resident::factory()->create();

    $this->actingAs(User::factory()->assignedOperations()->create())
        ->postJson(route('staff.residents.photo.store', $resident), ['photo' => UploadedFile::fake()->createWithContent('photo.jpg', '%PDF-1.7 not an image')])
        ->assertUnprocessable()->assertJsonValidationErrors('photo');
});
