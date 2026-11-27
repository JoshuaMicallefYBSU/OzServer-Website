<?php

namespace App\Http\Controllers;

use App\Support\ChartViews;
use App\Support\PublicUrl;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HomeController extends Controller
{
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
        // One chart view per region (App\Support\ChartViews), each with the outlines of its own
        // CTR sectors in one path - drawn alike, and one element costs the browser less than
        // seventy - and its coastline.
        $shapes = $this->sectorShapes();
        $views = [];
        foreach (ChartViews::ALL as $region => $view) {
            $views[$region] = [
                'label' => $view['label'],
                'airspace' => $view['airspace'],
                'projection' => ChartViews::projection($view),
                'viewBox' => implode(' ', $view['viewBox']),
                'narrow' => implode(' ', $view['narrow']),
                'extent' => $view['extent'],
                'sectorsPath' => $shapes
                    ->filter(fn (array $sector) => ($sector['type'] ?? null) === 'CTR'
                        && ($sector['region'] ?? 'australia') === $region
                        && ! in_array($sector['name'], $view['exclude'], true)
                        && (! $view['clip'] || $this->withinView($view, $sector)))
                    ->map(fn (array $sector) => $this->sectorPath($view, $sector))
                    ->filter()
                    ->implode(''),
                'coast' => $this->coast($view['coast']),
                'graticule' => $this->graticule($view),
            ];
        }

        return view('landing', [
            'views' => $views,
            // What the page's script needs of each view; the paths are in its markup already.
            'chartViews' => collect($views)->map(fn (array $view) => Arr::only($view, ['label', 'airspace', 'projection', 'viewBox', 'narrow', 'extent'])),
            'windows' => $this->windows(),
        ]);
    }

    /**
     * Whether every point of a sector's outline is in (or within a couple of degrees of) the view.
     *
     * @param  array<string, mixed>  $sector
     */
    private function withinView(array $view, array $sector): bool
    {
        $points = collect($sector['boundary'] ?? [])->flatten(1)->filter(fn ($point) => is_array($point));

        return $points->isNotEmpty()
            && $points->every(fn (array $point) => ChartViews::inExtent($view, (float) $point['lat'], (float) $point['lon'], 2.0));
    }

    /**
     * A line every ten degrees, well past the view's own edges: on a wide screen the ocean runs on
     * to the hero's.
     */
    private function graticule(array $view): string
    {
        $path = '';
        $from = (int) (floor(($view['lon0'] - 100) / 10) * 10);
        foreach (range($from, $from + 260, 10) as $lon) {
            $x = round(($lon - $view['lon0']) * cos(deg2rad($view['parallel'])) * 20, 1);
            $path .= "M{$x} -3000V3900";
        }
        $lat0 = (int) (floor($view['lat0'] / 10) * 10);
        foreach (range($lat0 + 50, $lat0 - 110, -10) as $lat) {
            $y = round(ChartViews::projectY($view, $lat), 1);
            $path .= "M-4000 {$y}H5400";
        }

        return $path;
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
     * A view's coastline, already in its projection (see App\Support\ChartViews): `land` filled,
     * `shore` only stroked. Australia's .txt is one land path, taken from the VATPAC vatSys
     * dataset's COAST_ALL map and simplified; the .json ones are built by `php artisan
     * landing:coast`. They change only if regenerated, so each is read once and kept.
     *
     * @return array{land: string, shore: string}
     */
    private function coast(string $file): array
    {
        $path = resource_path('data/'.$file);

        return Cache::rememberForever('landing-coast-'.$file.'-'.(@filemtime($path) ?: 0), function () use ($path, $file) {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                return ['land' => '', 'shore' => ''];
            }
            if (str_ends_with($file, '.json')) {
                $decoded = json_decode($contents, true) ?: [];

                return ['land' => (string) ($decoded['land'] ?? ''), 'shore' => (string) ($decoded['shore'] ?? '')];
            }

            return ['land' => trim($contents), 'shore' => ''];
        });
    }

    /**
     * @param  array<string, mixed>  $sector
     */
    private function sectorPath(array $view, array $sector): ?string
    {
        $rings = collect($sector['boundary'] ?? [])->filter(fn ($ring) => is_array($ring) && count($ring) > 2);

        return $rings->isEmpty() ? null : $rings->map(fn (array $ring) => $this->ringToPath($view, $ring))->implode('');
    }

    private function ringToPath(array $view, array $ring): string
    {
        return collect($ring)
            ->map(function (array $point, int $index) use ($view) {
                $x = round(ChartViews::projectX($view, (float) $point['lon']), 1);
                $y = round(ChartViews::projectY($view, (float) $point['lat']), 1);

                return ($index === 0 ? 'M' : 'L')."{$x} {$y}";
            })
            ->implode('').'Z';
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
