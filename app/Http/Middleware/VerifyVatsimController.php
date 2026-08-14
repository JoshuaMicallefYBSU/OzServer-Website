<?php

namespace App\Http\Middleware;

use App\Services\VATSIMClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the request's claimed controller_cid+controller_callsign against
 * the live VATSIM data feed (App\Services\VATSIMClient, already
 * 15s-cached). There's no login system - a currently-online VATSIM
 * connection under that exact callsign *is* the credential.
 *
 * Deliberately namespaced as controller_cid/controller_callsign (not
 * cid/callsign) so it never collides with an endpoint's own payload - e.g.
 * the FDR upload's `callsign` field is the aircraft's, not the controller's.
 */
class VerifyVatsimController
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $cid = $request->input('controller_cid');
        $callsign = $request->input('controller_callsign');

        if (! $cid || ! $callsign) {
            abort(401, 'controller_cid and controller_callsign are required.');
        }

        $controller = (new VATSIMClient)->searchCallsign($callsign, true);

        if ($controller === null || (int) $controller->cid !== (int) $cid) {
            abort(401, 'Not currently connected to VATSIM under that callsign.');
        }

        $request->attributes->set('vatsim', [
            'cid' => (int) $controller->cid,
            'callsign' => (string) $controller->callsign,
        ]);

        return $next($request);
    }
}
