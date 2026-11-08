<?php

namespace App\Http\Controllers\Concerns;

trait SharesProfileShell
{
    use SharesSchoolShell, SharesPlatformShell;

    protected function profileShellProps(): array
    {
        $user = auth()->user();

        if (! $user?->school_id) {
            return $this->platformShellProps();
        }

        return $this->shellProps();
    }
}
