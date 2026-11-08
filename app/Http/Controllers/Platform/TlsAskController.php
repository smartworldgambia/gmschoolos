<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\School\SchoolDomainService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * On-demand TLS "ask" endpoint for Caddy / Traefik / nginx-lua:
 *
 *   on_demand_tls { ask https://app.example.com/_internal/domains/allow?token=SECRET }
 *
 * The edge calls it with ?domain=host before issuing a certificate. We answer 200
 * only for verified hosts of active schools, so nobody can make the edge request
 * certificates for arbitrary domains.
 */
class TlsAskController extends Controller
{
    public function __invoke(Request $request, SchoolDomainService $domains): Response
    {
        $token = (string) config('schoolos.tls_ask_token', '');
        if ($token !== '' && ! hash_equals($token, (string) $request->query('token', ''))) {
            abort(403);
        }

        $host = (string) $request->query('domain', '');

        return $host !== '' && $domains->isHostServable($host)
            ? response('ok', 200)
            : response('not allowed', 404);
    }
}
