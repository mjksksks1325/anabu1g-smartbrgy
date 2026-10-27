<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CabinetCredentialRevocation;
use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CabinetCredentialRevocationController extends Controller
{
    public function pending(CabinetDevice $cabinet): JsonResponse
    {
        $revocations = CabinetCredentialRevocation::query()
            ->where('cabinet_device_id', $cabinet->id)
            ->whereNull('acknowledged_at')
            ->oldest('id')
            ->limit(100)
            ->get(['request_id', 'rpi_employee_id', 'remove_rfid', 'remove_face', 'created_at'])
            ->map(fn (CabinetCredentialRevocation $revocation): array => [
                'request_id' => $revocation->request_id,
                'rpi_employee_id' => $revocation->rpi_employee_id,
                'remove_rfid' => $revocation->remove_rfid,
                'remove_face' => $revocation->remove_face,
                'requested_at' => $revocation->created_at?->toIso8601String(),
            ]);

        return response()->json(['revocations' => $revocations->values()]);
    }

    public function acknowledge(CabinetDevice $cabinet, string $requestId): JsonResponse
    {
        abort_unless(Str::isUuid($requestId), 404);

        $acknowledged = DB::transaction(function () use ($cabinet, $requestId): CabinetCredentialRevocation {
            $revocationQuery = CabinetCredentialRevocation::query()
                ->where('cabinet_device_id', $cabinet->id)
                ->where('request_id', $requestId);
            $revocation = $revocationQuery->firstOrFail();

            $access = EmployeeCabinetAccess::query()
                ->where('rpi_employee_id', $revocation->rpi_employee_id)
                ->lockForUpdate()
                ->first();

            $revocation = $revocationQuery->lockForUpdate()->firstOrFail();
            $revocation->acknowledged_at ??= now();
            $revocation->save();

            $hasPendingRequests = CabinetCredentialRevocation::query()
                ->where('rpi_employee_id', $revocation->rpi_employee_id)
                ->whereNull('acknowledged_at')
                ->exists();

            $deletedUser = $access === null
                ? null
                : User::withTrashed()->find($access->user_id);

            if (! $hasPendingRequests && $access !== null && $deletedUser?->trashed()) {
                $access->forceFill([
                    'rpi_employee_id' => null,
                    'rfid_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
                    'face_enrollment_status' => EmployeeCabinetAccess::NOT_STARTED,
                    'rfid_enrolled_at' => null,
                    'face_enrolled_at' => null,
                ])->save();
            }

            return $revocation;
        });

        return response()->json([
            'status' => 'acknowledged',
            'request_id' => $acknowledged->request_id,
            'acknowledged_at' => $acknowledged->acknowledged_at?->toIso8601String(),
        ]);
    }
}
