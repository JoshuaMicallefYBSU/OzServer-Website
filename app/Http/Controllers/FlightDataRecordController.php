<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateFlightDataRecordRequest;
use App\Models\FlightDataRecord;

class FlightDataRecordController extends Controller
{
    /**
     * Upsert a flight's FDR/position data, pushed by the vatSys plugin.
     * Handles both full FDR pushes and position-only pings - same row
     * either way, keyed by callsign.
     *
     * controlling_cid/controlling_callsign (datalink authority - who owns
     * this flight) come from the request body, not from the submitter's own
     * identity (controller_cid/controller_callsign, used only for auth by
     * plugin.token) - any controller observing a flight can push its data,
     * attributing authority to whoever the plugin says actually has it.
     */
    public function update(UpdateFlightDataRecordRequest $request)
    {
        $data = $request->safe()->except(['controller_cid', 'controller_callsign', 'callsign']);

        $fdr = FlightDataRecord::updateOrCreate(
            ['callsign' => $request->validated('callsign')],
            [
                ...$data,
                'last_seen_at' => now(),
            ]
        );

        return response()->json($fdr);
    }
}
