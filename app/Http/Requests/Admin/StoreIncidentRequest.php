<?php

namespace App\Http\Requests\Admin;

use App\Models\Incident;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $submissionOnly = ! ($this->user()?->hasAnyPermission(['incidents.view', 'vawc.view']) ?? false);

        return [
            'is_sensitive' => [$this->user()?->hasPermission('vawc.submit') ? 'sometimes' : 'prohibited', 'boolean'],
            'incident_type' => ['required', 'string', 'max:100'],
            'incident_type_selection' => ['sometimes', 'string', Rule::in(array_keys(Incident::SUBMISSION_CATEGORIES))],
            'occurred_date' => ['required', 'date', 'before_or_equal:today'],
            'occurred_time' => [$submissionOnly ? 'required' : 'nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'purok' => ['nullable', 'string', 'max:100'],
            'immediate_action' => ['nullable', 'string', 'max:1000'],
            'complainant_name' => ['nullable', 'string', 'max:255'],
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high'])],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
            'assigned_to' => [$submissionOnly ? 'prohibited' : 'nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'staff'])->where('is_active', true)->whereNull('resident_id')],
            'complainant_resident_id' => [$submissionOnly ? 'prohibited' : 'nullable', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'respondent_resident_id' => [$submissionOnly ? 'prohibited' : 'nullable', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'remarks' => [$submissionOnly ? 'prohibited' : 'nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            ...array_fill_keys(['reported_by', 'updated_by', 'actor', 'actor_id', 'user_id', 'staff_permissions', 'role', 'permissions', 'status', 'resolution_notes', 'resolved_at', 'approved', 'approved_by', 'approved_at', 'approval_status', 'incident_number', 'created_at', 'updated_at', 'deleted_at', 'protection_orders', 'protection_order_id', 'bpo_id', 'bpo_links', 'restrictions', 'request_restrictions', 'restriction_id'], ['prohibited']),
        ];
    }

    /** @return array<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['details', 'purok', 'immediate_action']) && mb_strlen($this->reportedDetails()) > 5000) {
                $validator->errors()->add('details', 'The description, purok and immediate action together must not exceed 5,000 characters.');
            }
        }];
    }

    public function reportedDetails(): string
    {
        $details = (string) $this->input('details');
        foreach (['purok' => 'Purok (reported)', 'immediate_action' => 'Immediate action reported by submitter'] as $field => $label) {
            $value = trim((string) $this->input($field));
            if ($value !== '') {
                $details .= "\n\n".$label.': '.$value;
            }
        }

        return $details;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'incident_type.required' => 'Please specify the incident type.',
            'occurred_time.required' => 'Please enter the incident time.',
            'attachments.array' => 'Please select the attachments using the file picker.',
            'attachments.max' => 'You can attach up to five files per report.',
            'attachments.*.file' => 'Attachment :position could not be uploaded. Please select the file again.',
            'attachments.*.uploaded' => 'Attachment :position could not be uploaded. Please select a file up to 5 MB and try again.',
            'attachments.*.mimes' => 'Attachment :position must be a JPG, PNG, WebP image or PDF file. Other file types are not supported.',
            'attachments.*.max' => 'Attachment :position is too large. Each file must be 5 MB or smaller.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('incident_type_selection')) && $this->input('incident_type_selection') !== 'Iba pa') {
            $this->merge(['incident_type' => $this->input('incident_type_selection')]);
        }

        foreach (['incident_type', 'location', 'complainant_name', 'respondent_name', 'severity', 'details', 'purok', 'immediate_action'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim($value);
                $this->merge([$field => $field === 'severity' ? mb_strtolower($value) : $value]);
            }
        }
    }
}
