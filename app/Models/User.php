<?php

namespace App\Models;

use App\Support\Rbac;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'phone',
        'locale',
        'password',
        // two_factor_* columns are intentionally NOT mass assignable (written via forceFill only).
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'access_revoked_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Portal identity. School owners/admins hold every `portal.*` permission, so "can portal.student" alone would
     * wrongly treat an administrator as a learner. A user is only a learner / guardian when they hold no staff portal.
     */
    public function isStaffPortal(): bool
    {
        return $this->can('portal.admin') || $this->can('portal.teacher');
    }

    public function isLearnerPortal(): bool
    {
        return ! $this->isStaffPortal() && $this->can('portal.student');
    }

    public function isGuardianPortal(): bool
    {
        return ! $this->isStaffPortal() && $this->can('portal.parent');
    }

    /** Student or parent (and nothing else): sees only own / own-children's published data. */
    public function isFamilyPortal(): bool
    {
        return $this->isLearnerPortal() || $this->isGuardianPortal();
    }

    /** Where the SMS channel sends this person's texts. */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    /** A suspended or access-revoked account cannot sign in or use an existing session. */
    public function isBlocked(): bool
    {
        return $this->suspended_at !== null || $this->access_revoked_at !== null;
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function guardianProfile(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /** Platform staff: no school and at least one platform-level role (super_admin, platform_admin, finance, ...). */
    public function isPlatformAdmin(): bool
    {
        return $this->school_id === null && $this->hasAnyRole(Rbac::platformRoles());
    }

    public function isPlatformStaff(): bool
    {
        return $this->isPlatformAdmin();
    }

    /** True when this user holds a role that must use two-factor authentication. */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(config('rbac.two_factor_roles', []));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    public function recoveryCodes(): array
    {
        if (! $this->two_factor_recovery_codes) {
            return [];
        }

        try {
            return json_decode(decrypt($this->two_factor_recovery_codes), true) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function consumeRecoveryCode(string $code): bool
    {
        $codes = $this->recoveryCodes();
        $normalized = strtoupper(trim($code));
        $match = collect($codes)->first(fn ($c) => hash_equals(strtoupper($c), $normalized));

        if (! $match) {
            return false;
        }

        $remaining = array_values(array_filter($codes, fn ($c) => strtoupper($c) !== $normalized));
        $this->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode($remaining)),
        ])->save();

        return true;
    }
}
