<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CabinetDevice;
use App\Models\EmployeeCabinetAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CabinetAccessEventController extends Controller
{
    public function __invoke(Request $request, CabinetDevice $cabinet): JsonResponse
    {
        $allowedFields = [
            'event_id', 'rpi_employee_id', 'result', 'occurred_at',
            'authentication_method', 'authentication_verified', 'door_sensor_state',
        ];
        if (array_diff(array_keys($request->all()), $allowedFields) !== []) {
            throw ValidationException::withMessages(['payload' => 'Only cabinet access event fields are accepted.']);
        }

        if (is_string($request->input('rpi_employee_id'))) {
            $request->merge(['rpi_employee_id' => Str::upper(trim($request->input('rpi_employee_id')))]);
        }

        $data = $request->validate([
            'event_id' => ['required', 'string', 'max:200'],
            'rpi_employee_id' => ['nullable', 'string', 'max:32', 'regex:/\A[A-Z][A-Z0-9_-]*\z/'],
            'result' => ['required', Rule::in(['attempted', 'denied', 'granted', 'opened'])],
            'occurred_at' => ['required', 'date'],
            'authentication_method' => ['sometimes', Rule::in(['rfid', 'face', 'rfid_and_face'])],
            'authentication_verified' => ['sometimes', 'boolean'],
            'door_sensor_state' => ['sometimes', Rule::in(['open', 'closed'])],
        ]);

        if (($data['result'] === 'opened') !== (($data['door_sensor_state'] ?? null) === 'open')) {
            throw ValidationException::withMessages(['door_sensor_state' => 'A confirmed opening requires an open door sensor report.']);
        }

        if (isset($data['authentication_method']) && ! ($data['authentication_verified'] ?? false)) {
            throw ValidationException::withMessages(['authentication_verified' => 'The device must confirm authentication before its method is recorded.']);
        }

        if (($data['authentication_verified'] ?? false) && ! isset($data['authentication_method'])) {
            throw ValidationException::withMessages(['authentication_method' => 'A verified authentication method is required.']);
        }

        if (in_array($data['result'], ['attempted', 'denied'], true) && isset($data['authentication_method'])) {
            throw ValidationException::withMessages(['authentication_method' => 'Authentication details belong to granted access only.']);
        }

        $deviceEventId = $cabinet->id.':'.$data['event_id'];
        $occurredAt = Carbon::parse($data['occurred_at'])->utc()->toDateTimeString();
        $result = DB::transaction(function () use ($cabinet, $data, $deviceEventId, $occurredAt): array {
            $existing = DB::table('administrative_audits')->where('device_event_id', $deviceEventId)->first();
            if ($existing !== null) {
                $this->ensureSameEvent($existing, $data, $occurredAt);

                return ['id' => $existing->id, 'duplicate' => true];
            }

            $access = isset($data['rpi_employee_id'])
                ? EmployeeCabinetAccess::query()->with('user')->where('rpi_employee_id', $data['rpi_employee_id'])->first()
                : null;
            $employee = $access === null ? null : $access->user;

            if ($data['result'] === 'granted' && (! $employee
                || ! in_array($employee->role, ['admin', 'staff'], true) || ! $access->isEffective())) {
                throw ValidationException::withMessages(['rpi_employee_id' => 'Granted access requires an eligible mapped employee.']);
            }

            if (isset($data['authentication_method']) && (! $employee || ! $access->isEffective())) {
                throw ValidationException::withMessages(['authentication_method' => 'Verified authentication requires an eligible mapped employee.']);
            }

            if (isset($data['authentication_method']) && (
                (in_array($data['authentication_method'], ['rfid', 'rfid_and_face'], true)
                    && $access->rfid_enrollment_status !== EmployeeCabinetAccess::ENROLLED)
                || (in_array($data['authentication_method'], ['face', 'rfid_and_face'], true)
                    && $access->face_enrollment_status !== EmployeeCabinetAccess::ENROLLED)
            )) {
                throw ValidationException::withMessages(['authentication_method' => 'The reported authentication method is not enrolled for this employee.']);
            }

            $inserted = DB::table('administrative_audits')->insertOrIgnore([
                'user_id' => $employee?->id,
                'actor' => $employee === null ? 'Unmapped employee' : $employee->name,
                'action' => 'cabinet.access.'.$data['result'],
                'type' => 'cabinet',
                'record' => $cabinet->identifier,
                'device_event_id' => $deviceEventId,
                'cabinet_identifier' => $cabinet->identifier,
                'rpi_employee_id' => $data['rpi_employee_id'] ?? null,
                'access_result' => $data['result'],
                'authentication_method' => $data['authentication_method'] ?? null,
                'created_at' => $occurredAt,
                'updated_at' => now(),
            ]);

            $event = DB::table('administrative_audits')->where('device_event_id', $deviceEventId)->firstOrFail();
            $this->ensureSameEvent($event, $data, $occurredAt);

            return ['id' => $event->id, 'duplicate' => $inserted === 0];
        });

        return response()->json($result + ['event_id' => $data['event_id']], $result['duplicate'] ? 200 : 201);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureSameEvent(\stdClass $event, array $data, string $occurredAt): void
    {
        $stored = (array) $event;

        abort_unless($stored['access_result'] === $data['result']
            && $stored['rpi_employee_id'] === ($data['rpi_employee_id'] ?? null)
            && $stored['authentication_method'] === ($data['authentication_method'] ?? null)
            && Carbon::parse($stored['created_at'])->equalTo(Carbon::parse($occurredAt)), 409,
            'The event ID has already been used for a different access event.');
    }
}
