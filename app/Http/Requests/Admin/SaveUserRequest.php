<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\StaffPermissions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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

        return ($this->user()?->hasAdministrativeAccess() ?? false)
            && (! $target instanceof User || (! $target->isResidentAccount() && $target->resident_id === null))
            && (! $target instanceof User || ! $target->is_super_admin || $this->user()->isSuperAdmin());
    }

    /**
     * A refused attempt to change a Super Admin account by a non–Super Admin is a security event: record it,
     * then refuse as usual. Captains and other administrators cannot change Super Admin accounts.
     */
    protected function failedAuthorization(): void
    {
        $target = $this->route('user');
        if ($target instanceof User && $target->is_super_admin && ($this->user()?->hasAdministrativeAccess() ?? false)) {
            DB::table('administrative_audits')->insert([
                'user_id' => $this->user()->id, 'actor' => $this->user()->name, 'action' => 'security.super-admin-account.change-denied',
                'type' => 'security', 'record' => (string) $target->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        parent::failedAuthorization();
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

        $allowedRoles = $this->isMethod('POST')
            ? ['staff']
            : ($target instanceof User && $target->is_super_admin ? ['admin'] : ['staff', 'captain']);

        if ($target instanceof User && in_array($target->role, ['admin', 'viewer'], true)) {
            $allowedRoles[] = $target->role;
        }

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
            'role' => ['required', Rule::in($allowedRoles)],
            'is_active' => ['required', 'boolean'],
            'password' => [
                $this->isMethod('POST') ? 'required' : 'nullable',
                'string',
                Password::default(),
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
            $target = $this->route('user');
            // A Super Admin's sign-in credentials change only through that Super Admin, the password reset link or the server command.
            if ($target instanceof User && $target->is_super_admin && ! $this->user()->is($target)) {
                if ($this->filled('password')) {
                    $validator->errors()->add('password', 'Only this Super Admin can change their own password. Use the password reset link or the server command.');
                }
                if (strtolower(trim((string) $this->input('email'))) !== strtolower($target->email)) {
                    $validator->errors()->add('email', 'Only this Super Admin can change their own email address.');
                }
            }
            if ($this->input('role') === 'captain' && $this->boolean('is_active')) {
                $captainQuery = User::query()
                    ->where('role', 'captain')
                    ->where('is_active', true)
                    ->whereNull('resident_id');
                if ($target instanceof User) {
                    $captainQuery->where($target->getKeyName(), '<>', $target->getKey());
                }
                $captainExists = $captainQuery->exists();

                if ($captainExists) {
                    $validator->errors()->add('role', 'There is already an active Barangay Captain. Move that designation first before assigning another.');
                }
            }

            if (! is_array($permissions)) {
                return;
            }
            $mayAssignSuperAdmin = $target instanceof User && $target->is_super_admin && $this->input('role') === 'admin';
            if ($permissions !== [] && $this->input('role') !== 'staff' && ! $mayAssignSuperAdmin) {
                // A Captain already holds every other module; only VAWC/BPO access can be recorded on a Captain account.
                $otherThanVawc = array_filter($permissions, fn (mixed $permission): bool => ! is_string($permission) || ! str_starts_with($permission, 'vawc.'));
                if ($this->input('role') !== 'captain' || $otherThanVawc !== []) {
                    $validator->errors()->add('staff_permissions', $this->input('role') === 'captain'
                        ? 'A Captain account already has every other module; only VAWC/BPO access can be assigned to it.'
                        : 'Custom assignments are only available for Barangay Staff accounts.');
                }
            }
            if ($this->has('staff_permissions') && self::vawcKeys($target instanceof User ? ($target->staff_permissions ?? []) : []) !== self::vawcKeys($permissions)) {
                // Confidential case access is granted only by the Super Admin, and never to themselves.
                if (! $this->user()->isSuperAdmin()) {
                    $validator->errors()->add('staff_permissions', 'Only the Super Admin can grant or remove VAWC/BPO access.');
                } elseif ($target instanceof User && $this->user()->is($target)) {
                    $validator->errors()->add('staff_permissions', 'You cannot change your own VAWC/BPO access.');
                }
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

    /**
     * @param  array<mixed>  $permissions
     * @return list<string>
     */
    public static function vawcKeys(array $permissions): array
    {
        $keys = array_values(array_unique(array_filter($permissions, fn (mixed $permission): bool => is_string($permission) && str_starts_with($permission, 'vawc.'))));
        sort($keys);

        return $keys;
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
