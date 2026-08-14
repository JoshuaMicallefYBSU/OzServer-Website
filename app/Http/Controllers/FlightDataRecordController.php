<?php

namespace App\Http\Controllers;

use App\Http\Requests\BatchUpdateFlightDataRecordRequest;
use App\Http\Requests\UpdateFlightDataRecordRequest;
use App\Models\FlightDataRecord;
use App\Models\SectorOwnership;
use App\Services\VATSIMClient;

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
        $data = $request->safe()->except(['controller_cid', 'controller_callsign']);

        $fdr = $this->upsert($vatsim, $data);

        if ($fdr === null) {
            return response()->json([
                'message' => "Only this flight's current authority controller may update it right now.",
            ], 403);
        }

        return response()->json($fdr);
    }

    /**
     * Same as update(), but for every flight the plugin currently knows
     * about in one request rather than one HTTP call per aircraft. Each
     * flight succeeds or is blocked independently (see upsert()) - one
     * flight's authority controller being online with sectors doesn't stop
     * every other flight in the same batch from updating normally.
     */
    public function batchUpdate(BatchUpdateFlightDataRecordRequest $request)
    {
        $vatsim = $request->attributes->get('vatsim');

        $results = collect($request->validated('flights'))->map(function (array $flight) use ($vatsim) {
            $fdr = $this->upsert($vatsim, $flight);

            return [
                'callsign' => $flight['callsign'],
                'updated' => $fdr !== null,
            ];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Upserts one flight's FDR/position data if the caller is allowed to
     * right now - null means blocked, not written.
     *
     * Blocked only when all of: the flight already has a recorded datalink
     * authority (controlling_cid) that isn't this caller; that authority
     * controller is currently online on VATSIM; and they currently hold at
     * least one OzServer sector. That combination means someone is actively
     * working this flight right now, so nobody else's plugin should be able
     * to overwrite their data underneath them. Without all three - no
     * recorded authority yet (free), the authority controller has
     * disconnected, or they aren't holding any sector - anyone may write,
     * same as before this check existed.
     */
    private function upsert(array $vatsim, array $data): ?FlightDataRecord
    {
        $callsign = $data['callsign'];
        $existing = FlightDataRecord::where('callsign', $callsign)->first();

        if ($existing !== null && $existing->controlling_cid !== null && $existing->controlling_cid !== $vatsim['cid']) {
            $authorityOnline = (new VATSIMClient)->searchCallsign($existing->controlling_callsign, true) !== null;
            $authorityHasSectors = SectorOwnership::where('controller_cid', $existing->controlling_cid)->exists();

            if ($authorityOnline && $authorityHasSectors) {
                return null;
            }
        }

        return FlightDataRecord::updateOrCreate(
            ['callsign' => $callsign],
            [
                ...collect($data)->except('callsign')->all(),
                'last_seen_at' => now(),
            ]
        );
    }
}
