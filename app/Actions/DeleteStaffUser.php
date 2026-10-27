<?php

namespace App\Actions;

use App\Models\CabinetCredentialRevocation;
use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeleteStaffUser
{
    /**
     * @return array{pending_rpi_revocations: int, rpi_mapping_missing: bool, rpi_delivery_unavailable: bool}
     */
    public function handle(User $actor, User $target): array
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($target->is_super_admin) {
            throw ValidationException::withMessages([
                'user' => 'Super Admin accounts cannot be deleted here.',
            ]);
        }

        if ($target->isResidentAccount() || $target->resident_id !== null) {
            abort(403);
        }

        return DB::transaction(function () use ($actor, $target): array {
            $lockedUser = User::query()
                ->lockForUpdate()
                ->findOrFail($target->id);

            if ($actor->is($lockedUser) || $lockedUser->is_super_admin) {
                throw ValidationException::withMessages([
                    'user' => 'This account cannot be deleted.',
                ]);
            }

            $access = EmployeeCabinetAccess::query()
                ->where('user_id', $lockedUser->id)
                ->lockForUpdate()
                ->first();
            $wasActive = $lockedUser->is_active;

            $pendingRevocations = 0;
            $hasRpiMapping = $access?->rpi_employee_id !== null;
            $rpiMappingMissing = $access !== null
                && $access->rpi_employee_id === null
                && ($access->rfid_enrollment_status === EmployeeCabinetAccess::ENROLLED
                    || $access->face_enrollment_status === EmployeeCabinetAccess::ENROLLED);

            if ($access?->rpi_employee_id !== null) {
                $devices = CabinetDevice::query()
                    ->whereNotNull('api_token_hash')
                    ->orderBy('id')
                    ->get(['id']);

                foreach ($devices as $device) {
                    CabinetCredentialRevocation::query()->create([
                        'request_id' => (string) Str::uuid(),
                        'cabinet_device_id' => $device->id,
                        'rpi_employee_id' => $access->rpi_employee_id,
                        'remove_rfid' => true,
                        'remove_face' => true,
                    ]);
                    $pendingRevocations++;
                }
            }

            $access?->forceFill([
                'is_active' => false,
                'authorization_version' => $access->authorization_version + 1,
            ])->save();

            DB::table('password_reset_tokens')
                ->where('email', $lockedUser->email)
                ->delete();

            $lockedUser->forceFill([
                'is_active' => false,
                'password' => Str::random(80),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'staff_permissions' => null,
            ])->save();
            $lockedUser->delete();

            DB::table('administrative_audits')->insert([
                'user_id' => $actor->id,
                'actor' => $actor->name,
                'action' => 'admin.users.deleted',
                'type' => 'security',
                'record' => (string) $lockedUser->id,
                'target_user_id' => $lockedUser->id,
                'before_assignments' => json_encode([
                    'role' => $lockedUser->role,
                    'is_active' => $wasActive,
                    'rpi_employee_id' => $access?->rpi_employee_id,
                    'rfid_enrolled' => $access?->rfid_enrollment_status === EmployeeCabinetAccess::ENROLLED,
                    'face_enrolled' => $access?->face_enrollment_status === EmployeeCabinetAccess::ENROLLED,
                ], JSON_THROW_ON_ERROR),
                'after_assignments' => json_encode([
                    'account_deleted' => true,
                    'cabinet_access' => false,
                    'pending_rpi_revocations' => $pendingRevocations,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'pending_rpi_revocations' => $pendingRevocations,
                'rpi_mapping_missing' => $rpiMappingMissing,
                'rpi_delivery_unavailable' => $hasRpiMapping && $pendingRevocations === 0,
            ];
        });
    }
}
