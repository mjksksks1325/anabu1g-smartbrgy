<?php

namespace App;

use App\Models\CabinetDevice;
use App\Models\FileMovementEvent;
use App\Models\User;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class FileMovementRecorder
{
    public function record(
        string $fileReference,
        User $employee,
        string $action,
        CarbonInterface $occurredAt,
        ?CabinetDevice $cabinet = null,
        ?string $drawerReference = null,
        ?string $fileName = null,
        ?string $rfidTag = null,
        ?string $deviceEventId = null,
    ): FileMovementEvent {
        if (! in_array($action, [FileMovementEvent::REMOVED, FileMovementEvent::RETURNED], true)) {
            throw new InvalidArgumentException('Unsupported file movement action.');
        }

        if ($employee->isResidentAccount() || $employee->resident_id !== null) {
            throw new InvalidArgumentException('A barangay employee is required.');
        }

        $attributes = [
            'file_reference' => $fileReference,
            'file_name' => $fileName,
            'rfid_tag' => $rfidTag,
            'cabinet_device_id' => $cabinet?->id,
            'drawer_reference' => $drawerReference,
            'user_id' => $employee->id,
            'action' => $action,
            'occurred_at' => $occurredAt,
            'device_event_id' => $deviceEventId,
        ];

        if ($deviceEventId !== null) {
            return FileMovementEvent::query()->firstOrCreate(['device_event_id' => $deviceEventId], $attributes);
        }

        return FileMovementEvent::query()->create($attributes);
    }
}
