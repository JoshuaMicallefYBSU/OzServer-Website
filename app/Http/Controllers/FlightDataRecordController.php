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
     */
    public function update(UpdateFlightDataRecordRequest $request)
    {
        $vatsim = $request->attributes->get('vatsim');

        $data = $request->safe()->except(['controller_cid', 'controller_callsign', 'callsign']);

        $fdr = FlightDataRecord::updateOrCreate(
            ['callsign' => $request->validated('callsign')],
            [
                ...$data,
                'controlling_cid' => $vatsim['cid'],
                'controlling_callsign' => $vatsim['callsign'],
                'last_seen_at' => now(),
            ]
        );

        return response()->json($fdr);
    }
}
