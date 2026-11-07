<?php

namespace App\Http\Requests\Admin;

use App\AdditionalCertificateType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * The resident (and, for Cohabitation, the partner) and the certificate-specific details of a staff-only certificate,
 * validated against the rules its type declares. Used for both the preview and issuance.
 */
class AdditionalCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('documents.issue') ?? false;
    }

    public function certificateType(): AdditionalCertificateType
    {
        return AdditionalCertificateType::fromSlug((string) $this->route('type')) ?? abort(404);
    }

    protected function prepareForValidation(): void
    {
        $details = $this->input('details', []);
        if (! is_array($details)) {
            $details = [];
        }

        // Guardianship dependents are typed one per line.
        if (isset($details['dependents']) && is_string($details['dependents'])) {
            $details['dependents'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', $details['dependents']) ?: []), fn (string $name): bool => $name !== ''));
        }

        $this->merge(['details' => array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $details)]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->certificateType();
        $rules = [
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'partner_resident_id' => $type->needsPartner()
                ? ['required', 'integer', 'different:resident_id', Rule::exists('residents', 'id')->whereNull('deleted_at')]
                : ['prohibited'],
            'details' => ['array'],
        ];

        foreach ($type->fields() as $name => $field) {
            $rules['details.'.$name] = $field['rules'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['resident_id' => 'resident', 'partner_resident_id' => 'partner'];
        foreach ($this->certificateType()->fields() as $name => $field) {
            $attributes['details.'.$name] = mb_strtolower($field['label']);
        }

        return $attributes;
    }

    /**
     * Only the details this certificate type declares.
     *
     * @return array<string, mixed>
     */
    public function certificateDetails(): array
    {
        $declared = array_filter(array_keys($this->certificateType()->fields()), fn (string $name): bool => ! str_contains($name, '*'));

        return Arr::only((array) $this->validated('details', []), $declared);
    }
}
