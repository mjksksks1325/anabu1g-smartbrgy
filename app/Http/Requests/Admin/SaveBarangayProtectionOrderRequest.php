<?php

namespace App\Http\Requests\Admin;

use App\Models\BarangayProtectionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBarangayProtectionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('protectionOrder');

        return $order instanceof BarangayProtectionOrder ? ($this->user()?->can('update', $order) ?? false) : ($this->user()?->can('create', BarangayProtectionOrder::class) ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'incident_id' => ['required', 'integer', Rule::exists('incidents', 'id')->whereNull('deleted_at')],
            'protected_resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'respondent_resident_id' => ['nullable', 'integer', Rule::exists('residents', 'id')->whereNull('deleted_at')],
            'protected_person' => ['required_without:protected_resident_id', 'nullable', 'string', 'max:255'],
            'respondent' => ['required_without:respondent_resident_id', 'nullable', 'string', 'max:255'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
            'effective_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issued_on', ...($this->filled('effective_on') ? ['after_or_equal:effective_on'] : [])],
            'status' => ['required', 'string', 'max:30'],
            'issuing_authority' => ['required', 'string', 'max:255'],
            'internal_remarks' => ['nullable', 'string', 'max:5000'],
            'supporting_document_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
