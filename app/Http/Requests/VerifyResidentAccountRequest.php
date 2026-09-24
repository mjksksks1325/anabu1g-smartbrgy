<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class VerifyResidentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('resident')->guest();
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['resident_number' => ['required', 'string', 'max:40'], 'activation_code' => ['required', 'string', 'max:100']];
    }

    protected function prepareForValidation(): void
    {
        $this->session()->forget('resident_verification');
        if (is_string($this->input('resident_number'))) {
            $this->merge(['resident_number' => strtoupper(trim($this->input('resident_number')))]);
        }
    }
}
