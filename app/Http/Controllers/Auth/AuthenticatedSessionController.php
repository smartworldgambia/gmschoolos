<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\School;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request, TenantContext $tenant): Response
    {
        $school = $tenant->school();

        // Path-based school sites (/sites/{slug}) link here with ?school={slug} so the login
        // page can carry that school's brand. Only published, active schools are honoured.
        if (! $school && is_string($request->query('school')) && $request->query('school') !== '') {
            $candidate = School::query()
                ->where('slug', $request->query('school'))
                ->where('status', 'active')
                ->where('website_published', true)
                ->first();

            $school = $candidate?->isActive() ? $candidate : null;
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'school' => $school ? [
                'name' => $school->name,
                'home_url' => $tenant->has() ? url('/') : route('school.site', ['school_slug' => $school->slug]),
                'logo_path' => $school->logo_path,
                'logo_url' => $school->logo_path
                    ? Storage::disk('public')->url($school->logo_path)
                    : null,
                'primary_color' => $school->primary_color ?: '#082B55',
                'accent_color' => $school->accent_color ?: '#1683F8',
            ] : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->forget('auth.two_factor_passed');

        $user = $request->user();
        $user?->forceFill(['last_login_at' => now()])->save();
        if ($user?->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.challenge');
        }

        $request->session()->put('auth.two_factor_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
