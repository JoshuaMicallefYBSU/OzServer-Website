<?php

namespace App\Jobs;

use App\Models\Airway;
use App\Models\Position;
use App\Models\Procedure;
use App\Models\Runway;
use App\Models\Sector;
use App\Models\Volume;
use App\Models\Waypoint;
use App\Support\VatsysCoordinate;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;

/**
 * Syncs the vatSys Australia and Pacific datasets
 * (github.com/vatSys/australia-dataset, github.com/vatSys/pacific-dataset -
 * same file format) into the DB: sector/volume/position reference data used
 * by the sector-ownership system, plus runway/procedure/airway/waypoint
 * data. Both datasets feed the same tables.
 *
 * Order matters: Australia is synced first, Pacific second. A handful of
 * sector/volume names are defined in both (e.g. AYPM/Port Moresby) -
 * Australia's copy is a bare stub (no geometry, listed there only for
 * handoff-list completeness), while Pacific's is the real, fully-defined
 * one. Since each upsert overwrites the previous value for that name,
 * syncing Pacific second lets its fuller data win. Names that are genuine
 * duplicates between the two (e.g. ARA/Arafura, defined identically in
 * both for cross-FIR map continuity) are harmless to sync twice either way.
 */
class SyncVatsysDatasetJob implements ShouldQueue
{
    use Queueable;

    private const DATASETS = [
        'https://raw.githubusercontent.com/vatSys/australia-dataset/master/',
        'https://raw.githubusercontent.com/vatSys/pacific-dataset/master/',
    ];

    public int $tries = 3;

