<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }

    /**
     * Resident Portal passwords: the rule shown on the registration and reset pages, used for both, so a
     * resident who resets a password meets the same requirement they met when creating the account.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function residentPasswordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(12)->mixedCase()->numbers(), 'max:255'];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
