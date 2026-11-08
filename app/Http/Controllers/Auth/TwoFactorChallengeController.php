<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $user?->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if ($request->session()->get('auth.two_factor_passed')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        abort_unless($user?->hasTwoFactorEnabled(), 403);

        $code = $validated['code'];
        $ok = Totp::verify(decrypt($user->two_factor_secret), $code)
            || $user->consumeRecoveryCode($code);

        if (! $ok) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $request->session()->put('auth.two_factor_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
