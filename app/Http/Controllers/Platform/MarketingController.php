<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Services\Marketing\MarketingContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingController extends Controller
{
    use SharesPlatformShell;

    public function edit(MarketingContent $marketing): Response
    {
        $testimonial = $marketing->testimonial(forPublic: false) ?? [
            'quote' => '',
            'name' => '',
            'role' => '',
            'school' => '',
            'photo_url' => '',
            'rating' => 5,
            'published' => true,
        ];

        return Inertia::render('Platform/Marketing/Edit', array_merge($this->platformShellProps(), [
            'testimonial' => $testimonial,
            'updateUrl' => route('platform.marketing.update'),
            'landingUrl' => route('home').'#resources',
        ]));
    }

    public function update(Request $request, MarketingContent $marketing): RedirectResponse
    {
        if ($request->input('photo_url') === '') {
            $request->merge(['photo_url' => null]);
        }

        $validated = $request->validate([
            'quote' => ['required', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            'school' => ['nullable', 'string', 'max:160'],
            'photo_url' => ['nullable', 'string', 'max:500', 'url'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'published' => ['sometimes', 'boolean'],
        ]);

        $marketing->saveTestimonial([
            ...$validated,
            'published' => $request->boolean('published', true),
        ]);

        return back()->with('success', 'Landing testimonial saved.');
    }
}
