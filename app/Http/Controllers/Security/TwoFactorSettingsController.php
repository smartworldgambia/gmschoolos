<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Concerns\SharesProfileShell;
use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorSettingsController extends Controller
{
    use SharesProfileShell;

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('auth.two_factor_setup_secret');

        return Inertia::render('Profile/TwoFactor', [
            ...$this->profileShellProps(),
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmedAt' => $user->two_factor_confirmed_at?->toDateTimeString(),
            'setup' => $pendingSecret ? [
                'secret' => $pendingSecret,
                'otpauth_url' => Totp::otpAuthUrl($pendingSecret, $user->email, config('app.name', 'SchoolOS')),
            ] : null,
            'status' => session('status'),
            'recoveryCodes' => session('two_factor_recovery_codes'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $secret = Totp::generateSecret();
        $request->session()->put('auth.two_factor_setup_secret', $secret);

        return redirect()->route('two-factor.settings');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $secret = $request->session()->get('auth.two_factor_setup_secret');
        if (! $secret || ! Totp::verify($secret, $validated['code'])) {
            return back()->withErrors(['code' => 'Invalid code — scan the secret and try again.']);
        }

        $codes = Collection::times(8, fn () => Str::upper(Str::random(4).'-'.Str::random(4)))->all();

        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($codes)),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('auth.two_factor_setup_secret');
        $request->session()->put('auth.two_factor_passed', true);

        return redirect()
            ->route('two-factor.settings')
            ->with('status', 'two-factor-enabled')
            ->with('two_factor_recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('auth.two_factor_passed');

        return redirect()->route('two-factor.settings')->with('status', 'two-factor-disabled');
    }
}
