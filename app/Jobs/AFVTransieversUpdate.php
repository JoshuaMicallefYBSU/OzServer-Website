<?php

namespace App\Jobs;

use App\Services\VATSIMClient;
use Illuminate\Support\Facades\Storage;

/**
 * Fetches VATSIM's AFV transceiver feed and caches the raw response to
 * storage every ~15s, so both this site (the map's online-controller
 * frequency list) and external plugins can read it without hitting VATSIM
 * directly.
 *
 * Laravel's scheduler has no sub-minute frequency (no ->everyFifteenSeconds()
 * - cron itself is minute-granularity), and this environment only runs
 * `schedule:run` on a cron trigger - no persistent queue worker - so a
 * self-requeuing queued job has nothing to pick it up. Instead, the 15s
 * cadence is looped *inside* handle() itself: 4 passes ~15s apart (~45s
 * total), triggered once per minute via `Schedule::job(new
 * AFVTransieversUpdate)->everyMinute()` in routes/console.php. Bounded, so
 * it always finishes and returns well inside the next minute's tick - no
 * risk of piling up overlapping schedule:run processes.
 */
class AFVTransieversUpdate
{
    private const PASSES = 4;

    private const INTERVAL_SECONDS = 15;

    public function handle(): void
    {
        for ($i = 0; $i < self::PASSES; $i++) {
            $transceivers = (new VATSIMClient)->getAFVTransievers();

            if ($transceivers !== null) {
                Storage::put('afv-transceivers.json', json_encode($transceivers));
            }

            if ($i < self::PASSES - 1) {
                sleep(self::INTERVAL_SECONDS);
            }
        }
    }
}
