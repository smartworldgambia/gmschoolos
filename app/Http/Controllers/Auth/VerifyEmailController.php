<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EmailVerification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    /**
     * Confirm the address from the signed e-mail link and sign that administrator in.
     * The link itself is the proof — a live session for a different user must not block it.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        if (! EmailVerification::signatureIsValid($request)) {
            throw new InvalidSignatureException;
        }

        $user = User::query()->find($request->route('id'));

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        $this->authenticateAs($request, $user);

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }

    protected function authenticateAs(Request $request, User $user): void
    {
        $current = $request->user();

        if ($current && (int) $current->getKey() === (int) $user->getKey()) {
            return;
        }

        if ($current) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.two_factor_passed', true);
    }
}
