<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterResidentAccountRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return Auth::guard('resident')->guest();
    }

    /** @return array<string, array<int, string|Password|ValidationRule|array<mixed>>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => $this->residentPasswordRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }
}
