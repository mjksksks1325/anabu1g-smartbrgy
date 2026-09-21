<?php

namespace App\Http\Requests\Admin;

use App\Models\VoterRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVoterRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', VoterRegistration::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id', 'unique:voter_registrations,resident_id'],
            'comelec_voter_number' => ['required', 'string', 'min:6', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'precinct_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 -]+$/'],
            'cluster_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 -]+$/'],
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['active', 'transferred', 'deceased', 'deregistered'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comelec_voter_number' => mb_strtoupper(preg_replace('/\s+/u', '', trim((string) $this->input('comelec_voter_number'))) ?? ''),
            'precinct_number' => preg_replace('/\s+/u', ' ', trim((string) $this->input('precinct_number'))),
            'cluster_number' => preg_replace('/\s+/u', ' ', trim((string) $this->input('cluster_number'))),
        ]);
    }
}
