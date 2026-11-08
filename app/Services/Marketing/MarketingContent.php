<?php

namespace App\Services\Marketing;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class MarketingContent
{
    public const TESTIMONIAL_KEY = 'marketing.testimonial';

    public function testimonial(?bool $forPublic = true): ?array
    {
        $cached = Cache::remember('schoolos:marketing.testimonial', 60, function () {
            $fromDb = PlatformSetting::getValue(self::TESTIMONIAL_KEY);
            if (is_array($fromDb) && filled($fromDb['quote'] ?? null)) {
                return $fromDb;
            }

            $fromConfig = config('schoolos.marketing.testimonial');

            return is_array($fromConfig) ? $fromConfig : null;
        });

        if (! is_array($cached) || blank($cached['quote'] ?? null)) {
            return null;
        }

        if ($forPublic && array_key_exists('published', $cached) && ! $cached['published']) {
            return null;
        }

        return [
            'quote' => (string) $cached['quote'],
            'name' => (string) ($cached['name'] ?? ''),
            'role' => (string) ($cached['role'] ?? ''),
            'school' => (string) ($cached['school'] ?? ''),
            'photo_url' => $cached['photo_url'] ?? null,
            'rating' => max(1, min(5, (int) ($cached['rating'] ?? 5))),
            'published' => (bool) ($cached['published'] ?? true),
        ];
    }

    public function saveTestimonial(array $data): array
    {
        $payload = [
            'quote' => trim((string) ($data['quote'] ?? '')),
            'name' => trim((string) ($data['name'] ?? '')),
            'role' => trim((string) ($data['role'] ?? '')),
            'school' => trim((string) ($data['school'] ?? '')),
            'photo_url' => filled($data['photo_url'] ?? null) ? trim((string) $data['photo_url']) : null,
            'rating' => max(1, min(5, (int) ($data['rating'] ?? 5))),
            'published' => (bool) ($data['published'] ?? true),
        ];

        PlatformSetting::putValue(self::TESTIMONIAL_KEY, $payload);
        Cache::forget('schoolos:marketing.testimonial');

        return $payload;
    }
}