    public int $backoff = 300;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Volumes before Sectors (Sectors reference Volume names), Sectors
        // before Positions (Positions reference Sector names).
        $this->syncVolumes();
        $this->syncSectors();
        $this->syncPositions();
        $this->syncAirspace();
    }

    private function syncVolumes(): void
    {
        DB::transaction(function () {
            $seenNames = [];

            foreach (self::DATASETS as $baseUrl) {
                $xml = $this->fetchXml($baseUrl, 'Volumes.xml');

                // <Boundary> elements are raw polygon geometry, keyed by a
                // descriptive name (e.g. "ADELAIDE_CONTROL_ZONE_(C)"). They
                // are not stored directly - <Volume> elements (below) are
                // the named, vertically-bounded things Sectors.xml's
                // <Volumes> list actually references (e.g. Volume "AUG" ->
                // Boundary "AUGUSTA"), each pointing at one or more
                // Boundaries to form its shape.
                $boundaries = [];

                foreach ($xml->Boundary as $boundaryNode) {
                    $boundaries[(string) $boundaryNode['Name']] = VatsysCoordinate::decodeBoundary((string) $boundaryNode);
                }

                foreach ($xml->Volume as $volumeNode) {
                    $name = (string) $volumeNode['Name'];

                    $boundaryNames = array_filter(array_map('trim', explode(',', (string) $volumeNode->Boundaries)));
                    $rings = array_values(array_filter(array_map(
                        fn (string $boundaryName) => $boundaries[$boundaryName] ?? null,
                        $boundaryNames
                    )));

                    Volume::updateOrCreate(
                        ['name' => $name],
                        [
                            'boundary' => $rings,
                            'lower_limit' => isset($volumeNode['LowerLimit']) ? (int) $volumeNode['LowerLimit'] : null,
                            'upper_limit' => isset($volumeNode['UpperLimit']) ? (int) $volumeNode['UpperLimit'] : null,
                        ]
                    );

                    $seenNames[] = $name;
                }
            }

            Volume::whereNotIn('name', array_unique($seenNames))->delete();
        });
    }

    private function syncSectors(): void
    {
        DB::transaction(function () {
            $seenNames = [];

            foreach (self::DATASETS as $baseUrl) {
                $xml = $this->fetchXml($baseUrl, 'Sectors.xml');

                foreach ($xml->Sector as $sectorNode) {
                    // DisplayInSectorsWindow="False" marks sectors outside
                    // this dataset's own controlled airspace that vatSys
                    // itself hides from the sectors list - foreign/oceanic
                    // FSS positions (Nadi, Auckland, Jakarta, Colombo, San
                    // Francisco Oceanic, etc.), not anything the plugin
                    // needs to care about. Skipping them here means they're
                    // never created, and any that exist from a previous
                    // sync get pruned below.
                    if (isset($sectorNode['DisplayInSectorsWindow'])
                        && ! filter_var((string) $sectorNode['DisplayInSectorsWindow'], FILTER_VALIDATE_BOOLEAN)) {
                        continue;
                    }

                    $name = (string) $sectorNode['Name'];
                    $callsign = isset($sectorNode['Callsign']) ? (string) $sectorNode['Callsign'] : null;

                    // The callsign's own suffix (CTR/TWR/APP/DEP/GND/DEL/FMP/
                    // FSS) is what vatSys itself uses to categorise a sector.
                    $callsignParts = $callsign !== null ? explode('_', $callsign) : [];
                    $type = $callsignParts !== [] ? end($callsignParts) : null;

                    $responsibleSectors = trim((string) $sectorNode->ResponsibleSectors);

                    $sector = Sector::updateOrCreate(
                        ['name' => $name],
                        [
                            'full_name' => (string) $sectorNode['FullName'],
                            'frequency' => isset($sectorNode['Frequency']) ? (string) $sectorNode['Frequency'] : null,
                            'callsign' => $callsign,
                            'type' => $type,
                            'display_in_sectors_window' => true,
                            'display_in_handoff_window' => ! isset($sectorNode['DisplayInHandoffWindow'])
                                || filter_var((string) $sectorNode['DisplayInHandoffWindow'], FILTER_VALIDATE_BOOLEAN),
                            'responsible_sectors' => $responsibleSectors !== ''
                                ? array_map('trim', explode(',', $responsibleSectors))
                                : null,
                        ]
                    );

                    $volumeNames = array_filter(array_map('trim', explode(',', (string) $sectorNode->Volumes)));

                    // A bare stub (no <Volumes> at all) shouldn't wipe out a
                    // real volume link synced from the other dataset.
                    if ($volumeNames !== []) {
                        $volumeIds = Volume::whereIn('name', $volumeNames)->pluck('id');
                        $sector->volumes()->sync($volumeIds);
                    }

                    $seenNames[] = $name;
                }
            }

            Sector::whereNotIn('name', array_unique($seenNames))->delete();
        });
    }

    private function syncPositions(): void
    {
        DB::transaction(function () {
            $seenNames = [];

            foreach (self::DATASETS as $baseUrl) {
                $xml = $this->fetchXml($baseUrl, 'Positions.xml');

                foreach ($xml->xpath('//Position') as $positionNode) {
                    $parents = $positionNode->xpath('..');
                    $parent = $parents[0] ?? null;
                    $group = ($parent !== null && $parent->getName() === 'Group')
                        ? (string) $parent['Name']
                        : 'Airport';

                    $name = (string) $positionNode['Name'];
                    $center = VatsysCoordinate::decode((string) $positionNode['DefaultCenter']);

                    $position = Position::updateOrCreate(
                        ['name' => $name],
                        [
                            'group' => $group,
                            'type' => (string) $positionNode['Type'],
                            'default_lat' => $center['lat'],
                            'default_lon' => $center['lon'],
                            'default_range' => isset($positionNode['DefaultRange']) ? (float) $positionNode['DefaultRange'] : null,
                            'magnetic_variation' => isset($positionNode['MagneticVariation']) ? (float) $positionNode['MagneticVariation'] : null,
                            'rotation' => isset($positionNode['Rotation']) ? (float) $positionNode['Rotation'] : null,
                            'asmgcs_airport' => isset($positionNode['ASMGCSAirport']) ? (string) $positionNode['ASMGCSAirport'] : null,
                            'visibility_range' => isset($positionNode['VisibilityRange']) ? (float) $positionNode['VisibilityRange'] : null,
                        ]
                    );

                    $sectorNames = isset($positionNode->Sectors)
                        ? array_map(fn (SimpleXMLElement $s) => (string) $s['Name'], iterator_to_array($positionNode->Sectors->Sector, false))
                        : [];

                    if ($sectorNames !== []) {
                        $sectorIds = Sector::whereIn('name', $sectorNames)->pluck('id');
                        $position->sectors()->sync($sectorIds);
                    }

                    $seenNames[] = $name;
                }
            }

            Position::whereNotIn('name', array_unique($seenNames))->delete();
        });
    }

    private function syncAirspace(): void
    {
        DB::transaction(function () {
            $seenRunwayIds = [];
            $seenAirwayNames = [];
            $waypoints = [];

            // Identifiers aren't reliably unique within a single dataset
            // (~36 of ~9,500 collide in Australia's alone), so this is a
            // full delete-and-reinsert rather than upsert-by-name.
            Waypoint::query()->delete();

            foreach (self::DATASETS as $baseUrl) {
                $xml = $this->fetchXml($baseUrl, 'Airspace.xml');

                foreach ($xml->SystemRunways->Airport as $airportNode) {
                    $icao = (string) $airportNode['Name'];

                    foreach ($airportNode->Runway as $runwayNode) {
                        $runway = Runway::updateOrCreate(
                            ['airport_icao' => $icao, 'name' => (string) $runwayNode['Name']],
                            ['data_runway' => (string) $runwayNode['DataRunway']]
                        );

                        $seenRunwayIds[] = $runway->id;

                        $runway->procedures()->delete();

                        $procedures = [];

                        foreach ($runwayNode->SID as $sidNode) {
                            $procedures[] = $this->procedureRow($runway->id, 'SID', $sidNode);
                        }

                        foreach ($runwayNode->STAR as $starNode) {
                            $procedures[] = $this->procedureRow($runway->id, 'STAR', $starNode);
                        }

                        if ($procedures !== []) {
                            Procedure::insert($procedures);
                        }
                    }
                }

                foreach ($xml->Airways->Airway as $airwayNode) {
                    $name = (string) $airwayNode['Name'];

                    $airwayWaypoints = array_values(array_filter(
                        array_map('trim', explode('/', (string) $airwayNode)),
                        fn (string $waypoint) => $waypoint !== ''
                    ));

                    Airway::updateOrCreate(['name' => $name], ['waypoints' => $airwayWaypoints]);

                    $seenAirwayNames[] = $name;
                }

                foreach ($xml->Intersections->Point as $pointNode) {
                    $coordinate = VatsysCoordinate::decode((string) $pointNode);

                    $waypoints[] = [
                        'name' => (string) $pointNode['Name'],
                        'type' => isset($pointNode['Type']) ? (string) $pointNode['Type'] : null,
                        'lat' => $coordinate['lat'],
                        'lon' => $coordinate['lon'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    if (count($waypoints) >= 500) {
                        Waypoint::insert($waypoints);
                        $waypoints = [];
                    }
                }
            }

            if ($waypoints !== []) {
                Waypoint::insert($waypoints);
            }

            Runway::whereNotIn('id', array_unique($seenRunwayIds))->delete();
            Airway::whereNotIn('name', array_unique($seenAirwayNames))->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function procedureRow(int $runwayId, string $type, SimpleXMLElement $node): array
    {
        return [
            'runway_id' => $runwayId,
            'type' => $type,
            'name' => (string) $node['Name'],
            'aircraft_type' => isset($node['Type']) ? (string) $node['Type'] : null,
            'is_default' => isset($node['Default']) && filter_var((string) $node['Default'], FILTER_VALIDATE_BOOLEAN),
            'approach_name' => isset($node['ApproachName']) ? (string) $node['ApproachName'] : null,
            'op_data_flag' => isset($node['OpDataFlag']) ? (string) $node['OpDataFlag'] : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function fetchXml(string $baseUrl, string $filename): SimpleXMLElement
    {
        $client = new Client;
        $response = $client->get($baseUrl.$filename);
        $xml = simplexml_load_string((string) $response->getBody());

        if ($xml === false) {
            throw new RuntimeException("Failed to parse {$filename} from {$baseUrl}.");
        }

        return $xml;
    }
}
