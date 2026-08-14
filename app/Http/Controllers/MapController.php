<?php

namespace App\Http\Controllers;

use App\Models\FlightDataRecord;
use App\Models\Sector;
use App\Services\VATSIMClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MapController extends Controller
{
    /**
     * Sector types shown on the map: Tower, Approach, Departure, and
     * Enroute ("ENR", stored as the raw vatSys callsign suffix "CTR").
     * Ground/Delivery/flow-management/leftover-FSS sectors are excluded.
     */
    private const VISIBLE_SECTOR_TYPES = ['TWR', 'APP', 'DEP', 'CTR', 'FSS'];

    /**
     * Sector polygons, current ownership, and online status - claimed
     * sectors only, everything else is left off the map entirely. A
     * handful of sectors still won't have geometry - not every domestic
     * sector has a matching Volume in Volumes.xml - so `boundary` is
     * sometimes an empty list rather than an error.
     */
    public function sectors()
    {
        $onlineCallsigns = $this->onlineControllerCallsigns();

        $sectors = Sector::whereIn('type', self::VISIBLE_SECTOR_TYPES)
            ->whereHas('ownership')
            ->with(['volumes', 'ownership'])
            ->get();

        // A staffed sector also covers whatever it's "responsible for" -
        // sub-sectors that fold into it while nobody's working them
        // individually (e.g. OLW covers LEO/MEK/MTK/NEW/MZI/POT/PAR
        // whenever OLW alone is online), per Sectors.xml's
        // <ResponsibleSectors>, already stored on the sector itself.
        $coveredNames = [];

        foreach ($sectors as $sector) {
            if ($sector->callsign !== null && in_array($sector->callsign, $onlineCallsigns, true)) {
                $coveredNames[] = $sector->name;

                foreach ($sector->responsible_sectors ?? [] as $responsibleName) {
                    $coveredNames[] = $responsibleName;
                }
            }
        }

        $coveredNames = array_unique($coveredNames);

        return response()->json($sectors->map(fn (Sector $sector) => [
            'name' => $sector->name,
            'full_name' => $sector->full_name,
            'callsign' => $sector->callsign,
            'frequency' => $sector->frequency,
            // Each Volume's own `boundary` is already a list of rings (a
            // Volume can span multiple Boundaries), so this flattens all of
            // a sector's volumes down to one flat list of rings rather than
            // nesting per-volume.
            'boundary' => $sector->volumes->flatMap(fn ($volume) => $volume->boundary)->values(),
            'owner' => $sector->ownership !== null ? [
                'cid' => $sector->ownership->controller_cid,
                'callsign' => $sector->ownership->controller_callsign,
            ] : null,
            'online' => in_array($sector->name, $coveredNames, true),
        ]));
    }

    /**
     * Recently-seen aircraft with position data. Rows older than 5 minutes
     * are dropped here rather than needing a separate cleanup job.
     */
    public function aircraft()
    {
        $flights = FlightDataRecord::whereNotNull('lat')
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->get([
                'callsign', 'lat', 'lon', 'heading', 'altitude', 'ground_speed',
                'aircraft_type', 'dep_airport', 'des_airport', 'rfl', 'cfl_lower', 'cfl_upper', 'state',
            ]);

        return response()->json($flights);
    }

    /**
     * Online controllers matched to a known Sector.callsign (restricted to
     * the same TWR/APP/DEP/ENR types shown on the map, so this list stays
     * consistent with what's actually rendered), with the frequencies each
     * is actually transmitting/receiving on per the AFV transceiver feed
     * (App\Jobs\AFVTransieversUpdate) - a controller can be on more than
     * one via AFV multiplexing, so this is a list, not a single frequency.
     * Falls back to the datafeed's own single frequency if AFV data isn't
     * cached yet.
     */
    public function controllers()
    {
        $data = Cache::remember('vatsimdata', 15, fn () => (new VATSIMClient)->getVATSIMData());
        $afvFrequencies = $this->afvFrequenciesByCallsign();

        $sectorsByCallsign = Sector::whereNotNull('callsign')
            ->whereIn('type', self::VISIBLE_SECTOR_TYPES)
            ->pluck('name', 'callsign');

        $controllers = collect($data->controllers ?? [])
            ->filter(fn ($controller) => $sectorsByCallsign->has($controller->callsign))
            ->map(fn ($controller) => [
                'cid' => $controller->cid,
                'callsign' => $controller->callsign,
                'frequencies' => $afvFrequencies[$controller->callsign] ?? [(float) $controller->frequency],
                'sector_name' => $sectorsByCallsign->get($controller->callsign),
            ])
            ->values();

        return response()->json($controllers);
    }

    /**
     * @return list<string>
     */
    private function onlineControllerCallsigns(): array
    {
        $data = Cache::remember('vatsimdata', 15, fn () => (new VATSIMClient)->getVATSIMData());

        return collect($data->controllers ?? [])->pluck('callsign')->all();
    }

    /**
     * @return array<string, list<float>>
     */
    private function afvFrequenciesByCallsign(): array
    {
        if (! Storage::exists('afv-transceivers.json')) {
            return [];
        }

        $transceivers = json_decode(Storage::get('afv-transceivers.json'), true) ?? [];

        $result = [];

        foreach ($transceivers as $entry) {
            $frequencies = array_values(array_unique(array_map(
                fn (array $t) => round($t['frequency'] / 1_000_000, 3),
                $entry['transceivers'] ?? []
            )));

            $result[$entry['callsign']] = $frequencies;
        }

        return $result;
    }
}
