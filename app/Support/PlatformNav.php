<?php

namespace App\Support;

class PlatformNav
{
    public static function items(): array
    {
        $items = [
            [
                'key' => 'landing',
                'name' => 'Landing',
                'href' => route('home'),
                'current' => request()->routeIs('home'),
            ],
            [
                'key' => 'overview',
                'name' => 'Overview',
                'href' => route('platform.dashboard'),
                'current' => request()->routeIs('platform.dashboard'),
            ],
            [
                'key' => 'schools',
                'name' => 'Schools',
                'href' => route('platform.schools.index'),
                'current' => request()->routeIs('platform.schools.*'),
            ],
            [
                'key' => 'registrations',
                'name' => 'Registrations',
                'href' => route('platform.registrations.index'),
                'current' => request()->routeIs('platform.registrations.*'),
            ],
            [
                'key' => 'billing',
                'name' => 'Billing',
                'href' => route('platform.billing.index'),
                'current' => request()->routeIs('platform.billing.*'),
            ],
            [
                'key' => 'payments',
                'name' => 'Payments',
                'href' => route('platform.payments.index'),
                'current' => request()->routeIs('platform.payments.*'),
            ],
            [
                'key' => 'integrations',
                'name' => 'Integrations',
                'href' => route('platform.integrations.index'),
                'current' => request()->routeIs('platform.integrations.*'),
            ],
            [
                'key' => 'pricing',
                'name' => 'Pricing',
                'href' => route('platform.pricing.index'),
                'current' => request()->routeIs('platform.pricing.*'),
            ],
            [
                'key' => 'marketing',
                'name' => 'Marketing',
                'href' => route('platform.marketing.edit'),
                'current' => request()->routeIs('platform.marketing.*'),
            ],
            [
                'key' => 'monitoring',
                'name' => 'Monitoring',
                'href' => route('platform.monitoring.index'),
                'current' => request()->routeIs('platform.monitoring.*', 'platform.alerts.*'),
            ],
            [
                'key' => 'ops',
                'name' => 'Ops',
                'href' => route('platform.ops.index'),
                'current' => request()->routeIs('platform.ops.*'),
            ],
            [
                'key' => 'backups',
                'name' => 'Backups',
                'href' => route('platform.backups.index'),
                'current' => request()->routeIs('platform.backups.*'),
            ],
            [
                'key' => 'scale',
                'name' => 'Scale',
                'href' => route('platform.scale.index'),
                'current' => request()->routeIs('platform.scale.*'),
            ],
            [
                'key' => 'capacity',
                'name' => 'Capacity',
                'href' => route('platform.capacity.index'),
                'current' => request()->routeIs('platform.capacity.*'),
            ],
        ];

        // Credentials are for the few who may change them; everyone else would only meet a "forbidden" page.
        $canConfigure = (bool) auth()->user()?->can('platform.integrations.configure');

        return array_values(array_filter($items, fn (array $item) => $item['key'] !== 'integrations' || $canConfigure));
    }
}
