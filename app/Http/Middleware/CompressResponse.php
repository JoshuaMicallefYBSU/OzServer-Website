<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gzips the pages and JSON this site sends, for a browser that says it accepts gzip.
 *
 * The host serves PHP's output as it is given: the homepage, with the chart's coastline and sector
 * shapes in it, went out at 114 KB where gzip makes it about 35. Nothing is done where the
 * server is already compressing, or to a response that is streamed, a file, or already encoded.
 */
class CompressResponse
{
    private const MINIMUM_BYTES = 1024;

    private const TYPES = ['text/html', 'application/json', 'text/plain', 'image/svg+xml'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldCompress($request, $response)) {
            return $response;
        }

        $compressed = gzencode((string) $response->getContent(), 6);

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->setVary('Accept-Encoding', false);

        return $response;
    }

    private function shouldCompress(Request $request, Response $response): bool
    {
        if (! function_exists('gzencode')
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $response->headers->has('Content-Encoding')
            || filter_var(ini_get('zlib.output_compression'), FILTER_VALIDATE_BOOLEAN)
            || ! in_array('gzip', $request->getEncodings(), true)) {
            return false;
        }

        $content = $response->getContent();

        if (! is_string($content) || strlen($content) < self::MINIMUM_BYTES) {
            return false;
        }

        $type = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type', 'text/html'))[0]));

        return in_array($type, self::TYPES, true);
    }
}
