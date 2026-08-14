<?php

namespace App\Jobs;

use App\Models\SectorOwnership;
use App\Models\SectorOwnershipRequest;
use App\Services\VATSIMClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs every minute (see routes/console.php) and releases any sector whose
 * owning controller is no longer online under their claiming callsign -
 * the same disconnect self-heal already applied reactively when someone
 * else tries to claim a stale sector (App\Http\Controllers\
 * SectorOwnershipController::claim), just run proactively so the map and
 * the plugin's Controlled list don't sit on stale ownership between claims.
 */
class ReleaseStaleSectorOwnershipsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
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
