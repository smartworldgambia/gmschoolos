<?php

namespace App\Http\Controllers\Concerns;

use App\Support\PlatformNav;

trait SharesPlatformShell
{
    protected function platformShellProps(): array
    {
        $user = auth()->user();

        return [
            'nav' => PlatformNav::items(),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ] : null,
            'school' => [
                'id' => null,
                'name' => 'Platform Console',
                'slug' => 'platform',
            ],
            'academicYear' => null,
        ];
    }
}
