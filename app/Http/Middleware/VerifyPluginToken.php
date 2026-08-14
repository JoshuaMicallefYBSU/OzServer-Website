<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the request carries the shared plugin token (config('services.
 * plugin.token'), set via PLUGIN_TOKEN in .env) as a Bearer token. The
 * plugin is the trusted party now - it self-reports controller_cid/
 * controller_callsign, and those aren't cross-checked against the live
 * VATSIM feed anymore (that was the previous scheme, before the plugin
 * existed to be the trusted caller).
 *
 * Deliberately namespaced as controller_cid/controller_callsign (not
 * cid/callsign) so it never collides with an endpoint's own payload - e.g.
 * the FDR upload's `callsign` field is the aircraft's, not the controller's.
 */
class VerifyPluginToken
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $expected = config('services.plugin.token');

        if (! $expected || $token === null || ! hash_equals((string) $expected, $token)) {
            abort(401, 'Invalid or missing plugin token.');
        }

        $cid = $request->input('controller_cid');
        $callsign = $request->input('controller_callsign');

        if (! $cid || ! $callsign) {
            abort(401, 'controller_cid and controller_callsign are required.');
        }

        $request->attributes->set('vatsim', [
            'cid' => (int) $cid,
            'callsign' => (string) $callsign,
        ]);

        return $next($request);
    }
}
