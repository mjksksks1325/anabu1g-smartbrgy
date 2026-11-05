<?php

namespace App\Models;

use Database\Factories\CabinetFileEnrollmentRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $request_id
 * @property int $cabinet_file_id
 * @property int $cabinet_device_id
 * @property int $cabinet_reader_id
 * @property string $reader_identifier
 * @property array{file_reference: string, file_name: string, drawer_reference: string} $folder_snapshot
 * @property string $status
 * @property string|null $rfid_epc
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property CabinetFile $cabinetFile
 * @property CabinetReader $cabinetReader
 */
class CabinetFileEnrollmentRequest extends Model
{
    /** @use HasFactory<CabinetFileEnrollmentRequestFactory> */
    use HasFactory;

    public const PENDING = 'pending';

    public const ENROLLED = 'enrolled';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    protected $fillable = [
        'request_id', 'cabinet_file_id', 'cabinet_device_id', 'cabinet_reader_id',
        'reader_identifier', 'folder_snapshot', 'status', 'rfid_epc', 'requested_by', 'expires_at', 'completed_at',
    ];

    public static function expireForCabinet(int $cabinetId): void
    {
        static::query()->where('cabinet_device_id', $cabinetId)->where('status', self::PENDING)
            ->where('expires_at', '<=', now())->update(['status' => self::EXPIRED]);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<CabinetFile, $this> */
    public function cabinetFile(): BelongsTo
    {
        return $this->belongsTo(CabinetFile::class);
    }

    /** @return BelongsTo<CabinetReader, $this> */
    public function cabinetReader(): BelongsTo
    {
        return $this->belongsTo(CabinetReader::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['folder_snapshot' => 'array', 'expires_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
