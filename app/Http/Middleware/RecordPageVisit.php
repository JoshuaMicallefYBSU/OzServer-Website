<?php

namespace App\Http\Middleware;

use App\Support\PageVisits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts a visit to a public page (App\Support\PageVisits): one per page per browser per hour.
 *
 * The first request for a page records a row and sets a cookie for that page holding only the time
 * it was opened, kept for an hour; while the cookie is there, the page is not counted again.
 * Used as `visit:home`, `visit:how-to` or `visit:map`.
 *
 * Not counted: HEAD requests, the live map embedded in the /console, and clients that say they are
 * a bot. A failure to record never stops the page being served.
 */
class RecordPageVisit
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|monitor|uptime|curl|wget|python-requests|headless/i';

    public function handle(Request $request, Closure $next, string $page): Response
    {
        $response = $next($request);

        $cookie = 'oz_visit_'.str_replace('-', '_', $page);
        if (! $request->isMethod('GET')
            || $request->cookies->has($cookie)
            || $request->query('embed') === '1'
            || preg_match(self::BOTS, (string) $request->userAgent())
            || ! $response->isSuccessful()) {
            return $response;
        }

        try {
            PageVisits::record($page);
        } catch (Throwable $e) {
            report($e);

            return $response;
        }

        // An hour, path-wide, readable by the server only; its value is just when the page was opened.
        $response->headers->setCookie(cookie($cookie, now()->toIso8601String(), 60, '/', null, $request->isSecure(), true, false, 'lax'));

        return $response;
    }
}
