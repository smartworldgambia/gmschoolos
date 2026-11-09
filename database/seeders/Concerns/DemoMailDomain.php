<?php

namespace Database\Seeders\Concerns;

use App\Services\Demo\DemoLoginProvisioner;
use App\Support\DemoLoginCatalog;

trait DemoMailDomain
{
    protected function demoMailHost(): string
    {
        return DemoLoginCatalog::mailHost();
    }

    protected function demoPlatformEmail(string $localPart): string
    {
        return DemoLoginCatalog::platformEmail($localPart);
    }

    protected function demoSchoolMailHost(string $slug): string
    {
        return DemoLoginCatalog::schoolMailHost($slug);
    }

    protected function relocateLoginEmail(string $from, string $to): void
    {
        app(DemoLoginProvisioner::class)->relocateLoginEmail($from, $to);
    }
}
