<?php

namespace App\Http\Middleware;

use App\Support\DemoLoginCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Privileged roles must enrol in 2FA before using anything else (enforced when configured;
        // on by default in production - see schoolos.security.require_two_factor_privileged).
        // Seeded demo directory accounts stay usable without a TOTP enrolment.
        if (! $user->hasTwoFactorEnabled()
            && config('schoolos.security.require_two_factor_privileged')
            && $user->requiresTwoFactor()
            && ! DemoLoginCatalog::isDemoEmail((string) $user->email)
            && ! $request->routeIs('two-factor.*', 'logout', 'verification.*', 'password.*')
        ) {
            return redirect()->route('two-factor.settings')
                ->with('warning', 'Two-factor authentication is required for your role. Set it up to continue.');
        }

        if (! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('auth.two_factor_passed') === true) {
            return $next($request);
        }

        if ($request->routeIs('two-factor.*', 'logout')) {
            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }
}
