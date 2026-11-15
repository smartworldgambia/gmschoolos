<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Concerns\SharesProfileShell;
use App\Http\Controllers\Controller;
use App\Services\Sms\PhoneNumber;
use App\Support\Totp;
use App\Support\TwoFactorOtp;
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
        $setupMethod = $request->session()->get('auth.two_factor_setup_method');
        $pendingSecret = $request->session()->get('auth.two_factor_setup_secret');
        $pendingPhone = $request->session()->get('auth.two_factor_setup_phone');

        $setup = null;
        if ($setupMethod === 'totp' && $pendingSecret) {
            $setup = [
                'method' => 'totp',
                'secret' => $pendingSecret,
                'otpauth_url' => Totp::otpAuthUrl($pendingSecret, $user->email, config('app.name', 'SchoolOS')),
                'destination' => null,
            ];
        } elseif (in_array($setupMethod, ['email', 'sms'], true)) {
            $setup = [
                'method' => $setupMethod,
                'secret' => null,
                'otpauth_url' => null,
                'destination' => $setupMethod === 'sms'
                    ? TwoFactorOtp::maskPhone($pendingPhone ?: $user->phone)
                    : TwoFactorOtp::maskEmail((string) $user->email),
            ];
        }

        return Inertia::render('Profile/TwoFactor', [
            ...$this->profileShellProps(),
            'enabled' => $user->hasTwoFactorEnabled(),
            'method' => $user->hasTwoFactorEnabled() ? $user->twoFactorMethod() : null,
            'required' => $this->isMandatory($user),
            'confirmedAt' => $user->two_factor_confirmed_at?->toDateTimeString(),
            'phone' => $user->phone,
            'smsAvailable' => TwoFactorOtp::smsAvailable(),
            'emailMasked' => TwoFactorOtp::maskEmail((string) $user->email),
            'phoneMasked' => TwoFactorOtp::maskPhone($user->phone),
            'sendWait' => TwoFactorOtp::sendWait($user),
            'setup' => $setup,
            'status' => session('status'),
            'recoveryCodes' => session('two_factor_recovery_codes'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.settings');
        }

        $validated = $request->validate([
            'method' => ['required', 'in:totp,email,sms,cancel'],
            'phone' => ['nullable', 'string', 'max:24'],
        ]);

        $method = $validated['method'];
        $request->session()->forget(['auth.two_factor_setup_secret', 'auth.two_factor_setup_phone', 'auth.two_factor_setup_method']);
        TwoFactorOtp::forget($user, TwoFactorOtp::PURPOSE_SETUP);

        if ($method === 'cancel') {
            return redirect()->route('two-factor.settings');
        }

        if ($method === 'totp') {
            $request->session()->put('auth.two_factor_setup_method', 'totp');
            $request->session()->put('auth.two_factor_setup_secret', Totp::generateSecret());

            return redirect()->route('two-factor.settings');
        }

        $phone = null;
        if ($method === 'sms') {
            $phone = PhoneNumber::normalise($validated['phone'] ?? $user->phone);
            if (! $phone) {
                return back()->withErrors(['phone' => 'err.2fa.invalidPhone']);
            }
            if (! TwoFactorOtp::smsAvailable()) {
                return back()->withErrors(['phone' => 'err.2fa.unavailable']);
            }
            $request->session()->put('auth.two_factor_setup_phone', $phone);
        }

        $sent = TwoFactorOtp::issue($user, $method, TwoFactorOtp::PURPOSE_SETUP, $phone);
        if (! $sent['ok']) {
            return back()->withErrors([$method === 'sms' ? 'phone' : 'code' => $sent['error']]);
        }

        $request->session()->put('auth.two_factor_setup_method', $method);

        return redirect()->route('two-factor.settings')->with('status', 'two-factor-code-sent');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        $method = $request->session()->get('auth.two_factor_setup_method');
        if (! in_array($method, ['email', 'sms'], true)) {
            return redirect()->route('two-factor.settings');
        }

        $phone = $method === 'sms' ? $request->session()->get('auth.two_factor_setup_phone') : null;
        $sent = TwoFactorOtp::issue($user, $method, TwoFactorOtp::PURPOSE_SETUP, $phone);
        if (! $sent['ok']) {
            return back()->withErrors(['code' => $sent['error']]);
        }

        return back()->with('status', 'two-factor-code-sent');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $method = $request->session()->get('auth.two_factor_setup_method');

        if ($method === 'totp') {
            $secret = $request->session()->get('auth.two_factor_setup_secret');
            if (! $secret || ! Totp::verify($secret, $validated['code'])) {
                return back()->withErrors(['code' => 'err.2fa.invalidCode']);
            }

            $codes = Collection::times(8, fn () => Str::upper(Str::random(4).'-'.Str::random(4)))->all();
            $user->forceFill([
                'two_factor_method' => 'totp',
                'two_factor_secret' => encrypt($secret),
                'two_factor_recovery_codes' => encrypt(json_encode($codes)),
                'two_factor_confirmed_at' => now(),
            ])->save();

            $request->session()->forget(['auth.two_factor_setup_secret', 'auth.two_factor_setup_method']);
            $request->session()->put('auth.two_factor_passed', true);

            return redirect()
                ->route('two-factor.settings')
                ->with('status', 'two-factor-enabled')
                ->with('two_factor_recovery_codes', $codes);
        }

        if (! in_array($method, ['email', 'sms'], true)
            || ! TwoFactorOtp::verify($user, TwoFactorOtp::PURPOSE_SETUP, $validated['code'])) {
            return back()->withErrors(['code' => 'err.2fa.invalidCode']);
        }

        $phone = $method === 'sms' ? $request->session()->get('auth.two_factor_setup_phone') : null;
        $user->forceFill([
            'two_factor_method' => $method,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => now(),
            'phone' => $phone ?: $user->phone,
        ])->save();

        $request->session()->forget(['auth.two_factor_setup_method', 'auth.two_factor_setup_phone']);
        $request->session()->put('auth.two_factor_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->forceFill([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('auth.two_factor_passed');

        return redirect()->route('two-factor.settings')->with('status', 'two-factor-disabled');
    }

    private function isMandatory($user): bool
    {
        return ! $user->hasTwoFactorEnabled()
            && $user->requiresTwoFactor()
            && (bool) config('schoolos.security.require_two_factor_privileged');
    }
}
