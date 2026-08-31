<?php

namespace App\Jobs;

use App\Models\SectorOwnershipRequest;

/**
 * Drops rejected sector requests nobody ever came back for.
 *
 * A rejection is kept after the decision purely so the requesting plugin can
 * tell that controller they were denied (SectorOwnershipController::reject),
 * and it is normally deleted the moment they acknowledge it. If they never
 * do - they disconnected before their next poll, or closed vatSys - the row
 * would otherwise sit there forever, and because the table carries a
 * unique(sector_id, requesting_cid) index it would keep taking up that
 * controller's one slot for that sector.
 *
 * request() already supersedes a stale rejection when the same controller
 * asks again, so this is only about not accumulating rows indefinitely; the
 * window can be generous.
 */
class PruneRejectedSectorRequestsJob
{
    private const RETAIN_MINUTES = 30;

    public function handle(): void
    {
        SectorOwnershipRequest::query()
            ->whereNotNull('rejected_at')
            ->where('rejected_at', '<=', now()->subMinutes(self::RETAIN_MINUTES))
            ->delete();
    }
}
