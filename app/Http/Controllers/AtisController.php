<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAtisRequest;
use App\Models\AtisBroadcast;

class AtisController extends Controller
{
    /**
     * Upsert one airport's current ATIS, keyed by icao. Pushed by the
     * vatSys plugin only when the broadcast actually changes (a new letter/
     * content) - never a periodic heartbeat - so every call here represents
     * a real update, not a liveness ping. Staleness is handled separately,
     * by PruneStaleDataJob dropping rows whose last_seen_at falls too far
     * behind, not by anything in this endpoint.
     */
    public function update(UpdateAtisRequest $request)
    {
        $data = $request->safe()->except(['controller_cid', 'controller_callsign']);

        $atis = AtisBroadcast::updateOrCreate(
            ['icao' => $data['icao']],
            [
                ...collect($data)->except('icao')->all(),
                'last_seen_at' => now(),
            ]
        );

        return response()->json($atis);
    }

    /**
     * The current ATIS for a single airport, or null if none is on file
     * (nothing broadcast yet, or it's since expired via PruneStaleDataJob).
     */
    public function show(string $icao)
    {
        return response()->json(
            AtisBroadcast::where('icao', strtoupper($icao))->first()
        );
    }
}
