<?php

namespace App\Models;

use App\CertificateType;
use Carbon\CarbonInterface;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property CarbonInterface $date_of_birth
 * @property list<string>|null $special_groups
 */
class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth',
        'gender', 'civil_status', 'purok', 'address', 'contact_number',
        'residency_type', 'special_groups', 'status', 'is_in_good_standing',
    ];

    protected $appends = ['full_name', 'age'];

    protected static function booted(): void
    {
        static::creating(function (Resident $resident): void {
            $resident->resident_number ??= 'ANB-'.now()->format('Y').'-'.Str::upper(Str::random(10));
        });
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter()
            ->implode(' ');
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    /** @return HasMany<DocumentRequest, $this> */
    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }

    /** @return HasMany<IssuedCertificate, $this> */
    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(IssuedCertificate::class);
    }

    /** @return HasOne<VoterRegistration, $this> */
    public function voterRegistration(): HasOne
    {
        return $this->hasOne(VoterRegistration::class);
    }

    /** @return array{eligible: bool, reasons: list<string>} */
    public function certificateEligibility(CertificateType $certificateType): array
    {
        $reasons = [];

        if ($this->status !== 'active') {
            $reasons[] = 'Resident record is inactive.';
        }

        if (in_array($certificateType, [CertificateType::BarangayClearance, CertificateType::BusinessClearance], true)
            && ! $this->is_in_good_standing) {
            $reasons[] = 'Resident is not in good standing.';
        }

        if ($certificateType === CertificateType::FirstTimeJobseeker
            && $this->issuedCertificates()->where('certificate_type', $certificateType->value)->exists()) {
            $reasons[] = 'A First Time Jobseeker certificate has already been issued to this resident.';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons === [] ? ['Resident is eligible for this document.'] : $reasons,
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'special_groups' => 'array',
            'is_in_good_standing' => 'boolean',
        ];
    }
}
