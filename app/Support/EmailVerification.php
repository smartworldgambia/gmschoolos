<?php

namespace App\Support;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Signed verification links are the credential (like invitation tokens). They must work from
 * Gmail on a logged-out device, and must not 403 just because another SchoolOS session is open.
 */
final class EmailVerification
{
    public static function expireMinutes(): int
    {
        return (int) config('auth.verification.expire', 60);
    }

    /** Path + signature only — host-independent so apex / www / Cloud host all validate. */
    public static function relativeUrl(MustVerifyEmail $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(self::expireMinutes()),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
            absolute: false,
        );
    }

    /** Absolute href for the e-mail button; signature is still the relative one. */
    public static function signedUrl(MustVerifyEmail $user): string
    {
        return rtrim((string) config('app.url'), '/').self::relativeUrl($user);
    }

    public static function signatureIsValid(Request $request): bool
    {
        // Relative (new mail) or absolute (links already in inboxes).
        return $request->hasValidSignature(absolute: false)
            || $request->hasValidSignature(absolute: true);
    }
}
