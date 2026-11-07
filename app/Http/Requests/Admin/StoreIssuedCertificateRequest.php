<?php

namespace App\Http\Requests\Admin;

use App\CertificateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssuedCertificateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('documents.issue') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'certificate_type' => ['required', 'string', Rule::in(CertificateType::values())],
            'resident_id' => [
                'nullable',
                'integer',
                Rule::exists('residents', 'id')->whereNull('deleted_at'),
            ],
            'resident_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'purpose' => ['nullable', 'string', 'max:500'],
            ...self::staffEnteredDetailRules(),
        ];
    }

    /**
     * Details staff confirm at issuance for certificates whose template states them (see
     * CertificateType::staffEnteredDetails()). Shared by onsite issuance and issuance from a request.
     *
     * @return array<string, array<int, string>>
     */
    public static function staffEnteredDetailRules(): array
    {
        return [
            'residing_since' => ['nullable', 'date_format:Y-m'],
            'years_of_residency' => ['nullable', 'integer', 'min:0', 'max:130'],
        ];
    }

    /**
     * @return array{certificate_type: string, resident_id: int|null, resident_name: string, address: string, purpose: string|null, residing_since: string|null, years_of_residency: int|null}
     */
    public function certificateAttributes(): array
    {
        return [
            'certificate_type' => $this->string('certificate_type')->toString(),
            'resident_id' => $this->integer('resident_id') ?: null,
            'resident_name' => $this->string('resident_name')->toString(),
            'address' => $this->string('address')->toString(),
            'purpose' => $this->filled('purpose')
                ? $this->string('purpose')->toString()
                : null,
            'residing_since' => $this->filled('residing_since') ? $this->string('residing_since')->toString() : null,
            'years_of_residency' => $this->filled('years_of_residency') ? $this->integer('years_of_residency') : null,
        ];
    }
}
