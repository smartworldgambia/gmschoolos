<?php

namespace Database\Seeders\Concerns;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

trait DemoMailDomain
{
    protected function demoMailHost(): string
    {
        $host = trim((string) config('schoolos.base_domain', 'gambiaschoolos.com'));

        return in_array($host, ['', 'localhost', '127.0.0.1'], true)
            ? 'gambiaschoolos.com'
            : $host;
    }

    protected function demoPlatformEmail(string $localPart): string
    {
        return $localPart.'@'.$this->demoMailHost();
    }

    protected function demoSchoolMailHost(string $slug): string
    {
        return $slug.'.'.$this->demoMailHost();
    }

    protected function relocateLoginEmail(string $from, string $to): void
    {
        if ($from === '' || $from === $to) {
            return;
        }

        if (! User::query()->where('email', $to)->exists()) {
            User::query()->where('email', $from)->update(['email' => $to]);
        }

        if (Schema::hasTable('staff') && ! Staff::query()->where('email', $to)->exists()) {
            Staff::query()->where('email', $from)->update(['email' => $to]);
        }
    }
}
