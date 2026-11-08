<?php

namespace Database\Seeders;

use App\Models\NewsPost;
use App\Models\School;
use App\Services\Scale\CacheTuner;
use App\Services\School\SchoolLandingProvisioner;
use Illuminate\Database\Seeder;

/**
 * Restores the landing-page pictures.
 *
 * Platform landing: the hero slider uses the bundled stock photos in public/images/marketing;
 * this seeder verifies that they are present.
 *
 * School landing: for every school it fills in ONLY missing/broken pictures (hero image + slides,
 * about image, level cards, news posts) from the school's category template. Pictures a school has
 * chosen itself are never overwritten, and no other landing copy is touched. Safe to re-run.
 */
class LandingMediaSeeder extends Seeder
{
    /** @var list<string> */
    protected const STOCK_IMAGES = [
        '/images/marketing/hero-students.jpg',
        '/images/marketing/hero-classroom.jpg',
        '/images/marketing/hero-primary.jpg',
    ];

    public function run(SchoolLandingProvisioner $provisioner, CacheTuner $cache): void
    {
        foreach (self::STOCK_IMAGES as $image) {
            if (! $this->exists($image)) {
                $this->command?->warn("Platform stock picture missing: public{$image}");
            }
        }

        $restored = 0;

        School::query()->orderBy('id')->each(function (School $school) use ($provisioner, $cache, &$restored) {
            $template = $provisioner->templateFor($school->category ?? 'combined');
            $settings = $school->settings ?? [];
            $landing = $settings['landing'] ?? [];
            $changed = false;

            // Hero image + slides.
            if ($this->missing($landing['hero_image'] ?? null)) {
                $landing['hero_image'] = $template['hero_image'];
                $changed = true;
            }

            $slides = collect($landing['hero_slides'] ?? [])
                ->filter(fn ($slide) => is_array($slide) && ! $this->missing($slide['image'] ?? $slide['src'] ?? null))
                ->values();
            if ($slides->isEmpty()) {
                $landing['hero_slides'] = $template['hero_slides'];
                $changed = true;
            }

            // About image.
            if ($this->missing($landing['about_image'] ?? null)) {
                $landing['about_image'] = $template['about_image'];
                $changed = true;
            }

            // Level cards: fill blank/broken pictures only.
            $cards = $landing['level_cards'] ?? [];
            foreach ($cards as $index => $card) {
                if (! is_array($card) || ! $this->missing($card['image'] ?? null)) {
                    continue;
                }
                $cards[$index]['image'] = self::STOCK_IMAGES[$index % count(self::STOCK_IMAGES)];
                $changed = true;
            }
            $landing['level_cards'] = $cards;

            if ($changed) {
                $settings['landing'] = $landing;
                $school->forceFill(['settings' => $settings])->save();
                $restored++;
            }

            // News posts without a usable picture.
            NewsPost::query()
                ->withoutGlobalScopes()
                ->where('school_id', $school->id)
                ->get()
                ->each(function (NewsPost $post, int $index) use (&$changed) {
                    if ($this->missing($post->image_url)) {
                        $post->forceFill(['image_url' => self::STOCK_IMAGES[$index % count(self::STOCK_IMAGES)]])->save();
                        $changed = true;
                    }
                });

            if ($changed) {
                $cache->forgetPublicSite($school->id);
            }
        });

        $this->command?->info("Landing pictures verified; {$restored} school landing configuration(s) restored.");
    }

    /** A picture is "missing" when blank, or a bundled /images/... file that is not on disk. */
    protected function missing(mixed $url): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return true;
        }

        if (str_starts_with($url, '/images/')) {
            return ! $this->exists($url);
        }

        return false;
    }

    protected function exists(string $publicPath): bool
    {
        return is_file(public_path(ltrim($publicPath, '/')));
    }
}
