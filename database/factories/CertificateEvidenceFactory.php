<?php

namespace Database\Factories;

use App\AdditionalCertificateType;
use App\Models\CertificateEvidence;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<CertificateEvidence>
 */
class CertificateEvidenceFactory extends Factory
{
    /**
     * A draft Probation evidence photograph with a real JPEG on the private disk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'certificate_type' => AdditionalCertificateType::Probation->value,
            'resident_id' => Resident::factory(),
            'issued_certificate_id' => null,
            'uploaded_by' => null,
            'path' => 'certificate-evidence/'.$uuid.'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 0,
            'width' => 900,
            'height' => 1200,
            'sha256' => str_repeat('0', 64),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (CertificateEvidence $evidence): void {
            $image = imagecreatetruecolor(max(1, $evidence->width), max(1, $evidence->height));
            $green = imagecolorallocate($image, 120, 160, 120);
            imagefill($image, 0, 0, $green === false ? 0 : $green);
            ob_start();
            imagejpeg($image, null, 80);
            $contents = (string) ob_get_clean();
            Storage::disk('local')->put($evidence->path, $contents);
            $evidence->forceFill(['size' => strlen($contents), 'sha256' => hash('sha256', $contents)])->save();
        });
    }
}
