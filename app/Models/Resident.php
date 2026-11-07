<?php

namespace App\Models;

use App\Actions\CheckRequestRestrictions;
use App\CertificateType;
use Carbon\CarbonInterface;
use Database\Factories\ResidentFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /** @var list<string> */
    public const SPECIAL_GROUPS = [
        'PWD', 'Solo Parent', 'Indigenous People', '4Ps Beneficiary',
        'Teenage Mother', 'Out-of-School Youth', 'Unemployed Adult', 'Malnourished Child',
    ];

    protected $fillable = [
        'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth',
        'gender', 'civil_status', 'purok', 'address', 'contact_number',
        'nationality', 'photo_path', 'is_verified_indigent',
        'residency_type', 'special_groups', 'status', 'is_in_good_standing',
    ];

    protected $appends = ['full_name', 'age', 'has_photo'];

    protected $hidden = ['photo_path', 'portal_registration_hash', 'portal_registration_expires_at', 'portal_registration_email', 'portal_registration_sent_at'];

    /** Philippine mobile numbers as typed: 09XXXXXXXXX or +639XXXXXXXXX, after spaces, dashes and brackets are removed. */
    public const MOBILE_NUMBER_PATTERN = '/^(?:\+63|0)9\d{9}$/';

    /**
     * Eligibility reasons that wait on a staff verification rather than disqualify the resident: each says where
     * authorized staff record it. Until then a certificate can be previewed as a draft but not issued.
     */
    public const INDIGENCY_NOT_VERIFIED = 'Indigency must be verified by authorized barangay staff. Staff who may update resident records: in Resident Records, edit this resident and tick "Indigency verified by barangay staff" once indigency is confirmed, then issue again.';

    public const VOTER_REGISTRATION_NOT_VERIFIED = 'An active verified voter registration is required for the official certificate wording. Staff who manage voters: record or update the voter registration of this resident in Voters, then issue again.';

    /**
     * Store new mobile numbers in the international format: spaces, dashes and brackets are removed and
     * 09XXXXXXXXX becomes +639XXXXXXXXX. Anything else is returned as typed so validation can reject it.
     * Numbers already stored as 09XXXXXXXXX are left as they are until the record is next saved.
     */
    public static function normalizeContactNumber(mixed $value): ?string
    {
        $number = preg_replace('/[\s()-]+/', '', (string) $value);

        if ($number === '' || $number === null) {
            return null;
        }

        return preg_match('/^09\d{9}$/', $number) === 1 ? '+63'.substr($number, 1) : $number;
    }

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

    /**
     * Record details official certificate templates print (CertificateType::requiredDetails()), as detail keys, that
     * Resident Records flags as missing on every record. Nationality is not among them: residents are not asked for
     * it, and only the templates with a NATIONALITY line or a "Filipino" statement (Indigency, Registered Voter,
     * Low-Income, Probation, 4Ps Move-Out, Nonagenarian, Common-Law) need it. Those certificates ask for it in their
     * own readiness checks (Print & Release, the eligibility checker), when one is actually requested.
     */
    public const CERTIFICATE_RECORD_DETAILS = ['address', 'date_of_birth', 'gender', 'civil_status', 'photo'];

    /**
     * The certificate details this record lacks, so staff can complete them before a certificate needs them.
     * Whether a particular certificate is blocked depends on its own template (CertificateType::requiredDetails()).
     *
     * @return list<string>
     */
    public function missingCertificateDetails(): array
    {
        return array_values(array_filter(self::CERTIFICATE_RECORD_DETAILS, fn (string $detail): bool => $detail === 'photo'
            ? $this->photo_path === null
            : blank($this->getAttribute($detail))));
    }

    /**
     * Residents missing at least one detail in CERTIFICATE_RECORD_DETAILS.
     *
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeMissingCertificateDetails(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('photo_path');
            foreach (array_diff(self::CERTIFICATE_RECORD_DETAILS, ['photo']) as $column) {
                $query->orWhereNull($column)->orWhere($column, '');
            }
        });
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

    /**
     * Residents the voter registry lists: active, not archived, and at least 15 years old (SK minimum).
     * Demographics and the Voters module both count registered voters through this scope.
     *
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeInVoterRegistry(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereDate('date_of_birth', '<=', today()->subYears(15));
    }

    /**
     * Adds `voter_status`: the status of the resident's voter record (active, transferred, deceased or
     * deregistered), or null when the system holds no voter record. A null value means "not recorded here",
     * not "not registered with COMELEC". Only the status is read; COMELEC numbers stay out of the payload.
     *
     * @param  Builder<Resident>  $query
     * @return Builder<Resident>
     */
    public function scopeWithVoterStatus(Builder $query): Builder
    {
        return $query->addSelect(['voter_status' => VoterRegistration::query()
            ->select('status')
            ->whereColumn('voter_registrations.resident_id', 'residents.id')
            ->limit(1)]);
    }

    /**
     * Staff name search: every word must match the resident ID or a name part, so "Juan Dela Cruz",
     * "dela cruz juan", "Cruz, Juan" and "Juan D. Cruz" all find Juan Dela Cruz. Extra spaces, commas and
     * initials' full stops are ignored; letter case follows the database collation (case-insensitive on
     * MySQL `_ci` and on SQLite for ASCII letters).
     *
     * @param  Builder<Resident>  $query
     * @param  list<string>  $extraColumns
     * @return Builder<Resident>
     */
    public function scopeMatchingSearch(Builder $query, string $search, array $extraColumns = []): Builder
    {
        $columns = ['resident_number', 'first_name', 'middle_name', 'last_name', 'suffix', ...$extraColumns];
        $words = collect(preg_split('/[\s,]+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $word): string => rtrim($word, '.'))
            ->filter(fn (string $word): bool => $word !== '')
            ->unique()
            ->take(6);

        foreach ($words as $word) {
            $query->where(function (Builder $query) use ($columns, $word): void {
                foreach ($columns as $column) {
                    $query->orWhere($query->qualifyColumn($column), 'like', '%'.$word.'%');
                }
            });
        }

        return $query;
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
                $reasons[] = self::VOTER_REGISTRATION_NOT_VERIFIED;
            }
        }

        if ($certificateType === CertificateType::CertificateOfIndigency && ! $this->is_verified_indigent) {
            $reasons[] = self::INDIGENCY_NOT_VERIFIED;
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

    /**
     * Whether every reason this resident is not yet eligible waits on a staff verification (indigency, voter
     * registration), with no disqualification such as an inactive record, standing, a prior First Time Jobseeker
     * certificate or a document hold.
     */
    public function awaitsOnlyVerification(CertificateType $certificateType): bool
    {
        $eligibility = $this->certificateEligibility($certificateType);

        return ! $eligibility['eligible']
            && array_diff($eligibility['reasons'], [self::INDIGENCY_NOT_VERIFIED, self::VOTER_REGISTRATION_NOT_VERIFIED]) === [];
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
