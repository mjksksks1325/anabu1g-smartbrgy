<?php

namespace App\Models;

use Database\Factories\CabinetReaderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $cabinet_device_id
 * @property string $reader_identifier
 * @property string $model
 * @property string $drawer_reference
 * @property bool $is_active
 */
class CabinetReader extends Model
{
    /** @use HasFactory<CabinetReaderFactory> */
    use HasFactory;

    protected $fillable = [
        'cabinet_device_id',
        'reader_identifier',
        'model',
        'drawer_reference',
        'is_active',
    ];

    /** @return BelongsTo<CabinetDevice, $this> */
    public function cabinetDevice(): BelongsTo
    {
        return $this->belongsTo(CabinetDevice::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
