<?php

namespace App\Models;

use Database\Factories\FileMovementEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $file_reference
 * @property string|null $file_name
 * @property string|null $rfid_tag
 * @property string|null $drawer_reference
 * @property string $action
 * @property Carbon $occurred_at
 * @property User $user
 * @property CabinetDevice|null $cabinetDevice
 */
class FileMovementEvent extends Model
{
    public const REMOVED = 'removed';

    public const RETURNED = 'returned';

    /** @use HasFactory<FileMovementEventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('File movement history cannot be changed.'));
        static::deleting(fn (): never => throw new LogicException('File movement history cannot be deleted.'));
    }

    protected $fillable = [
        'file_reference', 'file_name', 'rfid_tag', 'cabinet_device_id', 'drawer_reference',
        'user_id', 'action', 'occurred_at', 'device_event_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CabinetDevice, $this> */
    public function cabinetDevice(): BelongsTo
    {
        return $this->belongsTo(CabinetDevice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
