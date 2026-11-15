<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use App\Support\TwoFactorOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $method = $user->twoFactorMethod();
        $sent = (bool) $request->session()->get('auth.two_factor_otp_sent');
        $status = session('status');

        if (in_array($method, ['email', 'sms'], true) && ! $sent && TwoFactorOtp::sendWait($user, TwoFactorOtp::PURPOSE_LOGIN) === 0) {
            $result = TwoFactorOtp::issue($user, $method, TwoFactorOtp::PURPOSE_LOGIN, $user->phone);
            if ($result['ok']) {
                $request->session()->put('auth.two_factor_otp_sent', true);
                $sent = true;
                $status = 'two-factor-code-sent';
            }
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'method' => $method,
            'destination' => $method === 'sms'
                ? TwoFactorOtp::maskPhone($user->phone)
                : TwoFactorOtp::maskEmail((string) $user->email),
            'sent' => $sent,
            'sendWait' => TwoFactorOtp::sendWait($user, TwoFactorOtp::PURPOSE_LOGIN),
            'status' => $status,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->hasTwoFactorEnabled(), 403);

        $method = $user->twoFactorMethod();
        if (! in_array($method, ['email', 'sms'], true)) {
            return redirect()->route('two-factor.challenge');
        }

        $result = TwoFactorOtp::issue($user, $method, TwoFactorOtp::PURPOSE_LOGIN, $user->phone);
        if (! $result['ok']) {
            return back()->withErrors(['code' => $result['error']]);
        }

        $request->session()->put('auth.two_factor_otp_sent', true);

        return back()->with('status', 'two-factor-code-sent');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        abort_unless($user?->hasTwoFactorEnabled(), 403);

        $code = $validated['code'];
        $method = $user->twoFactorMethod();

        $ok = match ($method) {
            'totp' => Totp::verify(decrypt($user->two_factor_secret), $code) || $user->consumeRecoveryCode($code),
            'email', 'sms' => TwoFactorOtp::verify($user, TwoFactorOtp::PURPOSE_LOGIN, $code) || $user->consumeRecoveryCode($code),
            default => false,
        };

        if (! $ok) {
            return back()->withErrors(['code' => 'err.2fa.invalidCode']);
        }

        $request->session()->forget('auth.two_factor_otp_sent');
        $request->session()->put('auth.two_factor_passed', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
