<?php

namespace App\Actions;

use App\AdditionalCertificateType;
use App\Models\CertificateEvidence;
use App\Models\Resident;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores one evidence photograph privately. The upload is accepted only if its actual content decodes as a JPEG,
 * PNG or WebP image; it is then re-encoded as a new JPEG under a random name, which drops anything else the file
 * carried (embedded metadata such as GPS, appended data, disguised content). The camera's rotation is applied first,
 * and the picture is never cropped.
 */
class StoreCertificateEvidence
{
    public function handle(UploadedFile $file, Resident $resident, AdditionalCertificateType $type, User $uploader): CertificateEvidence
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($contents);
        $image = $info !== false && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) ? @imagecreatefromstring($contents) : false;

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages(['photos' => __('Each file must be a real JPEG, PNG or WebP photograph.')]);
        }

        if ($info[2] === IMAGETYPE_JPEG) {
            $image = $this->applyCameraOrientation($image, $file->getRealPath());
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 88);
        $jpeg = (string) ob_get_clean();

        $uuid = (string) Str::uuid();
        $path = 'certificate-evidence/'.$uuid.'.jpg';
        if (! Storage::disk('local')->put($path, $jpeg)) {
            throw new \RuntimeException('Evidence photograph could not be saved.');
        }

        return CertificateEvidence::query()->create([
            'uuid' => $uuid,
            'certificate_type' => $type->value,
            'resident_id' => $resident->id,
            'uploaded_by' => $uploader->id,
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'size' => strlen($jpeg),
            'width' => $width,
            'height' => $height,
            'sha256' => hash('sha256', $jpeg),
        ]);
    }

    private function applyCameraOrientation(GdImage $image, string $path): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (int) ((@exif_read_data($path) ?: [])['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }
}
