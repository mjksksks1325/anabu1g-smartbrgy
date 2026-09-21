<?php

namespace App\Http\Requests\Admin;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Incident::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'incident_type' => ['required', 'string', 'max:100'],
            'occurred_date' => ['required', 'date', 'before_or_equal:today'],
            'occurred_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'complainant_name' => ['nullable', 'string', 'max:255'],
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high'])],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'incident_type' => trim((string) $this->input('incident_type')),
            'location' => trim((string) $this->input('location')),
            'complainant_name' => trim((string) $this->input('complainant_name')) ?: null,
            'respondent_name' => trim((string) $this->input('respondent_name')) ?: null,
            'severity' => mb_strtolower(trim((string) $this->input('severity'))),
            'details' => trim((string) $this->input('details')),
        ]);
    }
}
