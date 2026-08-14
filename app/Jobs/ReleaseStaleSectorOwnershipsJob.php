<?php

namespace App\Jobs;

use App\Models\SectorOwnership;
use App\Models\SectorOwnershipRequest;
use App\Services\VATSIMClient;

/**
 * Releases any sector whose owning controller is no longer online under
 * their claiming callsign - the same disconnect self-heal already applied
 * reactively when someone else tries to claim a stale sector (App\Http\
 * Controllers\SectorOwnershipController::claim), just run proactively so
 * the map and the plugin's Controlled list don't sit on stale ownership
 * between claims.
 *
 * Laravel's scheduler has no sub-minute frequency (no ->everyFifteenSeconds()
 * - cron itself is minute-granularity), and this environment only runs
 * `schedule:run` on a cron trigger - no persistent queue worker - so a
 * self-requeuing queued job has nothing to pick it up. Instead, the 15s
 * cadence is looped *inside* handle() itself: 4 passes ~15s apart (~45s
 * total), triggered once per minute via `Schedule::job(new
 * ReleaseStaleSectorOwnershipsJob)->everyMinute()` in routes/console.php.
 * Bounded, so it always finishes and returns well inside the next minute's
 * tick - no risk of piling up overlapping schedule:run processes.
 */
class ReleaseStaleSectorOwnershipsJob
{
    private const PASSES = 4;

    private const INTERVAL_SECONDS = 15;

    public function handle(): void
    {
        for ($i = 0; $i < self::PASSES; $i++) {
            $this->releaseStale();

            if ($i < self::PASSES - 1) {
                sleep(self::INTERVAL_SECONDS);
            }
        }
    }

    private function releaseStale(): void
    {
        $owners = SectorOwnership::query()
            ->select('controller_cid', 'controller_callsign')
            ->distinct()
            ->get();

        foreach ($owners as $owner) {
            $stillOnline = (new VATSIMClient)->searchCallsign($owner->controller_callsign, true);

            if ($stillOnline !== null && (int) $stillOnline->cid === $owner->controller_cid) {
                continue;
            }

            $sectorIds = SectorOwnership::where('controller_cid', $owner->controller_cid)
                ->where('controller_callsign', $owner->controller_callsign)
                ->pluck('sector_id');

            SectorOwnership::whereIn('sector_id', $sectorIds)
                ->where('controller_cid', $owner->controller_cid)
                ->where('controller_callsign', $owner->controller_callsign)
                ->delete();

            // Nothing left to accept/reject once the sector's unowned.
            SectorOwnershipRequest::whereIn('sector_id', $sectorIds)->delete();
        }
    }
}
