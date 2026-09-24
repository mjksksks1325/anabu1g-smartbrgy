<?php

namespace App\Http\Controllers\Api;

use App\FileMovementRecorder;
use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use App\Models\FileMovementEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CabinetEventController extends Controller
{
    public function heartbeat(Request $request, CabinetDevice $cabinet): JsonResponse
    {
        $data = $request->validate([
            'door_state' => ['sometimes', Rule::in(['open', 'closed'])],
            'drawer_reference' => ['required_with:drawer_state', 'string', 'max:255'],
            'drawer_state' => ['required_with:drawer_reference', Rule::in(['open', 'closed'])],
            'software_version' => ['sometimes', 'string', 'max:255'],
        ]);

        $state = $cabinet->cabinet_state ?? [];

        if (isset($data['door_state'])) {
            $state['door'] = $data['door_state'];
        }

        if (isset($data['drawer_reference'], $data['drawer_state'])) {
            $state['drawers'][$data['drawer_reference']] = $data['drawer_state'];
        }

        $cabinet->forceFill([
            'last_seen_at' => now(),
            'reported_status' => 'online',
            'cabinet_state' => $state === [] ? null : $state,
            'software_version' => $data['software_version'] ?? $cabinet->software_version,
        ])->save();

        return response()->json(['status' => 'online', 'cabinet' => $cabinet->identifier, 'state' => $state]);
    }

    public function movement(Request $request, CabinetDevice $cabinet, FileMovementRecorder $recorder): JsonResponse
    {
        $allowedFields = [
            'event_id', 'file_reference', 'file_name', 'rfid_tag', 'drawer_reference',
            'rpi_employee_id', 'action', 'occurred_at',
        ];
        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages(['payload' => 'Only movement fields are accepted.']);
        }

        $input = $request->input('rpi_employee_id');
        if (is_string($input)) {
            $request->merge(['rpi_employee_id' => Str::upper(trim($input))]);
        }

        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:200'],
            'file_reference' => ['required', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'rfid_tag' => ['nullable', 'string', 'max:255'],
            'drawer_reference' => ['nullable', 'string', 'max:255'],
            'rpi_employee_id' => ['required', 'string', 'max:32', 'regex:/\A[A-Z][A-Z0-9_-]*\z/'],
            'action' => ['required', Rule::in([FileMovementEvent::REMOVED, FileMovementEvent::RETURNED])],
            'occurred_at' => ['required', 'date'],
        ]);

        $access = EmployeeCabinetAccess::query()
            ->with('user')
            ->where('rpi_employee_id', $data['rpi_employee_id'])
            ->first();
        $employee = $access?->user;

        if (! $employee || ! in_array($employee->role, ['admin', 'staff'], true)
            || ! $access->isEffective()) {
            throw ValidationException::withMessages(['rpi_employee_id' => 'An eligible cabinet employee is required.']);
        }

        $movement = $recorder->record(
            $data['file_reference'],
            $employee,
            $data['action'],
            Carbon::parse($data['occurred_at']),
            cabinet: $cabinet,
            drawerReference: $data['drawer_reference'] ?? null,
            fileName: $data['file_name'] ?? null,
            rfidTag: $data['rfid_tag'] ?? null,
            deviceEventId: $cabinet->id.':'.$data['event_id'],
        );

        if (! $movement->wasRecentlyCreated && (
            $movement->file_reference !== $data['file_reference']
            || $movement->action !== $data['action']
            || $movement->user_id !== $employee->id
            || $movement->cabinet_device_id !== $cabinet->id
        )) {
            abort(409, 'The event ID has already been used for a different movement.');
        }

        $cabinet->forceFill(['last_seen_at' => now(), 'reported_status' => 'online'])->save();

        return response()->json([
            'id' => $movement->id,
            'event_id' => $data['event_id'],
            'duplicate' => ! $movement->wasRecentlyCreated,
        ], $movement->wasRecentlyCreated ? 201 : 200);
    }

    public function employeeEnrollment(Request $request, CabinetDevice $cabinet): JsonResponse
    {
        $allowedFields = ['rpi_employee_id', 'rfid_enrolled', 'face_enrolled'];
        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages(['payload' => 'Only enrollment confirmations are accepted.']);
        }

        $input = $request->input('rpi_employee_id');
        if (is_string($input)) {
            $request->merge(['rpi_employee_id' => Str::upper(trim($input))]);
        }

        $data = $request->validate([
            'rpi_employee_id' => ['required', 'string', 'max:32', 'regex:/\A[A-Z][A-Z0-9_-]*\z/'],
            'rfid_enrolled' => ['sometimes', 'required', 'boolean'],
            'face_enrolled' => ['sometimes', 'required', 'boolean'],
        ]);

        if (! array_key_exists('rfid_enrolled', $data) && ! array_key_exists('face_enrolled', $data)) {
            throw ValidationException::withMessages(['enrollment' => 'Confirm at least one enrollment type.']);
        }

        foreach (['rfid_enrolled', 'face_enrolled'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== true) {
                throw ValidationException::withMessages([$field => 'Only confirmed enrollment can be reported.']);
            }
        }

        $access = DB::transaction(function () use ($data): EmployeeCabinetAccess {
            $access = EmployeeCabinetAccess::query()
                ->where('rpi_employee_id', $data['rpi_employee_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $confirmedAt = Carbon::now();

            if ($data['rfid_enrolled'] ?? false) {
                $access->rfid_enrollment_status = EmployeeCabinetAccess::ENROLLED;
                $access->rfid_enrolled_at ??= $confirmedAt;
            }

            if ($data['face_enrolled'] ?? false) {
                $access->face_enrollment_status = EmployeeCabinetAccess::ENROLLED;
                $access->face_enrolled_at ??= $confirmedAt;
            }

            if ($access->isDirty()) {
                $access->save();
            }

            return $access;
        });

        $access->load('user');

        return response()->json([
            'rpi_employee_id' => $access->rpi_employee_id,
            'rfid_enrollment_status' => $access->rfid_enrollment_status,
            'face_enrollment_status' => $access->face_enrollment_status,
            'effective_access' => $access->isEffective(),
        ]);
    }
}
