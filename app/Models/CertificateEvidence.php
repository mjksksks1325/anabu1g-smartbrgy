<?php

namespace App\Models;

use Database\Factories\CertificateEvidenceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An evidence photograph for a certificate, stored privately. Drafts (issued_certificate_id null) belong to the
 * resident being prepared for; once issued, the photograph belongs to that certificate.
 *
 * @property string $uuid
 * @property string $certificate_type
 * @property int $resident_id
 * @property int|null $issued_certificate_id
 * @property int|null $uploaded_by
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property int $width
 * @property int $height
 * @property string $sha256
 */
class CertificateEvidence extends Model
{
    /** @use HasFactory<CertificateEvidenceFactory> */
    use HasFactory;

    /** Most photographs one certificate holds; the template's page shows them in a two-by-two grid. */
    public const MAX_PER_CERTIFICATE = 4;

    protected $table = 'certificate_evidence';

    protected $fillable = ['uuid', 'certificate_type', 'resident_id', 'issued_certificate_id', 'uploaded_by', 'path', 'mime_type', 'size', 'width', 'height', 'sha256'];

    protected $hidden = ['path', 'sha256'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @param  Builder<CertificateEvidence>  $query
     * @return Builder<CertificateEvidence>
     */
    public function scopeDraftFor(Builder $query, Resident $resident, string $certificateType): Builder
    {
        return $query->where('resident_id', $resident->id)->where('certificate_type', $certificateType)->whereNull('issued_certificate_id')->orderBy('id');
    }

    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return BelongsTo<IssuedCertificate, $this> */
    public function issuedCertificate(): BelongsTo
    {
        return $this->belongsTo(IssuedCertificate::class);
    }

    public function isDraft(): bool
    {
        return $this->issued_certificate_id === null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
