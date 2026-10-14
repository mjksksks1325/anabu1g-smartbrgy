<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreResidentApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'middle_name' => ['nullable', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'last_name' => ['required', 'string', 'max:100', "regex:/^[\\pL\\pM .'-]+$/u"],
            'suffix' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['Male', 'Female'])],
            'civil_status' => ['required', Rule::in(['Single', 'Married', 'Widowed', 'Separated'])],
            'nationality' => ['nullable', 'string', 'max:100'],
            'purok' => ['required', 'string', Rule::exists('puroks', 'name')->where('is_active', true)],
            'address' => ['required', 'string', 'max:1000'],
            'contact_number' => ['nullable', 'regex:/^(?:\\+63|0)9\\d{9}$/'],
            'residency_type' => ['required', Rule::in(['Homeowner', 'Renter', 'Boarder'])],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            ...array_fill_keys(['status', 'role', 'is_active', 'resident_id', 'reviewed_by', 'reviewed_at', 'reference', 'remarks', 'staff_permissions', 'portal_registration_hash', 'is_in_good_standing', 'is_verified_indigent', 'password'], ['prohibited']),
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'middle_name', 'last_name', 'suffix', 'address', 'nationality', 'email', 'contact_number'] as $field) {
            if (is_string($this->input($field))) {
                $value = Str::squish($this->input($field));
                $this->merge([$field => $field === 'email' ? Str::lower($value) : $value]);
            }
        }
    }
}
