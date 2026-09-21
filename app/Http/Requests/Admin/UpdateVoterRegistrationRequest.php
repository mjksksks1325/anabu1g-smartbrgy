<?php

namespace App\Http\Requests\Admin;

use App\Models\VoterRegistration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVoterRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $registration = $this->route('voterRegistration');

        return $registration instanceof VoterRegistration
            && ($this->user()?->can('update', $registration) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comelec_voter_number' => ['nullable', 'string', 'min:6', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'precinct_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 -]+$/'],
            'cluster_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9 -]+$/'],
            'registration_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['active', 'transferred', 'deceased', 'deregistered'])],
            'version' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $identifier = trim((string) $this->input('comelec_voter_number'));

        $this->merge([
            'comelec_voter_number' => $identifier === '' ? null : mb_strtoupper(preg_replace('/\s+/u', '', $identifier) ?? ''),
            'precinct_number' => preg_replace('/\s+/u', ' ', trim((string) $this->input('precinct_number'))),
            'cluster_number' => preg_replace('/\s+/u', ' ', trim((string) $this->input('cluster_number'))),
        ]);
    }
}
