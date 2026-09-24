<?php

namespace App\Models;

use Database\Factories\EmployeeCabinetAccessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property bool $is_active
 * @property string $rfid_enrollment_status
 * @property string $face_enrollment_status
 * @property Carbon|null $rfid_enrolled_at
 * @property Carbon|null $face_enrolled_at
 * @property int $authorization_version
 * @property string|null $rpi_employee_id
 * @property User $user
 */
class EmployeeCabinetAccess extends Model
{
    public const NOT_STARTED = 'not_started';

    public const PENDING = 'pending';

    public const ENROLLED = 'enrolled';

    /** @use HasFactory<EmployeeCabinetAccessFactory> */
    use HasFactory;

    protected $table = 'employee_cabinet_access';

    protected $fillable = ['user_id', 'rpi_employee_id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEffective(): bool
    {
        return $this->is_active && $this->user->canTrackRfidFiles()
            && $this->rfid_enrollment_status === self::ENROLLED
            && $this->face_enrollment_status === self::ENROLLED;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rfid_enrolled_at' => 'datetime',
            'face_enrolled_at' => 'datetime',
            'authorization_version' => 'integer',
        ];
    }
}
