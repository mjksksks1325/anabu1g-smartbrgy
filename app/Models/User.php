<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $role
 * @property int|null $resident_id
 * @property bool $is_active
 * @property bool $is_super_admin
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @return BelongsTo<Resident, $this> */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /** @return HasOne<EmployeeCabinetAccess, $this> */
    public function cabinetAccess(): HasOne
    {
        return $this->hasOne(EmployeeCabinetAccess::class);
    }

    /** @return HasMany<FileMovementEvent, $this> */
    public function fileMovements(): HasMany
    {
        return $this->hasMany(FileMovementEvent::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin' && $this->is_active && $this->is_super_admin && $this->resident_id === null;
    }

    public function canTrackRfidFiles(): bool
    {
        return $this->is_active && in_array($this->role, ['admin', 'staff', 'viewer'], true) && $this->resident_id === null;
    }

    public function isResidentAccount(): bool
    {
        return $this->role === 'resident';
    }

    public function canUseResidentPortal(): bool
    {
        return $this->isResidentAccount() && $this->is_active
            && $this->resident !== null && $this->resident->status === 'active';
    }

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_super_admin' => 'boolean',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
