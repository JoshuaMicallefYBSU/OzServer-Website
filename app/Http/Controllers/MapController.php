<?php

namespace App\Http\Controllers;

use App\Models\AtisBroadcast;
use App\Models\FlightDataRecord;
use App\Models\Position;
use App\Models\Sector;
use App\Models\SectorOwnership;
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
     * Recently-seen aircraft with position data - everything the popup
     * needs to show the full FDR picture for a flight, not just enough to
     * place the marker. Rows older than 5 minutes are dropped here rather
     * than needing a separate cleanup job. Requires `lat` (no position, no
     * marker) - if nothing shows up here, check that the plugin is
     * actually sending position fields on its /v1/fdr pushes, not just the
     * flight-plan fields.
     */
    public function aircraft()
    {
        $flights = FlightDataRecord::whereNotNull('lat')
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->get([
                'callsign', 'lat', 'lon', 'heading', 'altitude', 'ground_speed', 'vertical_rate', 'on_ground',
                'aircraft_type', 'aircraft_wake', 'aircraft_equip', 'aircraft_surv_equip',
                'dep_airport', 'des_airport', 'route', 'sid_star_string', 'runway_string', 'departure_runway',
                'flight_rules', 'rfl', 'cfl_lower', 'cfl_upper', 'assigned_ssr_code',
                'atd', 'etd', 'eet_minutes', 'tas', 'state', 'remarks',
                'controlling_cid', 'controlling_callsign', 'current_sector', 'last_seen_at',
            ]);

        return response()->json($flights);
    }

    /**
     * ATIS broadcasts currently saved in the DB (App\Jobs\PruneStaleDataJob
     * drops rows once they're 90 minutes stale, so nothing further to
     * filter by age here), placed using the airport's vatSys ASMGCS
     * position as a stand-in for the airport's own coordinates. Only
     * `positions` rows with an ASMGCSAirport are usable for this, so an
     * ATIS for an airport without a defined ground/ASMGCS position in the
     * dataset is dropped rather than shown with no location.
     */
    public function atis()
    {
        $airportCoordinates = Position::whereNotNull('asmgcs_airport')
            ->get(['asmgcs_airport', 'default_lat', 'default_lon'])
            ->keyBy('asmgcs_airport');

        return response()->json(
            AtisBroadcast::all()
                ->map(function (AtisBroadcast $atis) use ($airportCoordinates) {
                    $position = $airportCoordinates->get($atis->icao);

                    return [
                        'icao' => $atis->icao,
                        'atis_letter' => $atis->atis_letter,
                        'content' => $atis->content,
                        'frequency' => $atis->frequency,
                        'last_seen_at' => $atis->last_seen_at,
                        'lat' => $position?->default_lat,
                        'lon' => $position?->default_lon,
                    ];
                })
                ->filter(fn (array $atis) => $atis['lat'] !== null && $atis['lon'] !== null)
                ->values()
        );
    }

    /**
     * Sort priority for the online-controllers list: Flow first, then
     * Centre/FSS, then Approach/Departure together, then Tower/Ground/
     * Delivery together - not the same restricted set the map's sector
     * polygons use (VISIBLE_SECTOR_TYPES), since staffing/ownership isn't
     * type-limited the way the map display is.
     */
    private const TYPE_PRIORITY = [
        'FMP' => 0,
        'CTR' => 1,
        'FSS' => 1,
        'APP' => 2,
        'DEP' => 2,
        'TWR' => 3,
        'GND' => 3,
        'DEL' => 3,
    ];

    /**
     * Online controllers matched to a known Sector.callsign, with the
     * frequencies each is actually transmitting/receiving on per the AFV
     * transceiver feed (App\Jobs\RefreshVatsimLiveDataJob) - a controller can
     * be on more than one via AFV multiplexing, so this is a list, not a
     * single frequency. Falls back to the datafeed's own single frequency
     * if AFV data isn't cached yet. Ordered Flow, then Centre (incl. FSS),
     * then Approach/Departure, then Tower/Ground/Delivery, alphabetically
     * by sector within each tier.
     *
     * `is_ozserver` reflects whether the controller currently holds their
     * sector via a SectorOwnership row - i.e. actually claimed it through
     * the OzServer plugin - rather than just being visible on the raw
     * VATSIM datafeed on a recognised position/callsign.
     */
    public function controllers()
    {
        $data = Cache::remember('vatsimdata', 15, fn () => (new VATSIMClient)->getVATSIMData());
        $afvFrequencies = $this->afvFrequenciesByCallsign();

        $sectorsByCallsign = Sector::whereNotNull('callsign')->get(['callsign', 'name', 'type'])->keyBy('callsign');
        $ozserverCids = SectorOwnership::pluck('controller_cid')->all();

        $controllers = collect($data->controllers ?? [])
            ->filter(fn ($controller) => $sectorsByCallsign->has($controller->callsign))
            ->map(function ($controller) use ($sectorsByCallsign, $afvFrequencies, $ozserverCids) {
                $sector = $sectorsByCallsign->get($controller->callsign);

                return [
                    'cid' => $controller->cid,
                    'callsign' => $controller->callsign,
                    'frequencies' => $afvFrequencies[$controller->callsign] ?? [(float) $controller->frequency],
                    'sector_name' => $sector->name,
                    'type' => $sector->type,
                    'is_ozserver' => in_array((int) $controller->cid, $ozserverCids, true),
                ];
            })
            ->sortBy(fn ($controller) => sprintf(
                '%d-%s',
                self::TYPE_PRIORITY[$controller['type']] ?? 99,
                $controller['sector_name']
            ))
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
