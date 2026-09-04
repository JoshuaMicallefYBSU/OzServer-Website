<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\Support\Arr;

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
     * responsible for while combined (Sector::responsible_sectors, synced from
     * vatSys's own ResponsibleSectors - the same coverage MapController::sectors()
     * folds into a staffed sector's "online" area), pulses together as one group.
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

    private const AIRPORTS = [
        ['code' => 'YBBN', 'lon' => 153.1218, 'lat' => -27.3942],
        ['code' => 'YSSY', 'lon' => 151.1797, 'lat' => -33.9508],
        ['code' => 'YMML', 'lon' => 144.8403, 'lat' => -37.6714],
        ['code' => 'YPAD', 'lon' => 138.5264, 'lat' => -34.9450],
        ['code' => 'YPPH', 'lon' => 115.9669, 'lat' => -31.9402],
        ['code' => 'YPDN', 'lon' => 130.8792, 'lat' => -12.4142],
        ['code' => 'YBCS', 'lon' => 145.7501, 'lat' => -16.8785],
        ['code' => 'YMLT', 'lon' => 147.2089, 'lat' => -41.5453],
        ['code' => 'YMHB', 'lon' => 147.5103, 'lat' => -42.8389],
        ['code' => 'YBAS', 'lon' => 133.8981, 'lat' => -23.8058],
        ['code' => 'YBRM', 'lon' => 122.2319, 'lat' => -17.9447],
        ['code' => 'YAYE', 'lon' => 130.9761, 'lat' => -25.1867],
        ['code' => 'YMIA', 'lon' => 142.0819, 'lat' => -34.2231],
    ];

    public function index()
    {
        // Kept fresh automatically by App\Jobs\SyncVatsysDatasetJob, scheduled
        // dailyAt('10:15') in routes/console.php - no separate fetch needed here.
        $ctrSectors = Sector::where('type', 'CTR')
            ->whereNotIn('name', self::EXCLUDED_SECTORS)
            ->with('volumes')
            ->get();

        $primaries = Sector::whereIn('name', Arr::flatten(self::ACTIVATED_SECTOR_GROUPS))
            ->get(['name', 'responsible_sectors']);

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
            $parentIndex = $activatedIndexByName[$primary->name];

            foreach ($primary->responsible_sectors ?? [] as $childName) {
                $activatedIndexByName[$childName] ??= $parentIndex;
            }
        }

        // Child sectors aren't necessarily CTR (e.g. BLA covers the MAE/MAV/SAS/PHA
        // approach sectors while combined) - fetch whichever of them the CTR query
        // above didn't already pick up.
        $missingChildren = array_diff(array_keys($activatedIndexByName), $ctrSectors->pluck('name')->all());
        $extraSectors = $missingChildren !== []
            ? Sector::whereIn('name', $missingChildren)->with('volumes')->get()
            : collect();

        $sectors = $ctrSectors->concat($extraSectors)
            ->map(fn (Sector $sector) => $this->sectorToMapData($sector, $activatedIndexByName))
            ->filter()
            ->values();

        $airports = collect(self::AIRPORTS)->map(fn (array $airport) => [
            'code' => $airport['code'],
            'x' => round($this->projectX($airport['lon']), 2),
            'y' => round($this->projectY($airport['lat']), 2),
        ])->values();

        return view('landing', [
            'mapSectors' => $sectors,
            'mapAirports' => $airports,
        ]);
    }

    /**
     * @param  array<string, int>  $activatedIndexByName
     */
    private function sectorToMapData(Sector $sector, array $activatedIndexByName): ?array
    {
        $rings = $sector->volumes
            ->flatMap(fn ($volume) => $volume->boundary)
            ->filter(fn ($ring) => count($ring) > 1)
            ->values();

        if ($rings->isEmpty()) {
            return null;
        }

        $activatedIndex = $activatedIndexByName[$sector->name] ?? null;

        return [
            'name' => $sector->name,
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
