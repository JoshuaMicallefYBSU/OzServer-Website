<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Decodes vatSys's coordinate encoding, which appears in two forms in the
 * same dataset: plain decimal degrees ("-34.945000+138.526389") and packed
 * sign+DMS with no separators ("-335318.000+1504241.654", i.e.
 * -33 deg 53' 18.000" / +150 deg 42' 41.654"). Which form a given value uses
 * is inferred from the digit count before the decimal point.
 */
class VatsysCoordinate
{
    /**
     * Decode a combined "<lat><lon>" token (e.g. Position DefaultCenter, or
     * a single point in a Volumes.xml boundary) into decimal degrees.
     *
     * @return array{lat: float, lon: float}
     */
    public static function decode(string $raw): array
    {
        $raw = trim($raw);

        if (! preg_match('/^([+-]\d+(?:\.\d+)?)([+-]\d+(?:\.\d+)?)$/', $raw, $matches)) {
            throw new InvalidArgumentException("Unrecognised vatSys coordinate: {$raw}");
        }

        return [
            'lat' => self::decodeComponent($matches[1]),
            'lon' => self::decodeComponent($matches[2]),
        ];
    }

    /**
     * Decode a Volumes.xml boundary body ("/"-separated combined tokens)
     * into an ordered list of [lat, lon] pairs.
     *
     * @return list<array{lat: float, lon: float}>
     */
    public static function decodeBoundary(string $body): array
    {
        $tokens = array_filter(array_map('trim', explode('/', $body)), fn (string $token) => $token !== '');

        return array_values(array_map(self::decode(...), $tokens));
    }

    private static function decodeComponent(string $signed): float
    {
        $sign = $signed[0] === '-' ? -1 : 1;
        $value = substr($signed, 1);

        [$intPart, $fracPart] = array_pad(explode('.', $value, 2), 2, '0');

        // 3 digits or fewer before the decimal point: already decimal degrees.
        if (strlen($intPart) <= 3) {
            return $sign * (float) $value;
        }

        // Packed DDMMSS (lat) / DDDMMSS (lon): last 2 digits are seconds' whole
        // part, the 2 before that are minutes, the rest are degrees.
        $seconds = (float) (substr($intPart, -2).'.'.$fracPart);
        $minutes = (float) substr($intPart, -4, 2);
        $degrees = (float) substr($intPart, 0, -4);

        return $sign * ($degrees + $minutes / 60 + $seconds / 3600);
    }
}
