<?php

use App\FileMovementRecorder;
use App\Models\FileMovementEvent;
use App\Models\User;

it('records real file movements with idempotent device event IDs', function () {
    $employee = User::factory()->create(['role' => 'staff']);
    $recorder = app(FileMovementRecorder::class);
    $first = $recorder->record('FILE-001', $employee, FileMovementEvent::REMOVED, now(), deviceEventId: 'device-event-1');
    $again = $recorder->record('FILE-001', $employee, FileMovementEvent::REMOVED, now(), deviceEventId: 'device-event-1');

    expect($first->id)->toBe($again->id);
    $this->assertDatabaseCount('file_movement_events', 1);
    expect($first->fresh()->user_id)->toBe($employee->id);
});

it('rejects invalid actions and preserves movement history', function () {
    $employee = User::factory()->create(['role' => 'staff']);
    $recorder = app(FileMovementRecorder::class);
    expect(fn () => $recorder->record('FILE-001', $employee, 'unlocked', now()))->toThrow(InvalidArgumentException::class);

    $event = $recorder->record('FILE-001', $employee, FileMovementEvent::RETURNED, now());
    expect(fn () => $event->update(['action' => FileMovementEvent::REMOVED]))->toThrow(LogicException::class);
    expect(fn () => $event->delete())->toThrow(LogicException::class);
    $this->assertDatabaseCount('file_movement_events', 1);
});
