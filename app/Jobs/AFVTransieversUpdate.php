<?php

namespace App\Jobs;

use App\Services\VATSIMClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Fetches VATSIM's AFV transceiver feed and caches the raw response to
 * storage, so both this site (the map's online-controller frequency list)
 * and external plugins can read it without hitting VATSIM directly.
 *
 * Self-requeues with a 15s delay at the end of every run rather than
 * relying on the scheduler, which can't go below 1-minute granularity.
 * Each run also refreshes a cache heartbeat; a cheap scheduled check (see
 * routes/console.php) restarts the chain if it ever silently stops (e.g.
 * an unhandled failure, or the app/queue worker restarting).
 */
class AFVTransieversUpdate implements ShouldQueue
{
    use Queueable;

    private const HEARTBEAT_KEY = 'afv-transceivers-poll-alive';

    public function handle(): void
    {
        Cache::put(self::HEARTBEAT_KEY, true, 45);

        try {
            $transceivers = (new VATSIMClient)->getAFVTransievers();

            if ($transceivers !== null) {
                Storage::put('afv-transceivers.json', json_encode($transceivers));
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
