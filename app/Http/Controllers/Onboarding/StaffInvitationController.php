<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\StaffInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Public (guest) acceptance of a staff invitation. The token is the only credential. */
class StaffInvitationController extends Controller
{
    public function show(string $token, StaffInvitationService $invitations): Response|SymfonyResponse
    {
        $invitation = $invitations->findUsable($token);

        if (! $invitation || $invitation->school?->suspended_at !== null) {
            // One generic answer for unknown / used / expired / revoked tokens.
            return Inertia::render('Auth/AcceptInvitation', ['invalid' => true])->toResponse(request())->setStatusCode(410);
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'invalid' => false,
            'token' => $token,
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

        $user = $invitations->accept($invitation, $data['password']);

        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
