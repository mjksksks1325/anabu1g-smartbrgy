<?php

namespace App\Models;

use Database\Factories\CabinetDeviceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

        if ($this->last_seen_at->lt(now()->subSeconds(90))) {
            return 'offline';
        }

        return $this->reported_status;
    }

    /**
     * Readable component health entries for the staff dashboard.
     *
     * @return list<array{label: string, status: string, tone: string}>
     */
    public function componentHealthItems(): array
    {
        return collect($this->component_health ?? [])
            ->map(function (mixed $status, string $component): array {
                $statusText = is_scalar($status) ? (string) $status : json_encode($status, JSON_UNESCAPED_SLASHES);

                return [
                    'label' => (string) Str::of($component)->replace('facelock', 'face lock')->headline()->replace('Rfid', 'RFID'),
                    'status' => Str::headline($statusText),
                    'tone' => match (strtolower($statusText)) {
                        'connected', 'healthy', 'ok', 'online', 'ready' => 'success',
                        'disconnected', 'unhealthy', 'error', 'offline', 'failed' => 'warning',
                        default => 'neutral',
                    },
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'component_health' => 'array', 'cabinet_state' => 'array'];
    }
}
