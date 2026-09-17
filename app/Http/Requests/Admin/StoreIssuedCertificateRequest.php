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
        return $this->user() !== null;
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
            'resident_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'purpose' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array{certificate_type: string, resident_name: string, purpose: string|null}
     */
    public function certificateAttributes(): array
    {
        return [
            'certificate_type' => $this->string('certificate_type')->toString(),
            'resident_name' => $this->string('resident_name')->toString(),
            'address' => $this->string('address')->toString(),
            'purpose' => $this->filled('purpose')
                ? $this->string('purpose')->toString()
                : null,
        ];
    }
}
