<?php

namespace App\Services\Scale;

use App\Models\Level;
use App\Models\NewsPost;
use App\Models\School;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CacheTuner
{
    public function publicSitePayload(School $school): array
    {
        $ttl = (int) config('schoolos.cache_ttl.public_site', 300);
        $key = "schoolos:public_site:{$school->id}";

        return Cache::remember($key, $ttl, function () use ($school) {
            $levels = Level::query()
                ->where('school_id', $school->id)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code']);

            $news = NewsPost::query()
                ->where('school_id', $school->id)
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->limit(6)
                ->get(['id', 'title', 'slug', 'excerpt', 'image_url', 'published_at']);

            $landing = is_array($school->settings['landing'] ?? null)
                ? $school->settings['landing']
                : [];

            $logoUrl = $school->logo_path
                ? Storage::disk('public')->url($school->logo_path)
                : null;

            return [
                'school' => [
                    'id' => $school->id,
                    'name' => $school->name,
                    'slug' => $school->slug,
                    'category' => $school->category,
                    'tagline' => $school->tagline,
                    'about' => $school->about,
                    'phone' => $school->phone,
                    'email' => $school->email,
                    'address' => $school->address,
                    'region' => $school->region,
                    'country' => $school->country,
                    'primary_color' => $school->primary_color,
                    'accent_color' => $school->accent_color,
                    'logo_path' => $school->logo_path,
                    'logo_url' => $logoUrl,
                ],
                'landing' => [
                    'template' => $landing['template'] ?? $school->category,
                    'category_label' => $landing['category_label'] ?? null,
                    'hero_eyebrow' => $landing['hero_eyebrow'] ?? null,
                    'hero_headline' => $landing['hero_headline'] ?? null,
                    'hero_body' => $landing['hero_body'] ?? null,
                    'hero_image' => $landing['hero_image'] ?? '/images/marketing/hero-students.jpg',
                    'hero_slides' => is_array($landing['hero_slides'] ?? null) ? $landing['hero_slides'] : [],
                    'cta_primary' => $landing['cta_primary'] ?? 'Apply for Admission',
                    'cta_secondary' => $landing['cta_secondary'] ?? 'Learn More',
                    'levels_heading' => $landing['levels_heading'] ?? 'Our Academic Levels',
                    'levels_intro' => $landing['levels_intro'] ?? null,
                    'about_headline' => $landing['about_headline'] ?? null,
                    'about_image' => $landing['about_image'] ?? '/images/marketing/hero-classroom.jpg',
                    'admissions_title' => $landing['admissions_title'] ?? null,
                    'admissions_body' => $landing['admissions_body'] ?? null,
                    'admissions_steps' => is_array($landing['admissions_steps'] ?? null) ? $landing['admissions_steps'] : [],
                    'admissions_image' => $landing['admissions_image'] ?? null,
                    'admissions_requirements' => $landing['admissions_requirements'] ?? null,
                    'contact_blurb' => $landing['contact_blurb'] ?? null,
                    'contact_hours' => $landing['contact_hours'] ?? null,
                    'map_enabled' => (bool) ($landing['map_enabled'] ?? true),
                    'map_lat' => $landing['map_lat'] ?? null,
                    'map_lng' => $landing['map_lng'] ?? null,
                    'features' => is_array($landing['features'] ?? null) ? $landing['features'] : [],
                    'level_cards' => is_array($landing['level_cards'] ?? null) ? $landing['level_cards'] : [],
                ],
                'levels' => $levels->map(fn (Level $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'code' => $l->code,
                ])->all(),
                'news' => $news->map(fn (NewsPost $n) => [
                    'id' => $n->id,
                    'title' => $n->title,
                    'slug' => $n->slug,
                    'excerpt' => $n->excerpt,
                    'image_url' => $n->image_url,
                    'published_at' => $n->published_at?->toDateTimeString(),
                ])->all(),
            ];
        });
    }

    public function forgetPublicSite(int $schoolId): void
    {
        Cache::forget("schoolos:public_site:{$schoolId}");
    }

    /**
     * Public annual price list (one price per school type). Cached briefly; a price change flushes it.
     *
     * @return list<array{type: string, amount: int, currency: string, tax_rate: mixed, label: string}>
     */
    public function landingPrices(): array
    {
        $ttl = (int) config('schoolos.cache_ttl.plans_landing', 600);

        return Cache::remember('schoolos:landing_prices', $ttl, function () {
            $pricing = app(\App\Services\Billing\PricingService::class);
            $rows = [];

            foreach ($pricing->currentForAll() as $type => $price) {
                if (! $price) {
                    continue;
                }

                $rows[] = [
                    'type' => $type,
                    'amount' => $price->totalAmount(),
                    'currency' => $price->currency,
                    'tax_rate' => $price->tax_rate,
                    'label' => Money::formatCurrency($price->totalAmount(), $price->currency),
                ];
            }

            return $rows;
        });
    }

    public function forgetLandingPrices(): void
    {
        Cache::forget('schoolos:landing_prices');
    }
    public function adminDashboardStats(int $schoolId, callable $builder): array
    {
        $ttl = (int) config('schoolos.cache_ttl.admin_dashboard', 60);

        return Cache::remember("schoolos:admin_dash:{$schoolId}", $ttl, $builder);
    }

    public function forgetAdminDashboard(int $schoolId): void
    {
        Cache::forget("schoolos:admin_dash:{$schoolId}");
    }

    public function warm(): array
    {
        $warmed = ['prices' => false, 'public_sites' => 0];

        $this->landingPrices();
        $warmed['prices'] = true;

        School::query()
            ->where('status', 'active')
            ->whereNull('suspended_at')
            ->where('website_published', true)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (School $school) use (&$warmed) {
                $this->publicSitePayload($school);
                $warmed['public_sites']++;
            });

        return $warmed;
    }
}
