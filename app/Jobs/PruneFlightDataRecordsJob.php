<?php

namespace App\Jobs;

use App\Models\FlightDataRecord;

/**
 * Prunes flight_data_records that are no longer relevant: flights marked
 * STATE_FINISHED are kept for 10 minutes (enough time for the map/plugin
 * to reflect the final state) then dropped, and anything not updated at
 * all in 2 hours is dropped regardless of state - abandoned, e.g. the
 * plugin crashed or the flight vanished without ever being marked
 * finished.
 */
class PruneFlightDataRecordsJob
{
    public function handle(): void
    {
        FlightDataRecord::where(function ($query) {
            $query->where('state', 'STATE_FINISHED')
                ->where('last_seen_at', '<=', now()->subMinutes(10));
        })->orWhere('last_seen_at', '<=', now()->subHours(2))->delete();
    }
}
