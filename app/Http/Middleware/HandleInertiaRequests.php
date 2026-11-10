<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'roles' => $request->user()->getRoleNames(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'count' => fn () => $request->session()->get('count'),
                'failedCount' => fn () => $request->session()->get('failedCount'),
                'school_url' => fn () => $request->session()->get('school_url'),
                'school_host' => fn () => $request->session()->get('school_host'),
                'integration' => fn () => $request->session()->get('integration'),
            ],
            'schoolos' => [
                'currency' => config('schoolos.currency'),
                'locales' => config('schoolos.supported_locales'),
            ],
            'notifications' => [
                'unread_count' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
                'recent' => fn () => $request->user()
                    ? $request->user()->notifications()->latest()->limit(5)->get()->map(fn ($n) => [
                        'id' => $n->id,
                        'title' => $n->data['title'] ?? 'Notification',
                        'body' => $n->data['body'] ?? '',
                        'action_url' => $n->data['action_url'] ?? null,
                        'read_at' => $n->read_at?->toDateTimeString(),
                        'created_at' => $n->created_at?->diffForHumans(),
                    ])
                    : [],
            ],
        ];
    }
}
