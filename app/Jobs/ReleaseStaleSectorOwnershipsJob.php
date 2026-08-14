<?php

namespace App\Jobs;

use App\Models\SectorOwnership;
use App\Models\SectorOwnershipRequest;
use App\Services\VATSIMClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Releases any sector whose owning controller is no longer online under
 * their claiming callsign - the same disconnect self-heal already applied
 * reactively when someone else tries to claim a stale sector (App\Http\
 * Controllers\SectorOwnershipController::claim), just run proactively so
 * the map and the plugin's Controlled list don't sit on stale ownership
 * between claims.
 *
 * Self-requeues with a 15s delay at the end of every run rather than
 * relying on the scheduler, which can't go below 1-minute granularity.
 * Each run also refreshes a cache heartbeat; a cheap scheduled check (see
 * routes/console.php) restarts the chain if it ever silently stops (e.g.
 * an unhandled failure, or the app/queue worker restarting).
 */
class ReleaseStaleSectorOwnershipsJob implements ShouldQueue
{
    use Queueable;

    private const HEARTBEAT_KEY = 'release-stale-sectors-poll-alive';

    public function handle(): void
    {
        Cache::put(self::HEARTBEAT_KEY, true, 45);

        try {
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
        } finally {
            static::dispatch()->delay(now()->addSeconds(15));
        }
    }

    public static function isRunning(): bool
    {
        return Cache::has(self::HEARTBEAT_KEY);
    }
}
