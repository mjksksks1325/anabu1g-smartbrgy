<?php

namespace App\Actions;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Validation and private storage for resident identity photos (new-resident applications, the resident's own
 * profile photo and staff Resident Records). JPEG, PNG and WebP photos are re-encoded as a high-quality JPEG no
 * larger than MAX_DIMENSION on the longest side, turned upright, and stripped of camera metadata such as location.
 * Other image types, and photos too large to decode in the available memory, are kept exactly as uploaded.
 */
class StoreResidentPhoto
{
    /** Largest original photo accepted, in kilobytes (10 MB). */
    public const MAX_KILOBYTES = 10240;

    /** Longest side kept when a photo is optimised: ample detail for identity checks and the printed ID. */
    public const MAX_DIMENSION = 2400;

    public const JPEG_QUALITY = 90;

    private const EXTENSIONS = 'jpg,jpeg,png,gif,bmp,webp,avif,heic,heif';

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return ['required', 'image', 'mimes:'.self::EXTENSIONS, 'extensions:'.self::EXTENSIONS, 'max:'.self::MAX_KILOBYTES, 'dimensions:max_width=6000,max_height=6000'];
    }

    /**
     * Store the photo on the private disk and return its path, or false when it could not be written.
     */
    public function handle(UploadedFile $photo): string|false
    {
        $optimised = $this->optimise((string) $photo->getRealPath());
        if ($optimised === null) {
            return $photo->store('resident-photos', 'local');
        }

        $path = 'resident-photos/'.Str::random(40).'.jpg';

        return Storage::disk('local')->put($path, $optimised) ? $path : false;
    }

    /**
     * The re-encoded JPEG bytes, or null to keep the original file.
     */
    private function optimise(string $file): ?string
    {
        $info = @getimagesize($file);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || ! $this->fitsInMemory($info[0], $info[1])) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($file));
        if (! $image instanceof GdImage) {
            return null;
        }
        $image = $this->upright($image, $info[2] === IMAGETYPE_JPEG ? $this->orientation($file) : 1);

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // A white background, so transparent PNG or WebP areas do not turn black in the JPEG.
        $output = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($output, 0, 0, (int) imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $written = imagejpeg($output, null, self::JPEG_QUALITY);
        $bytes = (string) ob_get_clean();

        return $written && $bytes !== '' ? $bytes : null;
    }

    /**
     * Whether decoding and resizing the photo stays well within PHP's memory limit.
     */
    private function fitsInMemory(int $width, int $height): bool
    {
        $limit = (string) ini_get('memory_limit');
        if ($limit === '-1' || $limit === '') {
            return true;
        }
        $limitBytes = (int) $limit * match (strtolower(substr($limit, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };
        $target = min($width * $height, self::MAX_DIMENSION ** 2);

        return memory_get_usage() + (($width * $height) + $target) * 5 < $limitBytes * 0.8;
    }

    private function orientation(string $file): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($file);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    /**
     * Apply the camera's EXIF orientation, since the re-encoded file no longer carries it.
     */
    private function upright(GdImage $image, int $orientation): GdImage
    {
        // imagerotate() turns counter-clockwise, so -90 is a quarter turn clockwise.
        $rotation = match ($orientation) {
            3 => 180,
            5, 6, 7 => -90,
            8 => 90,
            default => 0,
        };
        if ($rotation !== 0) {
            $rotated = imagerotate($image, $rotation, 0);
            $image = $rotated instanceof GdImage ? $rotated : $image;
        }
        $flip = match ($orientation) {
            2, 5 => IMG_FLIP_HORIZONTAL,
            4, 7 => IMG_FLIP_VERTICAL,
            default => null,
        };
        if ($flip !== null) {
            imageflip($image, $flip);
        }

        return $image;
    }
}
