<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordCaseActivity
{
    /** @param list<string> $changedFields */
    public function handle(?User $actor, string $action, string $record, array $changedFields = []): void
    {
        DB::table('administrative_audits')->insert([
            'user_id' => $actor?->id, 'actor' => $actor === null ? 'System' : $actor->name, 'action' => $action,
            'type' => str_contains($action, 'restriction') ? 'cert' : 'incident', 'record' => $record,
            'changed_fields' => json_encode($changedFields, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
