<?php

namespace App\Http\Controllers;

use App\Support\PublicUrl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
    /**
     * A handful of oceanic CTR sectors (Norfolk, Solomon Sea, Fiji boundary areas) extend thousands
     * of miles out over the Pacific, far past the edge of the homepage's chart, so they are left
     * out of it.
     */
    private const EXCLUDED_SECTORS = ['HWE', 'AYPM', 'NFFJ'];

    /**
     * The chart's projection: longitude and latitude to the chart's own units, 20 to a degree of
     * latitude, with longitude narrowed for 26 S so that Australia keeps its shape. The origin is
     * out in the Indian Ocean, north-west of the continent.
     *
     * The page projects the live sectors and aircraft itself with the same four numbers (passed
     * to it as `projection`), and resources/data/coast-australia.txt is drawn with them too.
     */
    private const LON_ORIGIN = 84.0;

    private const LAT_ORIGIN = -3.0;

    private const UNITS_PER_DEGREE = 20.0;

    private const STANDARD_PARALLEL = 26.0;

    /**
     * The plugin's windows shown on the homepage, in order. Each picture is the window itself,
     * rendered by the plugin repository's tools/WindowShots; one that is missing is left out.
     */
    private const WINDOWS = [
        [
            'file' => 'sectors-window.png', 'tab' => 'Sectors window', 'caption' => 'Settings, Sectors',
            'title' => 'See what you own and what is free',
            'text' => 'Owned on the left, the changes you are planning in the middle, and everything available or held by someone else on the right. Nothing is sent until you press Apply Changes.',
            'alt' => 'The OzServer Sector Configuration Window in vatSys, with Benalla owned and the list of available centre sectors.',
        ],
        [
            'file' => 'request-prompt.png', 'tab' => 'Ask for a sector', 'caption' => 'After transmitting on a held sector',
            'title' => 'Ask before you take',
            'text' => 'Press Transmit on a line someone else is working and vatSys asks whether to send them a request. The sector stays theirs until they answer.',
            'alt' => 'The Sector already owned prompt: Yarrowee is held by ML-MUN_CTR, with Accept and Reject buttons.',
        ],
        [
            'file' => 'incoming-request.png', 'tab' => 'Answer a request', 'caption' => 'Release Sector Jurisdiction',
            'title' => 'Hand over with one press',
            'text' => 'A request for your airspace appears under Release Sector Jurisdiction, and the sector it covers is shaded yellow on your scope. Accept hands over the sector and the aircraft in it.',
            'alt' => 'The Sectors window with a request from ML-ELW_CTR for Eildon Weir selected, and Accept and Reject buttons.',
        ],
        [
            'file' => 'take-sectors-disconnected.png', 'tab' => 'Cover a dropped controller', 'caption' => 'A controller who dropped',
            'title' => 'A crash does not close the airspace',
            'text' => 'When a controller disconnects without releasing, their sectors are kept for two minutes. You are told whose they are and until when, and can take them now if traffic needs it.',
            'alt' => 'The Sectors held for a disconnected controller prompt, with Take Sectors and Leave buttons.',
        ],
        [
            'file' => 'arrival-list.png', 'tab' => 'Arrival list', 'caption' => 'A:YSSY List',
            'title' => 'One sequence, shared',
            'text' => 'Every controller with an airport\'s list open sees the same arrival order. Callsigns take the colour of the track on your own scope, and the flow position decides who may reorder.',
            'alt' => 'The shared arrival list for Sydney with eight flights, three controllers connected and the flow position online.',
        ],
        [
            'file' => 'update-window.png', 'tab' => 'Updates', 'caption' => 'OzServer update',
            'title' => 'It keeps itself current',
            'text' => 'New versions download in the background. Restart applies one, and your frequencies, sectors and aircraft tags are kept for when you reconnect.',
            'alt' => 'The OzServer update window with its Restart button.',
        ],
    ];

    public function index()
    {
        // Australian airspace only: the API serves other regions' sectors too (Mexico), which fall
        // far outside the chart and would be embedded in the page unseen.
        // One path for all of them: they are drawn alike, and one element costs the browser less
        // than seventy.
        $sectorsPath = $this->sectorShapes()
            ->filter(fn (array $sector) => ($sector['type'] ?? null) === 'CTR'
                && ($sector['region'] ?? 'australia') === 'australia'
                && ! in_array($sector['name'], self::EXCLUDED_SECTORS, true))
            ->map(fn (array $sector) => $this->sectorPath($sector))
            ->filter()
            ->implode('');

        return view('landing', [
            'sectorsPath' => $sectorsPath,
            'coastPath' => $this->coastPath(),
            'projection' => [
                'lon0' => self::LON_ORIGIN,
                'lat0' => self::LAT_ORIGIN,
                'k' => self::UNITS_PER_DEGREE,
                'c' => cos(deg2rad(self::STANDARD_PARALLEL)),
            ],
            'windows' => $this->windows(),
        ]);
    }

    /**
     * Every sector's static geometry, from OzServer-API's public /map/sector-shapes. Who holds
     * which of them is read by the page itself, from the live feed.
     *
     * Cached for a day on success, since the underlying dataset only changes when OzServer-API's
     * own daily sync runs. Only a minute on failure or an empty answer, so a transient API outage
     * heals quickly instead of leaving the chart without its sectors for the rest of the day.
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
     * Australia's coastline as one SVG path, already in the chart's projection. Taken from the
     * VATPAC vatSys dataset's COAST_ALL map and simplified; it changes only if that file is
     * regenerated, so it is read once and kept.
     */
    private function coastPath(): string
    {
        return Cache::rememberForever('landing-coast-'.(@filemtime(resource_path('data/coast-australia.txt')) ?: 0), function () {
            $path = @file_get_contents(resource_path('data/coast-australia.txt'));

            return $path === false ? '' : trim($path);
        });
    }

    /**
     * @param  array<string, mixed>  $sector
     */
    private function sectorPath(array $sector): ?string
    {
        $rings = collect($sector['boundary'] ?? [])->filter(fn ($ring) => is_array($ring) && count($ring) > 2);

        return $rings->isEmpty() ? null : $rings->map(fn (array $ring) => $this->ringToPath($ring))->implode('');
    }

    private function ringToPath(array $ring): string
    {
        return collect($ring)
            ->map(function (array $point, int $index) {
                $x = round($this->projectX((float) $point['lon']), 1);
                $y = round($this->projectY((float) $point['lat']), 1);

                return ($index === 0 ? 'M' : 'L')."{$x} {$y}";
            })
            ->implode('').'Z';
    }

    private function projectX(float $lon): float
    {
        return ($lon - self::LON_ORIGIN) * cos(deg2rad(self::STANDARD_PARALLEL)) * self::UNITS_PER_DEGREE;
    }

    private function projectY(float $lat): float
    {
        return (self::LAT_ORIGIN - $lat) * self::UNITS_PER_DEGREE;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function windows(): array
    {
        $windows = [];

        foreach (self::WINDOWS as $window) {
            $relative = 'images/how-to/'.$window['file'];
            $src = PublicUrl::file($relative);
            $size = $src === null ? false : @getimagesize(public_path($relative));

            if ($src === null || $size === false) {
                continue;
            }

            unset($window['file']);
            $windows[] = $window + ['src' => $src, 'w' => $size[0], 'h' => $size[1]];
        }

        return $windows;
    }
}
