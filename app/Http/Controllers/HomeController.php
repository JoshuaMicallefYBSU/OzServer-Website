<?php

namespace App\Http\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    /**
     * A handful of oceanic CTR sectors (Norfolk, Solomon Sea, Fiji boundary areas)
     * extend thousands of miles out over the Pacific - including them would shrink
     * the continent to a speck in the middle of the homepage's decorative map, so
     * they're left out of it entirely. Every other CTR sector, tiled together,
     * already traces the coastline - no separate outline needed.
     */
    private const EXCLUDED_SECTORS = ['HWE', 'AYPM', 'NFFJ'];

    /**
     * These "activate" on the homepage map - each one, plus every sector it's
     * responsible for while combined (a sector's own `responsible_sectors`,
     * synced from vatSys's own ResponsibleSectors - the same coverage
     * OzServer-API's /map/sectors folds into a staffed sector's "online" area),
     * pulses together as one group.
     *
     * Grouped into batches of at most MAX_ACTIVE_GROUP_SIZE so that no more than
     * that many are ever pulsing at once - each batch gets its own slice of the
     * animation cycle (see the animation-delay math in index()), and only one
     * batch's slice is "live" at a time. Changing the number of batches here
     * means updating the animation-duration and @keyframes percentages in
     * landing.blade.php to match.
     */
    private const ACTIVATED_SECTOR_GROUPS = [
        ['BLA', 'GUN', 'SNO', 'INL', 'HYD'],
        ['TRT', 'OLW', 'ISA', 'KEN', 'KPL'],
        ['ARL', 'MNN', 'ASP', 'TBD', 'MUN'],
        ['HUO'],
    ];

    private const MAX_ACTIVE_GROUP_SIZE = 5;

    private const LON_MIN = 110.0;

    private const LON_MAX = 157.5;

    private const LAT_MIN = -45.5;

    private const LAT_MAX = -6.0;

    public function index()
    {
        $allSectors = $this->sectorShapes()->keyBy('name');

        $ctrSectors = $allSectors->filter(fn (array $sector) => ($sector['type'] ?? null) === 'CTR'
            && ! in_array($sector['name'], self::EXCLUDED_SECTORS, true));

        $primaries = $allSectors->only(Arr::flatten(self::ACTIVATED_SECTOR_GROUPS));

        // Which primary (by its slot in ACTIVATED_SECTOR_GROUPS) each activated
        // sector belongs to - the primaries themselves, plus every child sector
        // they cover once combined. A child shares its parent's activated_index so
        // the whole group pulses in sync rather than independently. Each batch
        // reserves MAX_ACTIVE_GROUP_SIZE slots regardless of its own size, so a
        // batch's pulses never spill into the next batch's slice of the cycle.
        $activatedIndexByName = [];

        foreach (self::ACTIVATED_SECTOR_GROUPS as $groupIndex => $group) {
            foreach ($group as $positionInGroup => $name) {
                $activatedIndexByName[$name] = $groupIndex * self::MAX_ACTIVE_GROUP_SIZE + $positionInGroup;
            }
        }

        foreach ($primaries as $primary) {
            $parentIndex = $activatedIndexByName[$primary['name']];

            foreach ($primary['responsible_sectors'] ?? [] as $childName) {
                $activatedIndexByName[$childName] ??= $parentIndex;
            }
        }

        // Child sectors aren't necessarily CTR (e.g. BLA covers the MAE/MAV/SAS/PHA
        // approach sectors while combined) - fetch whichever of them the CTR filter
        // above didn't already pick up.
        $missingChildren = array_diff(array_keys($activatedIndexByName), $ctrSectors->keys()->all());
        $extraSectors = $allSectors->only($missingChildren);

        $sectors = $ctrSectors->concat($extraSectors)
            ->map(fn (array $sector) => $this->sectorToMapData($sector, $activatedIndexByName))
            ->filter()
            ->values();

        return view('landing', [
            'mapSectors' => $sectors,
            // The page projects live aircraft itself, with the same formula as projectX/projectY,
            // so their tags sit on the same map as the sector shapes drawn here.
            'mapBounds' => [
                'lonMin' => self::LON_MIN, 'lonMax' => self::LON_MAX,
                'latMin' => self::LAT_MIN, 'latMax' => self::LAT_MAX,
            ],
        ]);
    }

    /**
     * Every sector's static geometry, from OzServer-API's public
     * /map/sector-shapes (unlike /map/sectors, not staffed-only - the homepage's
     * animation is a decorative loop unrelated to who is actually online).
     *
     * Cached for a day on success, since the underlying dataset only changes
     * when OzServer-API's own daily sync runs - the homepage has no need to hit
     * the API on every single visit. Only a minute on failure/empty though, so
     * a transient API outage self-heals quickly instead of leaving the
     * homepage's map empty for the rest of the day.
     */
    private function sectorShapes(): Collection
    {
        $cached = Cache::get('landing-sector-shapes');

        if ($cached !== null) {
            return collect($cached);
        }

        try {
            $response = Http::timeout(5)->get(
                rtrim(config('services.ozserver_api.url'), '/').'/api/v1/map/sector-shapes'
            );

            $sectors = $response->successful() ? $response->json() : [];
        } catch (\Throwable) {
            $sectors = [];
        }

        Cache::put('landing-sector-shapes', $sectors, $sectors === [] ? now()->addMinute() : now()->addDay());

        return collect($sectors);
    }

    /**
     * @param  array<string, mixed>  $sector
     * @param  array<string, int>  $activatedIndexByName
     */
    private function sectorToMapData(array $sector, array $activatedIndexByName): ?array
    {
        $rings = collect($sector['boundary'] ?? [])
            ->filter(fn (array $ring) => count($ring) > 1)
            ->values();

        if ($rings->isEmpty()) {
            return null;
        }

        $activatedIndex = $activatedIndexByName[$sector['name']] ?? null;

        return [
            'name' => $sector['name'],
            'path' => $rings->map(fn (array $ring) => $this->ringToPath($ring))->implode(' '),
            'activated' => $activatedIndex !== null,
            'activated_index' => $activatedIndex,
        ];
    }

    private function ringToPath(array $ring): string
    {
        return collect($ring)
            ->map(function (array $point, int $index) {
                $x = round($this->projectX($point['lon']), 2);
                $y = round($this->projectY($point['lat']), 2);

                return ($index === 0 ? 'M' : 'L')."{$x} {$y}";
            })
            ->implode(' ').' Z';
    }

    private function projectX(float $lon): float
    {
        return ($lon - self::LON_MIN) / (self::LON_MAX - self::LON_MIN) * 100;
    }

    private function projectY(float $lat): float
    {
        return (self::LAT_MAX - $lat) / (self::LAT_MAX - self::LAT_MIN) * 100;
    }
}
