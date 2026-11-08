<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fail-closed tenant scope.
 *
 * - Tenant bound            -> rows of that school only.
 * - Explicit platform mode  -> unscoped (platform operations, schedulers, provisioning).
 * - Neither                 -> NO rows. A missing tenant context can never widen access.
 */
class SchoolScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);
        $tenantId = $tenant->id();

        if ($tenantId) {
            $builder->where($model->getTable().'.school_id', $tenantId);

            return;
        }

        if ($tenant->isPlatformMode()) {
            return;
        }

        $builder->whereRaw('1 = 0');
    }
}
