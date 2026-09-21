<?php

namespace App\Http\Requests\Admin;

use App\Models\DocumentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequestStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $documentRequest = $this->route('documentRequest');

        return $documentRequest instanceof DocumentRequest
            && ($this->user()?->can('update', $documentRequest) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['pending', 'processing', 'approved', 'ready_for_release', 'rejected'])],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => [
                Rule::requiredIf(fn (): bool => $this->input('status') === 'rejected'),
                'nullable',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remarks' => $this->filled('remarks') ? trim((string) $this->input('remarks')) : null,
            'rejection_reason' => $this->filled('rejection_reason') ? trim((string) $this->input('rejection_reason')) : null,
        ]);
    }
}
