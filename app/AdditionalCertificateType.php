<?php

namespace App;

use App\Actions\CheckRequestRestrictions;
use App\Models\Resident;
use App\Models\ResidentRequestRestriction;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The nine further official Barangay Anabu I-G certificates (public/certificates_format/), issued by staff only.
 *
 * They are never offered in the Resident Portal. Each can be completed and previewed, but is issued only once the
 * barangay approves its fee in config/certificates.php ("additional" => [slug => ['fee' => .., 'enabled' => true]]);
 * until then issuance is refused. The checks below only test facts the system records (age, nationality, verified
 * voter registration, good standing, recorded special groups); anything the wording claims that the system cannot
 * check is listed in pendingApprovals().
 */
enum AdditionalCertificateType: string
{
    case LowIncome = 'Certificate of Low Income';
    case Guardianship = 'Certificate of Guardianship';
    case Cohabitation = 'Certificate of Cohabitation';
    case FactOfBirth = 'Certificate for Fact of Birth';
    case Probation = 'Certificate for Probation';
    case NationalIdRegistration = 'Certificate for National ID Registration';
    case FourPsMoveOut = 'Certificate of Move-Out for 4Ps';
    case NoObjection = 'Certificate of No Objection';
    case Nonagenarian = 'Nonagenarian Certification';

    public function slug(): string
    {
        return match ($this) {
            self::LowIncome => 'low-income',
            self::Guardianship => 'guardianship',
            self::Cohabitation => 'cohabitation',
            self::FactOfBirth => 'fact-of-birth',
            self::Probation => 'probation',
            self::NationalIdRegistration => 'national-id-registration',
            self::FourPsMoveOut => 'move-out-4ps',
            self::NoObjection => 'no-objection',
            self::Nonagenarian => 'nonagenarian',
        };
    }

    /**
     * Every certificate name the system issues: the resident-requestable types and these staff-only ones. Used where
     * staff filter or hold documents; the Resident Portal keeps using CertificateType alone.
     *
     * @return list<string>
     */
    public static function allCertificateValues(): array
    {
        return [...CertificateType::values(), ...array_map(fn (self $type): string => $type->value, self::cases())];
    }

    public static function fromSlug(string $slug): ?self
    {
        return collect(self::cases())->first(fn (self $type): bool => $type->slug() === $slug);
    }

    /** The barangay's Word template in public/certificates_format/ that the layout and wording follow. */
    public function officialReference(): string
    {
        return match ($this) {
            self::LowIncome => 'Brgy. Certificate for Low-Income 2026.docx',
            self::Guardianship => 'Brgy. Certificate of Guardianship 2026.docx',
            self::Cohabitation => 'Brgy. Certificate for Common Law 2026.docx',
            self::FactOfBirth => 'Bgry. Certificate for Fact of Birth 2026.docx',
            self::Probation => 'Brgy. Certificate-for Probation 2026.docx',
            self::NationalIdRegistration => 'Brgy. Certificate-NATIONAL ID.docx',
            self::FourPsMoveOut => 'Brgy.Certificate-MOVE OUT FOR 4PS 2026.docx',
            self::NoObjection => 'CERTIFICATE OF NO- OBJECTION 2026.docx',
            self::Nonagenarian => 'Senior Citizen -Nonagenarian Letter.docx',
        };
    }

    /** @return view-string */
    public function printView(): string
    {
        return match ($this) {
            self::LowIncome => 'admin.certificates.additional.low-income',
            self::Guardianship => 'admin.certificates.additional.guardianship',
            self::Cohabitation => 'admin.certificates.additional.cohabitation',
            self::FactOfBirth => 'admin.certificates.additional.fact-of-birth',
            self::Probation => 'admin.certificates.additional.probation',
            self::NationalIdRegistration => 'admin.certificates.additional.national-id-registration',
            self::FourPsMoveOut => 'admin.certificates.additional.move-out-4ps',
            self::NoObjection => 'admin.certificates.additional.no-objection',
            self::Nonagenarian => 'admin.certificates.additional.nonagenarian',
        };
    }

    /**
     * The approved fee, or null while the barangay has not approved one. No fee is assumed.
     */
    public function fee(): ?int
    {
        $fee = config("certificates.additional.{$this->slug()}.fee");

        return is_numeric($fee) ? (int) $fee : null;
    }

    public function issuanceEnabled(): bool
    {
        return $this->fee() !== null && config("certificates.additional.{$this->slug()}.enabled") === true;
    }

    public function validityPeriodInMonths(): int
    {
        return 3;
    }

