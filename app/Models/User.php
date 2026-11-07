<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Exceptions\MailDeliveryUnavailableException;
use App\MailDelivery;
use App\StaffPermissions;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property list<string>|null $staff_permissions
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

    /**
     * Email the reset link only through a mailer that delivers it. With the `log` mailer the link, and the token in
     * it, would only be written to the application log, so nothing is sent and the caller reports the failure.
     *
     * @throws MailDeliveryUnavailableException
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        if (! MailDelivery::configured()) {
            throw new MailDeliveryUnavailableException;
        }

        $this->notify(new ResetPassword($token));
    }

    public function canLoginAsPersonnel(): bool
    {
        return $this->is_active && $this->resident_id === null && in_array($this->role, ['admin', 'captain', 'staff'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'admin' && $this->is_active && $this->is_super_admin && $this->resident_id === null;
    }

    public function isBarangayCaptain(): bool
    {
        return $this->role === 'captain' && $this->is_active && $this->resident_id === null;
    }

    /**
     * Permissions that reach confidential cases, change resident identity or verification details, or issue and
     * revoke official certificates. An account holding any of them must use two-factor authentication.
     */
    public const PRIVILEGED_PERMISSIONS = [
        'vawc.view', 'vawc.submit', 'vawc.update', 'vawc.archive',
        'records.create', 'records.update', 'records.archive', 'records.activate', 'records.review',
        'documents.issue', 'documents.revoke',
    ];

    /**
     * Whether this personnel account must complete two-factor authentication before using the workspace: the
     * Super Admin, the Barangay Captain (who also manage users and Smart Cabinet access) and staff whose current
     * assignments include a privileged permission. Read from the current assignments on every request.
     */
    public function requiresTwoFactorAuthentication(): bool
    {
        return $this->canLoginAsPersonnel()
            && ($this->hasAdministrativeAccess() || $this->role === 'admin' || $this->hasAnyPermission(self::PRIVILEGED_PERMISSIONS));
    }

    /** Two-factor authentication is set up only once the authenticator code was confirmed; a secret alone is not. */
    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function hasAdministrativeAccess(): bool
    {
        return $this->isSuperAdmin() || $this->isBarangayCaptain();
    }

    /** @param list<string> $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        if (str_starts_with($permission, 'vawc.') && self::vawcNeedsExplicitAssignment()) {
            return $this->canLoginAsPersonnel()
                && ($this->role === 'staff' || $this->hasAdministrativeAccess())
                && in_array($permission, $this->staff_permissions ?? [], true);
        }

        return $this->hasAdministrativeAccess() || ($this->canLoginAsPersonnel()
            && $this->role === 'staff' && in_array($permission, $this->staff_permissions ?? [], true));
    }

    /** When on, VAWC/BPO access comes only from an account's own assignments, for administrators too. */
    public static function vawcNeedsExplicitAssignment(): bool
    {
        return (bool) config('access.vawc_explicit_assignment');
    }

    /**
     * Every permission key this account holds now, for the interface (menus and buttons).
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        return array_values(array_filter(StaffPermissions::keys(), fn (string $permission): bool => $this->hasPermission($permission)));
    }

    public function canTrackRfidFiles(): bool
    {
        return $this->hasPermission('rfid.view');
    }

    public function isResidentAccount(): bool
    {
        return $this->role === 'resident';
    }

    /**
     * How this resident portal account was activated, read from stored traces:
     *  - `email_self_service`: through the removed name / birth date / any-email path. That path left the
     *    record's `portal_registration_sent_at` set; staff-issued codes clear it, and neither path can issue a
     *    code once an account exists, so the value is reliable for these accounts. Identity was never
     *    checked by staff.
     *  - `staff_code`: registered through the portal with a staff-issued code (audit `portal.account.registered`).
     *  - `unknown`: created outside the portal flow (for example by a seeder or console), so no trace exists.
     */
    public function portalActivationPath(): string
    {
        if ($this->resident?->portal_registration_sent_at !== null) {
            return 'email_self_service';
        }

        return DB::table('administrative_audits')->where('user_id', $this->id)->where('action', 'portal.account.registered')->exists()
            ? 'staff_code' : 'unknown';
    }

    public function canUseResidentPortal(): bool
    {
        return $this->isResidentAccount() && $this->is_active
            && $this->resident !== null && $this->resident->status === 'active';
    }

    /** @var array<string, mixed> */
    protected $attributes = ['is_active' => true];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

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
            'staff_permissions' => 'array',
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
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
