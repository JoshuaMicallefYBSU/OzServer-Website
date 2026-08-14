<?php

namespace App\Jobs;

use App\Services\VATSIMClient;
use Illuminate\Support\Facades\Storage;

/**
 * Fetches VATSIM's AFV transceiver feed and caches the raw response to
 * storage, so both this site (the map's online-controller frequency list)
 * and external plugins can read it without hitting VATSIM directly.
 *
 * Deliberately not a queued job - this runs on a strict ~15s cadence (see
 * the scheduled loop in routes/console.php), and queue-worker latency
 * would fight that. Called directly, not dispatched.
 */
class AFVTransieversUpdate
{
    public function handle(): void
    {
        $transceivers = (new VATSIMClient)->getAFVTransievers();

        if ($transceivers === null) {
            return;
        }

        Storage::put('afv-transceivers.json', json_encode($transceivers));
    }
}