    /** Whether the wording refers to "the person whose photo, signature and thumb mark appear herein". */
    public function requiresPhoto(): bool
    {
        return in_array($this, [self::LowIncome, self::Probation, self::NationalIdRegistration, self::FourPsMoveOut], true);
    }

    /** Whether staff attach evidence photographs (the Probation template's community-service page). */
    public function acceptsEvidence(): bool
    {
        return $this === self::Probation;
    }

    /** Whether the page carries the right thumb mark box and the resident's signature line. */
    public function hasResidentSignature(): bool
    {
        return $this->requiresPhoto();
    }

    /**
     * Resident record details the printed form states.
     *
     * @return list<'address'|'date_of_birth'|'gender'|'civil_status'|'nationality'>
     */
    public function requiredResidentDetails(): array
    {
        return match ($this) {
            self::LowIncome, self::Probation => ['address', 'date_of_birth', 'gender', 'civil_status', 'nationality'],
            self::FourPsMoveOut => ['date_of_birth', 'gender', 'civil_status', 'nationality'],
            self::NationalIdRegistration => ['address', 'date_of_birth'],
            self::Nonagenarian => ['address', 'date_of_birth', 'nationality'],
            self::Guardianship => ['address', 'date_of_birth'],
            self::Cohabitation => ['address', 'date_of_birth', 'gender', 'nationality'],
            self::FactOfBirth, self::NoObjection => ['address'],
        };
    }

