<?php

namespace App\Models;

use Database\Factories\CabinetDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $identifier
 * @property string $name
 * @property Carbon|null $last_seen_at
 * @property string|null $reported_status
 * @property array<string, mixed>|null $component_health
 * @property array<string, mixed>|null $cabinet_state
 * @property string|null $api_token_hash
 */
class CabinetDevice extends Model
{
    /** @use HasFactory<CabinetDeviceFactory> */
    use HasFactory;

    protected $fillable = ['identifier', 'name'];

    protected $hidden = ['api_token_hash'];

    /** @return HasMany<FileMovementEvent, $this> */
    public function fileMovements(): HasMany
    {
        return $this->hasMany(FileMovementEvent::class);
    }

    public function connectionStatus(): string
    {
        if (! $this->last_seen_at || ! $this->reported_status) {
            return 'unknown';
        }

        if ($this->last_seen_at->lt(now()->subMinutes(5))) {
            return 'offline';
        }

        return $this->reported_status;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'component_health' => 'array', 'cabinet_state' => 'array'];
    }
}
