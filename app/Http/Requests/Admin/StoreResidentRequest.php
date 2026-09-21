<?php

namespace App\Http\Requests\Admin;

use App\Models\Resident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Resident::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pM .\'-]+$/u'],
            'middle_name' => ['nullable', 'string', 'max:100', 'regex:/^[\pL\pM .\'-]+$/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[\pL\pM .\'-]+$/u'],
            'suffix' => ['nullable', 'string', 'max:20', 'regex:/^[\pL .]+$/u'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'civil_status' => ['required', Rule::in(['Single', 'Married', 'Widowed', 'Separated'])],
            'purok' => ['required', 'string', 'max:100', Rule::exists('puroks', 'name')->where('is_active', true)],
            'address' => ['required', 'string', 'max:1000'],
            'contact_number' => ['nullable', 'regex:/^(?:\+63|0)9\d{9}$/'],
            'residency_type' => ['required', Rule::in(['Homeowner', 'Renter', 'Boarder'])],
            'special_groups' => ['sometimes', 'array'],
            'special_groups.*' => [Rule::in([
                'PWD', 'Solo Parent', 'Indigenous People', '4Ps Beneficiary',
                'Teenage Mother', 'Out-of-School Youth', 'Unemployed Adult', 'Malnourished Child',
            ])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'is_in_good_standing' => ['sometimes', 'boolean'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->normalizeName($this->input('first_name')),
            'middle_name' => $this->normalizeName($this->input('middle_name')),
            'last_name' => $this->normalizeName($this->input('last_name')),
            'suffix' => $this->normalizeName($this->input('suffix')),
            'contact_number' => preg_replace('/[\s-]+/', '', (string) $this->input('contact_number')) ?: null,
        ]);
    }

    private function normalizeName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return preg_replace('/\s+/u', ' ', trim($value)) ?: null;
    }
}
