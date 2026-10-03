<?php

namespace App\Models;

use App\Actions\CheckRequestRestrictions;
use App\CertificateType;
use Carbon\CarbonInterface;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property CarbonInterface $date_of_birth
 * @property list<string>|null $special_groups
 * @property CarbonInterface|null $portal_registration_expires_at
 * @property CarbonInterface|null $portal_registration_sent_at
 * @property string|null $portal_registration_email
 * @property int|null $household_id
 * @property string|null $relationship_to_household_head
 * @property bool $is_household_head
 * @property-read Household|null $household
 */
class Resident extends Model
{
    /** @use HasFactory<ResidentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth',
        'gender', 'civil_status', 'purok', 'address', 'contact_number',
        'nationality', 'photo_path', 'is_verified_indigent',
        'residency_type', 'special_groups', 'status', 'is_in_good_standing',
    ];

    protected $appends = ['full_name', 'age', 'has_photo'];

    protected $hidden = ['photo_path', 'portal_registration_hash', 'portal_registration_expires_at', 'portal_registration_email', 'portal_registration_sent_at'];

    /** @return HasOne<User, $this> */
    public function portalAccount(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

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

    public function getHasPhotoAttribute(): bool
    {
        return $this->photo_path !== null;
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

        if (in_array($certificateType, [CertificateType::BarangayClearance, CertificateType::BusinessClearance, CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency], true)
            && ! $this->is_in_good_standing) {
            $reasons[] = 'Resident is not in good standing.';
        }

        if (in_array($certificateType, [CertificateType::RegisteredVoterCertification, CertificateType::CertificateOfIndigency], true)) {
            $registration = $this->voterRegistration;
            if ($registration === null || $registration->status !== 'active' || ! $registration->integrity_valid) {
                $reasons[] = 'An active verified voter registration is required for the official certificate wording.';
            }
        }

        if ($certificateType === CertificateType::CertificateOfIndigency && ! $this->is_verified_indigent) {
            $reasons[] = 'Indigency must be verified by authorized barangay staff.';
        }

        if ($certificateType === CertificateType::FirstTimeJobseeker
            && $this->issuedCertificates()->where('certificate_type', $certificateType->value)->exists()) {
            $reasons[] = 'A First Time Jobseeker certificate has already been issued to this resident.';
        }

        if (app(CheckRequestRestrictions::class)->active($this, $certificateType) !== null) {
            $reasons[] = ResidentRequestRestriction::MESSAGE;
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
            'date_of_birth' => 'date:Y-m-d',
            'portal_registration_expires_at' => 'datetime',
            'portal_registration_email' => 'encrypted',
            'portal_registration_sent_at' => 'datetime',
            'special_groups' => 'array',
            'is_in_good_standing' => 'boolean',
            'is_verified_indigent' => 'boolean',
            'is_household_head' => 'boolean',
        ];
    }
}
