<?php

namespace App\Jobs;

use App\Models\AtisBroadcast;

/**
 * Drops atis_broadcasts rows not updated in 90 minutes - well past when a
 * real ATIS would have changed, so anything still sitting there is an
 * abandoned entry (the broadcasting controller disconnected without the
 * plugin ever sending a final update) rather than current information.
 */
class PruneStaleAtisJob
{
    public function handle(): void
    {
        AtisBroadcast::where('last_seen_at', '<=', now()->subMinutes(90))->delete();
    }
}
