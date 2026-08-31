<?php

namespace App\Jobs;

use App\Models\AtisBroadcast;
use App\Models\FlightDataRecord;
use App\Models\SectorOwnershipRequest;

/**
 * Sweeps every table that just accumulates abandoned rows over time, in one
 * scheduler tick rather than a separate job process for each - none of these
 * is enough work on its own to justify booting a whole process every five
 * minutes.
 */
class PruneStaleDataJob
{
    /**
     * A flight not updated in this long - regardless of state, including
     * STATE_FINISHED - is abandoned: the plugin crashed, disconnected, or
     * otherwise stopped reporting it, not still in progress.
     */
    private const FDR_RETAIN_MINUTES = 10;

    /**
     * Well past when a real ATIS would have changed, so anything still
     * sitting there is an abandoned entry (the broadcasting controller
     * disconnected without the plugin ever sending a final update) rather
     * than current information.
     */
    private const ATIS_RETAIN_MINUTES = 90;

    /**
     * A rejection is kept after the decision purely so the requesting plugin
     * can tell that controller they were denied
     * (SectorOwnershipController::reject), and is normally deleted the
     * moment they acknowledge it. If they never do, this is what stops the
     * row sitting there forever and permanently occupying that controller's
     * one slot for that sector (unique(sector_id, requesting_cid)).
     */
    private const REJECTED_REQUEST_RETAIN_MINUTES = 30;

    public function handle(): void
    {
        FlightDataRecord::where('last_seen_at', '<=', now()->subMinutes(self::FDR_RETAIN_MINUTES))->delete();

        AtisBroadcast::where('last_seen_at', '<=', now()->subMinutes(self::ATIS_RETAIN_MINUTES))->delete();

        SectorOwnershipRequest::query()
            ->whereNotNull('rejected_at')
            ->where('rejected_at', '<=', now()->subMinutes(self::REJECTED_REQUEST_RETAIN_MINUTES))
            ->delete();
    }
}
