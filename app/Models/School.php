<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'country', 'region', 'district', 'registration_number', 'address', 'phone', 'email',
        'billing_email', 'logo_path', 'primary_color', 'accent_color', 'tagline', 'about',
        'status', 'isolation_tier', 'db_connection', 'partition_key', 'isolation_reviewed_at',
        'suspended_at', 'website_published', 'settings', 'terms_accepted_at', 'terms_version', 'setup_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'website_published' => 'boolean',
            'settings' => 'array',
            'suspended_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'setup_completed_at' => 'datetime',
            'isolation_reviewed_at' => 'datetime',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function primaryDomain(): HasOne
    {
        return $this->hasOne(Domain::class)->where('is_primary', true);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(SchoolSubscription::class)->latestOfMany();
    }

    public function quota(): HasOne
    {
        return $this->hasOne(SchoolQuota::class);
    }

    public function usageSnapshots(): HasMany
    {
        return $this->hasMany(UsageSnapshot::class);
    }

    public function dedicatedDatabase(): HasOne
    {
        return $this->hasOne(TenantDatabaseConnection::class);
    }

    public function usesDedicatedDatabase(): bool
    {
        return $this->isolation_tier === 'dedicated_db' && filled($this->db_connection);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function newsPosts(): HasMany
    {
        return $this->hasMany(NewsPost::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->suspended_at === null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
