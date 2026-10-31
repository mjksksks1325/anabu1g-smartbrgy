<?php

namespace App\Models;

use Database\Factories\CabinetFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $file_reference
 * @property string $file_name
 * @property int $cabinet_device_id
 * @property string $drawer_reference
 * @property string|null $rfid_epc
 * @property bool $is_active
 */
class CabinetFile extends Model
{
    public const DRAWER_1 = 'drawer_1';

    public const DRAWER_2 = 'drawer_2';

    /** @use HasFactory<CabinetFileFactory> */
    use HasFactory;

    protected $fillable = [
        'file_reference',
        'file_name',
        'cabinet_device_id',
        'drawer_reference',
        'is_active',
    ];

    /** @return BelongsTo<CabinetDevice, $this> */
    public function cabinetDevice(): BelongsTo
    {
        return $this->belongsTo(CabinetDevice::class);
    }

    public function isRfidEnrolled(): bool
    {
        return $this->rfid_epc !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
