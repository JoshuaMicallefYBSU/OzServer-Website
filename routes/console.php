<?php

use App\Support\ChartViews;
use App\Support\VatsysCoordinate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

// Nothing scheduled here - OzServer-API owns all of this now, against its own
// Postgres database: stale-row pruning and VATSIM live-data both run on an
// internal timer inside its own process (see its runMaintenance/server.ts),
// and its `npm run dataset:sync` is triggered externally to that service, not
// from here. This website has no domain data of its own left to keep fresh.

// Builds a homepage chart view's coastline (App\Support\ChartViews) from its datasets' vatSys coast
// maps: projected into the view, cut to what falls near it, and simplified to about a third of a
// chart unit, so it costs the page little. Run again only if a coast map changes; the result is
// committed in resources/data.
//
// A line that closes on itself is an island and goes into `land`, which is filled like Australia's;
// one that does not (a stretch of mainland coast) goes into `shore`, which is only stroked.
Artisan::command('landing:coast {region}', function (string $region) {
    $view = ChartViews::ALL[$region] ?? null;
    if ($view === null || $view['coastSources'] === []) {
        $this->error("No coast sources for {$region}.");

        return 1;
    }

    $land = $shore = '';
    $kept = 0;
    foreach ($view['coastSources'] as $url) {
        $xml = Http::timeout(60)->get($url)->throw()->body();
        preg_match_all('/<Line\b[^>]*>(.*?)<\/Line>/s', $xml, $lines);
        foreach ($lines[1] as $body) {
            try {
                $points = VatsysCoordinate::decodeBoundary(preg_replace('/<!--.*?-->/s', '', $body));
            } catch (InvalidArgumentException) {
                continue;
            }
            if (count($points) < 2 || ! collect($points)->contains(fn ($p) => ChartViews::inExtent($view, $p['lat'], $p['lon'], 1.0))) {
                continue;
            }
            $xy = array_map(fn ($p) => [ChartViews::projectX($view, $p['lon']), ChartViews::projectY($view, $p['lat'])], $points);
            $closed = count($xy) > 3 && abs($xy[0][0] - end($xy)[0]) < 0.05 && abs($xy[0][1] - end($xy)[1]) < 0.05;
            $xy = simplifyLine($xy, 0.35);
            if (count($xy) < ($closed ? 4 : 2)) {
                continue;
            }
            $path = implode('', array_map(fn ($p, $i) => ($i ? 'L' : 'M').round($p[0], 1).' '.round($p[1], 1), $xy, array_keys($xy)));
            $closed ? $land .= $path.'Z' : $shore .= $path;
            $kept++;
        }
    }

    $file = resource_path('data/'.$view['coast']);
    file_put_contents($file, json_encode(['land' => $land, 'shore' => $shore]));
    $this->info("{$kept} lines, ".number_format(filesize($file)).' bytes: '.$file);

    return 0;
})->purpose('Build a homepage chart view\'s coastline from its datasets\' coast maps');

// Douglas-Peucker: drops the points that sit within `tolerance` of the line through their
// neighbours.
function simplifyLine(array $points, float $tolerance): array
{
    if (count($points) < 3) {
        return $points;
    }
    [$ax, $ay] = $points[0];
    [$bx, $by] = end($points);
    $dx = $bx - $ax;
    $dy = $by - $ay;
    $length = sqrt($dx * $dx + $dy * $dy);
    $furthest = 0.0;
    $index = 0;
    for ($i = 1, $n = count($points) - 1; $i < $n; $i++) {
        [$px, $py] = $points[$i];
        $distance = $length > 0 ? abs($dy * $px - $dx * $py + $bx * $ay - $by * $ax) / $length : hypot($px - $ax, $py - $ay);
        if ($distance > $furthest) {
            $furthest = $distance;
            $index = $i;
        }
    }
    if ($furthest <= $tolerance) {
        return [$points[0], end($points)];
    }

    return array_merge(
        array_slice(simplifyLine(array_slice($points, 0, $index + 1), $tolerance), 0, -1),
        simplifyLine(array_slice($points, $index), $tolerance)
    );
}
