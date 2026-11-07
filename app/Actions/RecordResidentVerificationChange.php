<?php

namespace App\Actions;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * An audit entry for changes to a resident's verification and identity details, listing which fields changed.
 * Status fields keep their before and after values (they are yes/no or active/inactive); identity details such
 * as nationality, name or birth date are recorded only as "recorded", "changed" or "cleared", so the audit log
 * never copies personal details. Shown only to administrators (Audit Log).
 */
class RecordResidentVerificationChange
{
    public const ACTION = 'admin.residents.verification-changed';

    /** Fields whose before and after values are safe to keep. */
    public const STATUS_FIELDS = ['is_verified_indigent', 'is_in_good_standing', 'status'];

    /** Fields recorded by name only. */
    public const IDENTITY_FIELDS = ['nationality', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'civil_status', 'photo_path'];

    /** @var array<string, string> */
    public const LABELS = [
        'is_verified_indigent' => 'Indigency verified', 'is_in_good_standing' => 'Good standing', 'status' => 'Record status',
        'nationality' => 'Nationality', 'first_name' => 'First name', 'middle_name' => 'Middle name', 'last_name' => 'Last name',
        'suffix' => 'Suffix', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender', 'civil_status' => 'Civil status', 'photo_path' => 'Photo',
    ];

    /**
     * The fields this record tracks, as they stand now, to compare after a change.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(?Resident $resident): array
    {
        $values = [];
        foreach ([...self::STATUS_FIELDS, ...self::IDENTITY_FIELDS] as $field) {
            $value = $resident?->getAttribute($field);
            $values[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $values;
    }

    /**
     * Record the tracked fields that differ between two snapshots; nothing is written when none changed.
     *
     * @param  array<string, mixed>  $before  self::snapshot() before the change (all nulls for a new record)
     */
    public function handle(User $actor, Resident $resident, array $before, bool $created = false): void
    {
        $after = self::snapshot($resident);
        $changes = [];

        // A new record notes only what differs from the defaults: verified indigency, not in good standing, inactive.
        $notableOnCreate = ['is_verified_indigent' => true, 'is_in_good_standing' => false, 'status' => 'inactive'];
        foreach (self::STATUS_FIELDS as $field) {
            $old = $this->normalise($before[$field] ?? null);
            $new = $this->normalise($after[$field]);
            if ($created ? $new === $notableOnCreate[$field] : $old !== $new) {
                $changes[$field] = ['from' => $created ? null : $old, 'to' => $new];
            }
        }
        foreach (self::IDENTITY_FIELDS as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field];
            if ($created && ! ($field === 'nationality' || $field === 'photo_path')) {
                continue;
            }
            if ((string) $old !== (string) $new) {
                $changes[$field] = blank($old) ? 'recorded' : (blank($new) ? 'cleared' : 'changed');
            }
        }

        if ($changes === []) {
            return;
        }

        DB::table('administrative_audits')->insert([
            'user_id' => $actor->id,
            'actor' => $actor->name,
            'action' => self::ACTION,
            'type' => 'record',
            'record' => (string) $resident->id,
            'changed_fields' => json_encode($changes, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The Audit Log line for one entry, e.g. "Resident #27 - Indigency verified: no → yes; Nationality: recorded".
     */
    public static function describe(string $record, ?string $changedFields): string
    {
        $changes = json_decode((string) $changedFields, true) ?: [];
        $parts = [];
        foreach ($changes as $field => $change) {
            $label = self::LABELS[$field] ?? $field;
            $parts[] = is_array($change)
                ? $label.': '.self::value($change['from'] ?? null).' → '.self::value($change['to'] ?? null)
                : $label.': '.$change;
        }

        return 'Resident #'.$record.' - '.implode('; ', $parts);
    }

    private static function value(mixed $value): string
    {
        return match (true) {
            $value === null => 'not set',
            $value === true => 'yes',
            $value === false => 'no',
            default => (string) $value,
        };
    }

    private function normalise(mixed $value): mixed
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value,
            in_array($value, [0, 1, '0', '1'], true) => (bool) (int) $value,
            default => (string) $value,
        };
    }
}
