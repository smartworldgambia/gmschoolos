<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser-hardening headers plus a nonce-based Content-Security-Policy.
 *
 * The nonce is generated per request via Vite::useCspNonce(); app.blade.php applies it to every
 * inline <script> (Ziggy routes, Vite React refresh, service-worker registration) and Vite stamps it on
 * the module scripts it emits. There is no 'unsafe-inline' / 'unsafe-eval' for scripts.
 *
 * Tune with SCHOOLOS_CSP_MODE=enforce|report-only|off (config/schoolos.php -> security).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = (string) config('schoolos.security.csp_mode', 'enforce');
        $cspEnabled = config('schoolos.security.headers_enabled', true) && $mode !== 'off';

        if ($cspEnabled) {
            Vite::useCspNonce();
        }

        /** @var Response $response */
        $response = $next($request);

        if (! config('schoolos.security.headers_enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        if ($cspEnabled) {
            $header = $mode === 'report-only'
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';
            $response->headers->set($header, $this->policy((string) Vite::cspNonce()));
        }

        if (config('schoolos.security.hsts_enabled') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    protected function policy(string $nonce): string
    {
        $scriptSrc = ["'self'", "'nonce-{$nonce}'"];
        $connectSrc = ["'self'"];

        // Vite dev server (npm run dev) writes public/hot; allow it only while it exists.
        if (is_file(public_path('hot'))) {
            $dev = ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://[::1]:5173'];
            $scriptSrc = array_merge($scriptSrc, $dev);
            $connectSrc = array_merge($connectSrc, $dev, ['ws://localhost:5173', 'ws://127.0.0.1:5173', 'ws://[::1]:5173']);
        }

        $scriptSrc = array_merge($scriptSrc, $this->extra('csp_extra_script_src'));
        $connectSrc = array_merge($connectSrc, $this->extra('csp_extra_connect_src'));

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            // Inline style attributes are used by the UI (school brand colours); styles cannot execute script.
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data: blob: https:",
            "media-src 'self' blob: https:",
            'connect-src '.implode(' ', $connectSrc),
            // Map embeds and LMS external (LTI/SCORM) tools are framed; frames cannot touch this page.
            "frame-src 'self' https:",
            "worker-src 'self'",
            "manifest-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        if (config('schoolos.security.force_https')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        if ($uri = config('schoolos.security.csp_report_uri')) {
            $directives[] = 'report-uri '.$uri;
        }

        return implode('; ', $directives);
    }

    /** @return list<string> */
    protected function extra(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config("schoolos.security.{$key}", '')))));
    }
}
