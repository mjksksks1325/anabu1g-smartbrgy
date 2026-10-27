<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $request_id
 * @property int $cabinet_device_id
 * @property string $rpi_employee_id
 * @property bool $remove_rfid
 * @property bool $remove_face
 * @property Carbon|null $acknowledged_at
 */
class CabinetCredentialRevocation extends Model
{
    protected $fillable = [
        'request_id',
        'cabinet_device_id',
        'rpi_employee_id',
        'remove_rfid',
        'remove_face',
        'acknowledged_at',
    ];

    /** @return BelongsTo<CabinetDevice, $this> */
    public function cabinetDevice(): BelongsTo
    {
        return $this->belongsTo(CabinetDevice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'remove_rfid' => 'boolean',
            'remove_face' => 'boolean',
            'acknowledged_at' => 'datetime',
        ];
    }
}
