<?php

namespace App\Support;

/**
 * Where the files in public/ are on the web.
 *
 * Normally public/ is the web root and a file's URL is just its path. On ozserver.org the web root
 * is the project folder itself, so the same file is one level down, under /public. Worked out from
 * the request rather than configured, so a page links its images correctly on either.
 */
final class PublicUrl
{
    /** '' when public/ is the web root, '/public' (or deeper) when the web root is above it. */
    public static function prefix(): string
    {
        $webRoot = (string) request()->server('DOCUMENT_ROOT');

        if ($webRoot === '' || ($webRoot = realpath($webRoot)) === false || ($public = realpath(public_path())) === false) {
            return '';
        }

        $webRoot = rtrim(str_replace('\\', '/', $webRoot), '/');
        $public = str_replace('\\', '/', $public);

        return str_starts_with($public.'/', $webRoot.'/') ? substr($public, strlen($webRoot)) : '';
    }

    /**
     * The URL of a file in public/, stamped with when it last changed so a replaced file is not
     * served from a browser's cache. Null when the file is not there.
     */
    public static function file(string $relative): ?string
    {
        $absolute = public_path($relative);

        return is_file($absolute) ? self::prefix().'/'.$relative.'?v='.filemtime($absolute) : null;
    }
}
