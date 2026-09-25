<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SaveUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return ($this->user()?->isSuperAdmin() ?? false)
            && (! $target instanceof User || (! $target->isResidentAccount() && $target->resident_id === null));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $cabinetAccess = $target instanceof User ? $target->cabinetAccess : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'role' => ['required', Rule::in(['admin', 'staff', 'viewer'])],
            'is_active' => ['required', 'boolean'],
            'password' => [
                $this->isMethod('POST') ? 'required' : 'nullable',
                'string',
                Password::min(12),
                'max:255',
            ],

            'smart_cabinet_access' => ['sometimes', 'boolean'],

            'rpi_employee_id' => [
                Rule::requiredIf($this->boolean('smart_cabinet_access')),
                'nullable',
                'string',
                'max:32',
                'regex:/^[A-Za-z][A-Za-z0-9_-]*$/',
                Rule::unique('employee_cabinet_access', 'rpi_employee_id')
                    ->ignore($cabinetAccess?->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('rpi_employee_id')) {
            $this->merge([
                'rpi_employee_id' => strtoupper(trim((string) $this->input('rpi_employee_id'))),
            ]);
        }
    }
}
