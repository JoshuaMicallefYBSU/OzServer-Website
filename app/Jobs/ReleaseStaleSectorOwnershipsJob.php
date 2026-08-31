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
 * Runs once per minute and returns immediately.
 *
 * It used to loop four passes ~15s apart inside handle(), sleeping between
 * them, to get a sub-minute cadence out of a minute-granularity cron. That
 * kept a PHP process (and its database connection) alive for roughly 45
 * seconds of every 60 - alongside AFVTransieversUpdate doing the same thing
 * - which is a lot of a small host's workers to hold open permanently, and
 * it re-stamped every online owner's last_seen_online_at four times a minute
 * instead of once.
 *
 * That cadence bought nothing once ownership gained a disconnect grace:
 * SectorOwnership::DISCONNECT_GRACE_MINUTES is five minutes, so noticing a
 * departure at 15s versus 60s granularity cannot change any outcome. The
 * reactive path in SectorOwnershipController::claim already covers the case
 * where someone actively wants a stale sector before this job gets to it.
 */
class ReleaseStaleSectorOwnershipsJob
{
    public function handle(): void
    {
        $this->releaseStale();
    }

    private function releaseStale(): void
    {
        $vatsim = new VATSIMClient;

        // Can't confirm anyone's actually offline right now - skip this pass
        // rather than risk treating a transient VATSIM outage as every
        // controller network-wide having disconnected at once.
        $data = $vatsim->getCurrentData();

        if ($data === null || ! isset($data->controllers)) {
            return;
        }

        $owners = SectorOwnership::query()
            ->select('controller_cid', 'controller_callsign')
            ->distinct()
            ->get();

        foreach ($owners as $owner) {
            $ownerOnline = $vatsim->isControllerOnline($owner->controller_callsign, $owner->controller_cid);

            $ownerships = SectorOwnership::where('controller_cid', $owner->controller_cid)
                ->where('controller_callsign', $owner->controller_callsign);

            if ($ownerOnline) {
                // Seen online: stamp them, which is what the grace period below
                // and claim()'s takeover check are both measured from.
                (clone $ownerships)->update(['last_seen_online_at' => now()]);

                continue;
            }

            // Absent from the datafeed. That is not the same as having left:
            // a clean exit releases its sectors explicitly (releaseAll) and so
            // never gets here at all, which means anything reaching this point
            // is either a crash, a dropped connection, or a controller the feed
            // simply has not caught up with yet. All three want the same thing -
            // hold the sectors long enough to come back to them.
            $cutoff = now()->subMinutes(SectorOwnership::DISCONNECT_GRACE_MINUTES);

            $sectorIds = (clone $ownerships)
                ->where(fn ($query) => $query
                    ->where('last_seen_online_at', '<=', $cutoff)
                    ->orWhere(fn ($fallback) => $fallback
                        ->whereNull('last_seen_online_at')
                        ->where('created_at', '<=', $cutoff)))
                ->pluck('sector_id');

            if ($sectorIds->isEmpty()) {
                continue;
            }

            SectorOwnership::whereIn('sector_id', $sectorIds)
                ->where('controller_cid', $owner->controller_cid)
                ->where('controller_callsign', $owner->controller_callsign)
                ->delete();

            // Nothing left to accept/reject once the sector's unowned.
            SectorOwnershipRequest::whereIn('sector_id', $sectorIds)->delete();
        }
    }
}
