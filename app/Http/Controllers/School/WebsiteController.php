<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Concerns\AuthorizesSchoolAdmin;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use App\Models\School;
use App\Services\Scale\CacheTuner;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    use AuthorizesSchoolAdmin;
    use SharesSchoolShell;

    public function edit(TenantContext $tenant): Response
    {
        abort_unless(auth()->user()?->can('website.manage'), 403);

        $school = $this->currentSchool($tenant);
        $landing = is_array($school->settings['landing'] ?? null)
            ? $school->settings['landing']
            : [];

        $news = NewsPost::query()
            ->where('school_id', $school->id)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'excerpt', 'body', 'image_url', 'published_at']);

        $heroSlides = array_values($landing['hero_slides'] ?? []);
        if ($heroSlides === [] && filled($landing['hero_image'] ?? null)) {
            $heroSlides = [['image' => $landing['hero_image'], 'alt' => '']];
        }

        return Inertia::render('School/Website/Edit', array_merge($this->shellProps(), [
            'website' => [
                'name' => $school->name,
                'slug' => $school->slug,
                'tagline' => $school->tagline,
                'about' => $school->about,
                'phone' => $school->phone,
                'email' => $school->email,
                'address' => $school->address,
                'region' => $school->region,
                'primary_color' => $school->primary_color ?: '#1A2B56',
                'accent_color' => $school->accent_color ?: '#1683F8',
                'website_published' => (bool) $school->website_published,
                'hero_headline' => $landing['hero_headline'] ?? '',
                'hero_body' => $landing['hero_body'] ?? '',
                'hero_image' => $landing['hero_image'] ?? '/images/marketing/hero-students.jpg',
                'hero_slides' => $heroSlides,
                'cta_primary' => $landing['cta_primary'] ?? 'Apply for Admission',
                'cta_secondary' => $landing['cta_secondary'] ?? 'Learn More',
                'about_headline' => $landing['about_headline'] ?? '',
                'about_image' => $landing['about_image'] ?? '/images/marketing/hero-classroom.jpg',
                'levels_heading' => $landing['levels_heading'] ?? 'Our Academic Levels',
                'levels_intro' => $landing['levels_intro'] ?? '',
                'level_cards' => array_values($landing['level_cards'] ?? []),
                'features' => array_values($landing['features'] ?? []),
                'admissions_title' => $landing['admissions_title'] ?? '',
                'admissions_body' => $landing['admissions_body'] ?? '',
                'admissions_steps' => array_values($landing['admissions_steps'] ?? []),
                'admissions_image' => $landing['admissions_image'] ?? '',
                'admissions_requirements' => $landing['admissions_requirements'] ?? '',
                'contact_blurb' => $landing['contact_blurb'] ?? '',
                'contact_hours' => $landing['contact_hours'] ?? '',
                'map_enabled' => (bool) ($landing['map_enabled'] ?? true),
                'map_lat' => $landing['map_lat'] ?? '',
                'map_lng' => $landing['map_lng'] ?? '',
                'country' => $school->country,
            ],
            'news' => $news->map(fn (NewsPost $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'body' => $post->body,
                'image_url' => $post->image_url,
                'published_at' => $post->published_at?->format('Y-m-d'),
                'is_published' => $post->published_at !== null,
            ])->all(),
            'updateUrl' => route('website.update'),
            'newsStoreUrl' => route('website.news.store'),
            'publicUrl' => route('school.site', ['school_slug' => $school->slug]),
        ]));
    }

    public function update(Request $request, TenantContext $tenant, CacheTuner $cache): RedirectResponse
    {
        abort_unless(auth()->user()?->can('website.manage'), 403);

        $school = $this->currentSchool($tenant);

        $validated = $request->validate([
            'tagline' => ['nullable', 'string', 'max:255'],
            'about' => ['nullable', 'string', 'max:10000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:120'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'accent_color' => ['nullable', 'string', 'max:20'],
            'website_published' => ['sometimes', 'boolean'],
            'hero_headline' => ['nullable', 'string', 'max:255'],
            'hero_body' => ['nullable', 'string', 'max:2000'],
            'hero_image' => ['nullable', 'string', 'max:500'],
            'hero_slides' => ['nullable', 'array', 'max:8'],
            'hero_slides.*.image' => ['nullable', 'string', 'max:500'],
            'hero_slides.*.alt' => ['nullable', 'string', 'max:180'],
            'cta_primary' => ['nullable', 'string', 'max:80'],
            'cta_secondary' => ['nullable', 'string', 'max:80'],
            'about_headline' => ['nullable', 'string', 'max:255'],
            'about_image' => ['nullable', 'string', 'max:500'],
            'levels_heading' => ['nullable', 'string', 'max:120'],
            'levels_intro' => ['nullable', 'string', 'max:1000'],
            'level_cards' => ['nullable', 'array', 'max:12'],
            'level_cards.*.name' => ['nullable', 'string', 'max:120'],
            'level_cards.*.code' => ['nullable', 'string', 'max:60'],
            'level_cards.*.description' => ['nullable', 'string', 'max:500'],
            'level_cards.*.image' => ['nullable', 'string', 'max:500'],
            'features' => ['nullable', 'array', 'max:8'],
            'features.*.key' => ['nullable', 'string', 'max:60'],
            'features.*.icon' => ['nullable', 'string', 'max:60'],
            'features.*.title' => ['nullable', 'string', 'max:120'],
            'features.*.subtitle' => ['nullable', 'string', 'max:255'],
            'admissions_title' => ['nullable', 'string', 'max:255'],
            'admissions_body' => ['nullable', 'string', 'max:2000'],
            'admissions_steps' => ['nullable', 'array', 'max:6'],
            'admissions_steps.*.title' => ['nullable', 'string', 'max:120'],
            'admissions_steps.*.body' => ['nullable', 'string', 'max:400'],
            'admissions_image' => ['nullable', 'string', 'max:500'],
            'admissions_requirements' => ['nullable', 'string', 'max:1500'],
            'contact_blurb' => ['nullable', 'string', 'max:1000'],
            'contact_hours' => ['nullable', 'string', 'max:500'],
            'map_enabled' => ['sometimes', 'boolean'],
            'map_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:map_lng'],
            'map_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:map_lat'],
        ]);

        $existingLanding = is_array($school->settings['landing'] ?? null)
            ? $school->settings['landing']
            : [];

        $levelCards = collect($validated['level_cards'] ?? [])
            ->filter(fn ($card) => filled($card['name'] ?? null))
            ->map(function (array $card, int $index) {
                $name = trim((string) $card['name']);

                return [
                    'name' => $name,
                    'code' => filled($card['code'] ?? null)
                        ? Str::slug((string) $card['code'], '_')
                        : Str::slug($name, '_'),
                    'description' => trim((string) ($card['description'] ?? '')),
                    'image' => trim((string) ($card['image'] ?? '')) ?: '/images/marketing/hero-students.jpg',
                    'sort' => $index,
                ];
            })
            ->values()
            ->all();

        $features = collect($validated['features'] ?? [])
            ->filter(fn ($feature) => filled($feature['title'] ?? null))
            ->map(function (array $feature, int $index) {
                $title = trim((string) $feature['title']);
                $key = filled($feature['key'] ?? null)
                    ? Str::slug((string) $feature['key'], '_')
                    : Str::slug($title, '_');

                return [
                    'key' => $key ?: "feature_{$index}",
                    'icon' => filled($feature['icon'] ?? null) ? (string) $feature['icon'] : $key,
                    'title' => $title,
                    'subtitle' => trim((string) ($feature['subtitle'] ?? '')),
                ];
            })
            ->values()
            ->all();

        $admissionsSteps = collect($validated['admissions_steps'] ?? [])
            ->filter(fn ($step) => filled($step['title'] ?? null))
            ->map(fn (array $step) => [
                'title' => trim((string) $step['title']),
                'body' => trim((string) ($step['body'] ?? '')),
            ])
            ->values()
            ->all();

        $heroSlides = collect($validated['hero_slides'] ?? [])
            ->filter(fn ($slide) => filled($slide['image'] ?? null))
            ->map(fn (array $slide) => [
                'image' => trim((string) $slide['image']),
                'alt' => trim((string) ($slide['alt'] ?? '')),
            ])
            ->values()
            ->all();

        if ($heroSlides === [] && filled($validated['hero_image'] ?? null)) {
            $heroSlides = [['image' => trim((string) $validated['hero_image']), 'alt' => '']];
        }

        $landing = array_merge($existingLanding, [
            'hero_headline' => $validated['hero_headline'] ?? ($existingLanding['hero_headline'] ?? null),
            'hero_body' => $validated['hero_body'] ?? ($existingLanding['hero_body'] ?? null),
            'hero_image' => $heroSlides[0]['image']
                ?? ($validated['hero_image'] ?? ($existingLanding['hero_image'] ?? null)),
            'hero_slides' => $heroSlides,
            'cta_primary' => $validated['cta_primary'] ?? ($existingLanding['cta_primary'] ?? null),
            'cta_secondary' => $validated['cta_secondary'] ?? ($existingLanding['cta_secondary'] ?? null),
            'about_headline' => $validated['about_headline'] ?? ($existingLanding['about_headline'] ?? null),
            'about_image' => $validated['about_image'] ?? ($existingLanding['about_image'] ?? null),
            'levels_heading' => $validated['levels_heading'] ?? ($existingLanding['levels_heading'] ?? null),
            'levels_intro' => $validated['levels_intro'] ?? ($existingLanding['levels_intro'] ?? null),
            'level_cards' => $levelCards,
            'features' => $features,
            'admissions_title' => $validated['admissions_title'] ?? ($existingLanding['admissions_title'] ?? null),
            'admissions_body' => $validated['admissions_body'] ?? ($existingLanding['admissions_body'] ?? null),
            'admissions_steps' => $admissionsSteps,
            // Clearable: null removes the custom photo / checklist and falls back to defaults.
            'admissions_image' => filled($validated['admissions_image'] ?? null) ? trim((string) $validated['admissions_image']) : null,
            'admissions_requirements' => filled($validated['admissions_requirements'] ?? null) ? trim((string) $validated['admissions_requirements']) : null,
            'contact_blurb' => $validated['contact_blurb'] ?? ($existingLanding['contact_blurb'] ?? null),
            // Map + hours must be clearable, so they are written as-is (null clears).
            'contact_hours' => filled($validated['contact_hours'] ?? null) ? trim((string) $validated['contact_hours']) : null,
            'map_enabled' => $request->boolean('map_enabled', (bool) ($existingLanding['map_enabled'] ?? true)),
            'map_lat' => isset($validated['map_lat']) ? round((float) $validated['map_lat'], 6) : null,
            'map_lng' => isset($validated['map_lng']) ? round((float) $validated['map_lng'], 6) : null,
            'updated_at' => now()->toIso8601String(),
        ]);

        $school->fill([
            'tagline' => $validated['tagline'] ?? $school->tagline,
            'about' => $validated['about'] ?? $school->about,
            'phone' => $validated['phone'] ?? $school->phone,
            'email' => $validated['email'] ?? $school->email,
            'address' => $validated['address'] ?? $school->address,
            'region' => $validated['region'] ?? $school->region,
            'primary_color' => $validated['primary_color'] ?? $school->primary_color,
            'accent_color' => $validated['accent_color'] ?? $school->accent_color,
            'website_published' => $request->boolean('website_published', (bool) $school->website_published),
            'settings' => array_merge($school->settings ?? [], [
                'landing' => $landing,
            ]),
        ])->save();

        $cache->forgetPublicSite($school->id);

        return back()->with('success', 'Website content saved.');
    }

    public function storeNews(Request $request, TenantContext $tenant, CacheTuner $cache): RedirectResponse
    {
        abort_unless(auth()->user()?->can('website.manage'), 403);

        $school = $this->currentSchool($tenant);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $slugBase = Str::slug($validated['title']);
        $slug = $slugBase !== '' ? $slugBase : 'news-'.Str::random(6);
        $suffix = 1;
        while (
            NewsPost::query()
                ->where('school_id', $school->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $slugBase.'-'.$suffix;
            $suffix++;
        }

        $published = $request->boolean('published', true);

        NewsPost::query()->create([
            'school_id' => $school->id,
            'title' => $validated['title'],
            'slug' => $slug,
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'published_at' => $published
                ? ($validated['published_at'] ?? now())
                : null,
        ]);

        $cache->forgetPublicSite($school->id);

        return back()->with('success', 'News post created.');
    }

    public function updateNews(Request $request, NewsPost $news, TenantContext $tenant, CacheTuner $cache): RedirectResponse
    {
        abort_unless(auth()->user()?->can('website.manage'), 403);

        $school = $this->currentSchool($tenant);
        abort_unless((int) $news->school_id === (int) $school->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:10000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('news_posts', 'slug')
                    ->where(fn ($q) => $q->where('school_id', $school->id))
                    ->ignore($news->id),
            ],
        ]);

        $published = $request->boolean('published', $news->published_at !== null);

        $news->fill([
            'title' => $validated['title'],
            'slug' => filled($validated['slug'] ?? null)
                ? Str::slug((string) $validated['slug'])
                : $news->slug,
            'excerpt' => $validated['excerpt'] ?? null,
            'body' => $validated['body'] ?? null,
            'image_url' => $validated['image_url'] ?? null,
            'published_at' => $published
                ? ($validated['published_at'] ?? $news->published_at ?? now())
                : null,
        ])->save();

        $cache->forgetPublicSite($school->id);

        return back()->with('success', 'News post updated.');
    }

    public function destroyNews(NewsPost $news, TenantContext $tenant, CacheTuner $cache): RedirectResponse
    {
        abort_unless(auth()->user()?->can('website.manage'), 403);

        $school = $this->currentSchool($tenant);
        abort_unless((int) $news->school_id === (int) $school->id, 404);

        $news->delete();
        $cache->forgetPublicSite($school->id);

        return back()->with('success', 'News post deleted.');
    }

    protected function currentSchool(TenantContext $tenant): School
    {
        $user = auth()->user();
        if ($user?->school_id && ! $tenant->has()) {
            $tenant->set($user->school);
        }

        $school = $tenant->school() ?? $user?->school;
        abort_unless($school, 404);

        return $school;
    }
}
