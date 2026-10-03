<?php

namespace App\Http\Requests\Admin;

use App\CertificateType;
use App\Models\ResidentRequestRestriction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResidentRequestRestrictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ResidentRequestRestriction::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'incident_id' => ['nullable', 'integer', Rule::exists('incidents', 'id')->whereNull('deleted_at')],
            'affected_document_type' => ['nullable', Rule::in(CertificateType::values())],
            'reason_category' => ['required', 'string', 'max:100'],
            'internal_reason' => ['required', 'string', 'max:5000'],
            'resident_visible_reason' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'next_review_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['prohibited'], 'reviewed_by' => ['prohibited'], 'reviewed_at' => ['prohibited'], 'created_by' => ['prohibited'],
        ];
    }
}