    /**
     * The certificate-specific details staff enter, with their labels, input types and validation rules.
     *
     * @return array<string, array{label: string, type: string, rules: list<string>, help?: string}>
     */
    public function fields(): array
    {
        $purpose = ['purpose' => ['label' => 'Purpose', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']]];

        return match ($this) {
            self::LowIncome => [
                'occupation' => ['label' => 'Occupation', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'help' => 'As the resident declared it, for example Labandera.'],
                'monthly_income' => ['label' => 'Estimated monthly income (PHP)', 'type' => 'number', 'rules' => ['required', 'numeric', 'min:0', 'max:1000000'], 'help' => 'The estimate the resident declared and staff assessed.'],
                ...$purpose,
            ],
            self::Guardianship => [
                'dependents' => ['label' => 'Names of the dependents (one per line, up to 6)', 'type' => 'lines', 'rules' => ['required', 'array', 'min:1', 'max:6'], 'help' => 'They must live at the guardian\'s address.'],
                'dependents.*' => ['label' => 'Dependent', 'type' => 'hidden', 'rules' => ['required', 'string', 'max:150']],
                ...$purpose,
            ],
            self::Cohabitation => [
                'years_together' => ['label' => 'Years living together, as declared', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:80']],
                ...$purpose,
            ],
            self::FactOfBirth => [
                'father_name' => ['label' => "Father's full name", 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                'mother_name' => ['label' => "Mother's full name", 'type' => 'text', 'rules' => ['required', 'string', 'max:150'], 'help' => 'The selected resident must be the father or the mother.'],
                'child_name' => ['label' => "Child's full name", 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                'child_birth_date' => ['label' => "Child's date of birth", 'type' => 'date', 'rules' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:today']],
                'child_birth_place' => ['label' => "Child's place of birth", 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                ...$purpose,
            ],
            self::Probation => [
                'service_completed_on' => ['label' => 'Community service completed on', 'type' => 'date', 'rules' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today']],
                ...$purpose,
            ],
            self::NationalIdRegistration => [
                'birth_place' => ['label' => 'Place of birth', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                'mother_name' => ['label' => "Mother's full name", 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                'father_name' => ['label' => "Father's full name", 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                ...$purpose,
            ],
            self::FourPsMoveOut => [
                'destination' => ['label' => 'Moving to (full address)', 'type' => 'text', 'rules' => ['required', 'string', 'max:200']],
                ...$purpose,
            ],
            self::NoObjection => [
                'tree_count' => ['label' => 'Number of trees', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:500']],
                'tree_species' => ['label' => 'Kind of tree', 'type' => 'text', 'rules' => ['required', 'string', 'max:60'], 'help' => 'For example Mango.'],
                'tree_location' => ['label' => 'Where the trees are located', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'help' => 'Completes "which found to be located in the …", for example right of way.'],
            ],
            self::Nonagenarian => [
                'representative_name' => ['label' => 'Authorized representative (optional)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150'], 'help' => 'Only when the resident authorizes someone to claim the benefit.'],
                'representative_relationship' => ['label' => 'Relationship of the representative', 'type' => 'text', 'rules' => ['nullable', 'required_with:details.representative_name', 'string', 'max:40'], 'help' => 'For example daughter.'],
            ],
        };
    }

    /** Whether a second resident record (the partner) is part of the certificate. */
    public function needsPartner(): bool
    {
        return $this === self::Cohabitation;
    }

    /**
     * Reasons the selected resident (and partner) cannot receive this certificate, from facts the system records.
     *
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    public function eligibilityProblems(Resident $resident, ?Resident $partner, array $details, CarbonInterface $issuedOn): array
    {
        $problems = [];
        $age = fn (Resident $person): int => (int) floor($person->date_of_birth->diffInYears($issuedOn));

        if ($resident->status !== 'active') {
            $problems[] = 'Resident record is inactive.';
        }

        if (app(CheckRequestRestrictions::class)->activeFor($resident, $this->value) !== null) {
            $problems[] = ResidentRequestRestriction::MESSAGE;
        }

        if (in_array($this, [self::LowIncome, self::NationalIdRegistration, self::FourPsMoveOut], true) && ! $resident->is_in_good_standing) {
            $problems[] = 'Resident is not in good standing.';
        }

        if (in_array($this, [self::LowIncome, self::Probation, self::NationalIdRegistration, self::FourPsMoveOut, self::FactOfBirth], true)) {
            $registration = $resident->voterRegistration;
            if ($registration === null || $registration->status !== 'active' || ! $registration->integrity_valid) {
                $problems[] = 'An active verified voter registration is required for the official certificate wording.';
            }
        }

        if ($this === self::FourPsMoveOut && ! in_array('4Ps Beneficiary', $resident->special_groups ?? [], true)) {
            $problems[] = 'The resident record does not list the resident as a 4Ps beneficiary.';
        }

        if (in_array($this, [self::Guardianship, self::Cohabitation], true) && $age($resident) < 18) {
            $problems[] = 'The resident must be at least 18 years old ("of legal age").';
        }

        if ($this === self::Nonagenarian && $age($resident) < 90) {
            $problems[] = 'The resident must be at least 90 years old on the issue date.';
        }

        if (in_array($this, [self::Nonagenarian, self::Cohabitation], true) && Str::lower((string) $resident->nationality) !== 'filipino') {
            $problems[] = 'The resident record must give the nationality as Filipino.';
        }

        if ($this === self::Cohabitation) {
            if ($partner === null || $partner->is($resident)) {
                $problems[] = 'Select the partner\'s resident record.';
            } else {
                if ($partner->status !== 'active') {
                    $problems[] = 'The partner\'s resident record is inactive.';
                }
                if ($age($partner) < 18) {
                    $problems[] = 'The partner must be at least 18 years old ("of legal age").';
                }
                if (Str::lower((string) $partner->nationality) !== 'filipino') {
                    $problems[] = 'The partner\'s record must give the nationality as Filipino.';
                }
                $sameHousehold = $resident->household_id !== null && $resident->household_id === $partner->household_id;
                if (! $sameHousehold && Str::lower(trim((string) $resident->address)) !== Str::lower(trim((string) $partner->address))) {
                    $problems[] = 'The two residents do not share a household or an address on record.';
                }
            }
        }

        if ($this === self::FactOfBirth) {
            $parents = [Str::lower(trim((string) ($details['father_name'] ?? ''))), Str::lower(trim((string) ($details['mother_name'] ?? '')))];
            if (! in_array(Str::lower($resident->full_name), $parents, true)) {
                $problems[] = 'The selected resident must be the father or the mother named on the certificate.';
            }
        }

        return $problems;
    }

    /**
     * What the barangay still has to approve before this certificate can be issued.
     *
     * @return list<string>
     */
    public function pendingApprovals(): array
    {
        $pending = $this->fee() === null ? ['Fee'] : [];

        return [...$pending, ...match ($this) {
            self::LowIncome => ['Who qualifies as a low-income earner family, and how the income estimate is assessed'],
            self::Guardianship => ['How staff confirm guardianship and that the dependents live at the same address'],
            self::Cohabitation => ['How staff confirm the declared years together; the wording names the couple "husband and wife"'],
            self::FactOfBirth => ['How the child\'s birth details are verified; whether the other parent must also be a registered voter of legal age'],
            self::Probation => ['How completed community service is confirmed, and what the photographs must show'],
            self::NationalIdRegistration => ['How the place of birth and parents\' names are confirmed'],
            self::FourPsMoveOut => ['Confirmation that the 4Ps beneficiary flag in Resident Records may be relied on'],
            self::NoObjection => ['The inspection the wording mentions, and the validity period (it depends on CENRO certification)'],
            self::Nonagenarian => ['The Barangay Health Worker who co-signs, and how a representative\'s authority is confirmed'],
        }];
    }
}
