<?php

namespace App\Support;

/**
 * The homepage chart's views: one per airspace region OzServer covers (the API's src/regions.ts),
 * which the page cycles through.
 *
 * Each view has its own projection: longitude and latitude to the chart's units, 20 to a degree of
 * latitude, with longitude narrowed for the view's standard parallel so the land keeps its shape.
 * `viewBox` is the part of that plane shown (and `narrow`, on a phone); every view keeps Australia's
 * proportions, so swapping one for another moves nothing on the page.
 *
 * `wrap` takes longitudes west of Greenwich onto 180-360, for a view that runs past the antimeridian
 * (New Zealand's Chatham Islands). `extent` is the area the view shows, in degrees, and decides which
 * sectors' outlines and which aircraft are drawn in it.
 *
 * `coast` is the coastline file in resources/data: Australia's is a single land path, drawn by hand
 * from the Australia dataset's COAST_ALL map; the others are built from their datasets' coast maps
 * by `php artisan landing:coast`, from `coastSources`.
 */
class ChartViews
{
    public const ALL = [
        'australia' => [
            'label' => 'Australia',
            'airspace' => 'Australian airspace',
            'lon0' => 84.0, 'lat0' => -3.0, 'parallel' => 26.0,
            'viewBox' => [431, 30, 950, 840],
            'narrow' => [455, 90, 850, 760],
            'wrap' => false,
            'extent' => ['lat' => [-46.5, -4.5], 'lon' => [96.0, 162.0]],
            // Oceanic CTR sectors (Norfolk, Solomon Sea, Fiji boundary areas) that run thousands of
            // miles out over the Pacific, far past the chart's edge.
            'exclude' => ['HWE', 'AYPM', 'NFFJ'],
            // Every Australian CTR sector is drawn, bar those above, as the chart always has.
            'clip' => false,
            'coast' => 'coast-australia.txt',
            'coastSources' => [],
        ],
        'new-zealand' => [
            'label' => 'New Zealand',
            'airspace' => 'New Zealand airspace',
            'lon0' => 157.0, 'lat0' => -30.0, 'parallel' => 41.0,
            'viewBox' => [53, 40, 407, 360],
            'narrow' => [60, 46, 393, 351],
            'wrap' => true,
            'extent' => ['lat' => [-50.0, -32.0], 'lon' => [160.5, 187.5]],
            'exclude' => [],
            // The oceanic sectors (Auckland Oceanic, Tahiti, Samoa, Rarotonga, McMurdo) reach the
            // pole and the equator: only sectors within the view are drawn.
            'clip' => true,
            'coast' => 'coast-new-zealand.json',
            'coastSources' => [
                'https://raw.githubusercontent.com/vatSys/new-zealand-dataset/master/Maps/COAST.xml',
                // The Pacific islands: Lord Howe, Norfolk, the Kermadecs, the Chathams.
                'https://raw.githubusercontent.com/vatSys/combined-oceanic-dataset/main/Maps/Coast.xml',
            ],
        ],
        'mexico' => [
            'label' => 'Mexico',
            'airspace' => 'Mexican airspace',
            'lon0' => -123.0, 'lat0' => 38.4, 'parallel' => 22.0,
            'viewBox' => [0, 0, 742, 656],
            'narrow' => [15, 12, 712, 634],
            'wrap' => false,
            'extent' => ['lat' => [5.6, 38.4], 'lon' => [-123.0, -83.0]],
            'exclude' => [],
            'clip' => true,
            'coast' => 'coast-mexico.json',
            'coastSources' => [
                'https://raw.githubusercontent.com/vatmex/vatmex-mmfr-sector/central/Maps/%5BUniversal%5D/COASTLINE.xml',
            ],
        ],
    ];

    /**
     * The projection the page is given for a view (the same numbers projectX/projectY use).
     *
     * @return array{lon0: float, lat0: float, k: float, c: float, wrap: bool}
     */
    public static function projection(array $view): array
    {
        return [
            'lon0' => $view['lon0'],
            'lat0' => $view['lat0'],
            'k' => 20.0,
            'c' => cos(deg2rad($view['parallel'])),
            'wrap' => $view['wrap'],
        ];
    }

    public static function projectX(array $view, float $lon): float
    {
        if ($view['wrap'] && $lon < 0) {
            $lon += 360;
        }

        return ($lon - $view['lon0']) * cos(deg2rad($view['parallel'])) * 20.0;
    }

    public static function projectY(array $view, float $lat): float
    {
        return ($view['lat0'] - $lat) * 20.0;
    }

    /**
     * Whether a point lies in the view's extent, `margin` degrees beyond it allowed.
     */
    public static function inExtent(array $view, float $lat, float $lon, float $margin = 0.0): bool
    {
        if ($view['wrap'] && $lon < 0) {
            $lon += 360;
        }
        [$south, $north] = $view['extent']['lat'];
        [$west, $east] = $view['extent']['lon'];

        return $lat >= $south - $margin && $lat <= $north + $margin && $lon >= $west - $margin && $lon <= $east + $margin;
    }
}
