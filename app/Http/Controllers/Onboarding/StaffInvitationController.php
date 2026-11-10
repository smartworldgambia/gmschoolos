<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Invitation acceptance. The emailed token is the only credential; a live admin session must not block it. */
class StaffInvitationController extends Controller
{
    public function show(Request $request, string $token, StaffInvitationService $invitations): Response|SymfonyResponse
    {
        $invitation = $invitations->findUsable($token);

        if (! $invitation || $invitation->school?->suspended_at !== null) {
            // One generic answer for unknown / used / expired / revoked tokens.
            return Inertia::render('Auth/AcceptInvitation', ['invalid' => true])->toResponse(request())->setStatusCode(410);
        }

        $current = $request->user();

        return Inertia::render('Auth/AcceptInvitation', [
            'invalid' => false,
            'token' => $token,
            'signedIn' => $current ? ['name' => $current->name] : null,
            'invitation' => [
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'school' => $invitation->school->name,
                'expires_on' => $invitation->expires_at->toDateString(),
            ],
        ]);
    }

    public function accept(Request $request, string $token, StaffInvitationService $invitations): RedirectResponse
    {
        $invitation = $invitations->findUsable($token);

        if (! $invitation || $invitation->school?->suspended_at !== null) {
            return redirect()->route('invitations.show', $token);
        }

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($request->user()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $user = $invitations->accept($invitation, $data['password']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
