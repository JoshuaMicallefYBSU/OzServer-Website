<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How often the public pages are looked at: one row per visit - the page and when, nothing about
 * who - written by App\Http\Middleware\RecordPageVisit, read by the /console.
 *
 * A visit is the first request for a page in an hour from one browser (the middleware's cookie), so
 * reloads and the live map's own refreshes are not counted again.
 *
 * The table makes itself: the site is deployed by copying files, with no migration step.
 */
class PageVisits
{
    public const PAGES = ['home' => 'Homepage', 'how-to' => 'How-To', 'map' => 'Live map'];

    private const TABLE = 'page_visits';

    /** Rows older than this are deleted now and then. */
    private const KEEP_DAYS = 400;

    public static function record(string $page): void
    {
        self::ensureTable();
        DB::table(self::TABLE)->insert(['page' => $page, 'visited_at' => now()]);

        // About one visit in two hundred clears out the oldest rows.
        if (random_int(1, 200) === 1) {
            DB::table(self::TABLE)->where('visited_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
        }
    }

    /**
     * Visits per page over the last day, week and month, and per day (UTC) for the last 14 days.
     *
     * @return array{pages: array<string, string>, totals: array<string, array<string, int>>, days: list<array{date: string, counts: array<string, int>}>}
     */
    public static function summary(): array
    {
        self::ensureTable();
        $now = now();
        $count = fn (Carbon $since) => DB::table(self::TABLE)->where('visited_at', '>=', $since)
            ->selectRaw('page, count(*) as n')->groupBy('page')->pluck('n', 'page')->map(fn ($n) => (int) $n)->all();

        $totals = [
            'hour' => $count($now->copy()->subHour()),
            'day' => $count($now->copy()->subDay()),
            'week' => $count($now->copy()->subWeek()),
            'month' => $count($now->copy()->subDays(30)),
        ];

        $from = $now->copy()->utc()->startOfDay()->subDays(13);
        $byDay = [];
        foreach (DB::table(self::TABLE)->where('visited_at', '>=', $from)->get(['page', 'visited_at']) as $row) {
            $date = Carbon::parse($row->visited_at)->utc()->toDateString();
            $byDay[$date][$row->page] = ($byDay[$date][$row->page] ?? 0) + 1;
        }
        $days = [];
        for ($day = $from->copy(); $day <= $now; $day->addDay()) {
            $days[] = ['date' => $day->toDateString(), 'counts' => $byDay[$day->toDateString()] ?? []];
        }

        return ['pages' => self::PAGES, 'totals' => $totals, 'days' => $days];
    }

    private static function ensureTable(): void
    {
        Cache::rememberForever('page-visits-table', function () {
            if (! Schema::hasTable(self::TABLE)) {
                Schema::create(self::TABLE, function (Blueprint $table) {
                    $table->id();
                    $table->string('page', 16);
                    $table->timestamp('visited_at')->index();
                });
            }

            return true;
        });
    }
}
