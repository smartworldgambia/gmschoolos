<?php

namespace App\Http\Controllers\Concerns;

trait AuthorizesSchoolAdmin
{
    protected function authorizeSchoolAdmin(): void
    {
        abort_unless(auth()->user()?->can('portal.admin'), 403);
    }
}
