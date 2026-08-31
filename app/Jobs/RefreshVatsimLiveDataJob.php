<?php

namespace App\Jobs;

use App\Models\FlightDataRecord;
use App\Models\SectorOwnership;
use App\Models\SectorOwnershipRequest;
use App\Services\VATSIMClient;
use Illuminate\Support\Facades\Storage;

/**
 * The two things this site pulls from VATSIM every minute, combined into one
 * job so the scheduler forks a single process for them instead of two:
 *
 * - The AFV transceiver feed, cached to storage at ~15s resolution for the
 *   map's online-controller frequency list and for external plugins
 *   (App\Http\Controllers\AfvTransceiverController). Laravel's scheduler has
 *   no sub-minute frequency, so the 15s cadence is looped *inside* handle()
 *   itself: 4 passes ~15s apart (~45s total), triggered once a minute.
 *
 * - Releasing any sector whose owning controller is no longer online under
 *   their claiming callsign, at ordinary once-a-minute resolution - the same
 *   disconnect self-heal already applied reactively when someone else tries
 *   to claim a stale sector (SectorOwnershipController::claim), just run
 *   proactively so the map and the plugin's Controlled list don't sit on
 *   stale ownership between claims. SectorOwnership::DISCONNECT_GRACE_MINUTES
 *   is measured in whole minutes, so there is nothing for the 15s cadence
 *   above to buy this half - it runs once per invocation, not once per pass.
 */
class RefreshVatsimLiveDataJob
{
    private const AFV_PASSES = 4;

    private const AFV_INTERVAL_SECONDS = 15;

    public function handle(): void
    {
        $vatsim = new VATSIMClient;

        $this->releaseStaleOwnerships($vatsim);

        for ($i = 0; $i < self::AFV_PASSES; $i++) {
            $transceivers = $vatsim->getAFVTransievers();

            if ($transceivers !== null) {
                Storage::put('afv-transceivers.json', json_encode($transceivers));
            }

            if ($i < self::AFV_PASSES - 1) {
                sleep(self::AFV_INTERVAL_SECONDS);
            }
        }
    }

    private function releaseStaleOwnerships(VATSIMClient $vatsim): void
    {
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

            // Nothing left to accept/reject once the sector's unowned, and this
            // controller isn't coming back to act on anything they'd requested
            // from someone else either - mirrors releaseAll()'s cleanup for a
            // graceful disconnect, just triggered by the feed going quiet
            // instead of the plugin telling us directly.
            SectorOwnershipRequest::whereIn('sector_id', $sectorIds)->delete();
            SectorOwnershipRequest::where('requesting_cid', $owner->controller_cid)->delete();

            // $sectorIds above is every stale sector under this owner's
            // (cid, callsign) identity, so this owner now holds zero -
            // release any flight still attributing authority to them too.
            FlightDataRecord::releaseAuthorityFor($owner->controller_cid);
        }
    }
}
