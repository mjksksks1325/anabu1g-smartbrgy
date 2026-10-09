<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\StaffPermissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'staff_permissions' => ['sometimes', 'array', 'max:100'],
            'staff_permissions.*' => ['required', 'string', 'distinct', Rule::in(StaffPermissions::keys())],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'role' => ['required', Rule::in($target instanceof User && in_array($target->role, ['admin', 'viewer'], true) ? ['staff', $target->role] : ['staff'])],
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

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $permissions = $this->input('staff_permissions', []);
            if (! is_array($permissions)) {
                return;
            }
            if ($permissions !== [] && $this->input('role') !== 'staff') {
                $validator->errors()->add('staff_permissions', 'Assignments are only available for Barangay Staff accounts.');
            }
            foreach ($permissions as $permission) {
                if (! is_string($permission) || ! str_contains($permission, '.')) {
                    continue;
                }
                [$module, $action] = explode('.', $permission, 2);
                if ($action !== 'view' && ! in_array($permission, ['incidents.submit', 'vawc.submit'], true) && ! in_array($module.'.view', $permissions, true)) {
                    $validator->errors()->add('staff_permissions', 'Select access to '.$module.' before assigning its actions.');
                }
            }
        }];
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
