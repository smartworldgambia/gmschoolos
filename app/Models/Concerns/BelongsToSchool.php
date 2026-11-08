<?php

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('school_id')) {
                $tenant = app(TenantContext::class);
                if ($tenant->id()) {
                    $model->setAttribute('school_id', $tenant->id());
                } elseif (! $tenant->isPlatformMode()) {
                    // Fail closed: never persist a tenant-owned record without an owner.
                    throw new \RuntimeException(sprintf('Cannot create %s without a school (no tenant context).', $model::class));
                }
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
