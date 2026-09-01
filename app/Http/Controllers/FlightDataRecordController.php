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
     * either way, keyed by callsign. Routed through upsertMany() (a batch
     * of exactly one) rather than its own standalone path, so the two
     * endpoints can never drift on what "allowed to write" means - see
     * upsertMany()'s own comment.
     */
    public function update(UpdateFlightDataRecordRequest $request)
    {
        $data = $request->safe()->except(['controller_cid', 'controller_callsign']);
        $vatsim = $request->attributes->get('vatsim');

        $updated = $this->upsertMany($vatsim, [$data]);

        if (! $updated[$data['callsign']]) {
            return response()->json([
                'message' => "Only this flight's current authority controller may update it right now.",
            ], 403);
        }

        return response()->json(FlightDataRecord::where('callsign', $data['callsign'])->first());
    }

    /**
     * Every FDR row OzServer currently holds, for the plugin's own pull
     * side (FdrActivationSync) - the counterpart to update()/batchUpdate()
     * pushing data up. No staleness filter is needed here: PruneStaleDataJob
     * already deletes any row not touched in FDR_RETAIN_MINUTES, so every
     * row returned is already live. Deliberately dumb - this endpoint
     * doesn't know or care what vatSys's own FDRStates ordering means
     * (e.g. which values count as "activated"); that judgement is made
     * client-side, in FdrActivationSync.
     */
    public function sync()
    {
        return response()->json(FlightDataRecord::all());
    }

    /**
     * Same as update(), but for every flight the plugin currently knows
     * about in one request rather than one HTTP call per aircraft. Each
     * flight succeeds or is blocked independently (see upsertMany()) - one
     * flight's authority controller being online with sectors doesn't stop
     * every other flight in the same batch from updating normally.
     */
    public function batchUpdate(BatchUpdateFlightDataRecordRequest $request)
    {
        $flights = $request->validated('flights');
        $vatsim = $request->attributes->get('vatsim');

        $updated = $this->upsertMany($vatsim, $flights);

        $results = collect($flights)->map(fn (array $flight) => [
            'callsign' => $flight['callsign'],
            'updated' => $updated[$flight['callsign']],
        ]);

        return response()->json(['results' => $results]);
    }

    /**
     * Upserts every flight in $flights in one pass, doing the checks each
     * flight needs against its recorded authority ONCE for the whole
     * batch rather than once per flight.
     *
     * The old shape (a loop calling a per-flight upsert()) issued, per
     * flight: a SELECT to find the existing row, a VATSIMClient datafeed
     * scan and a SectorOwnership::exists() query when its authority
     * didn't match the caller, and updateOrCreate()'s own internal
     * SELECT-then-write. A busy controller's five-second /fdr/batch push
     * can easily carry several dozen flights, which turned into several
     * dozen avoidable round trips *per push*, from *every* connected
     * controller, every five seconds - the actual source of the server
     * lag/gateway 503s under load this was rewritten to fix, not the
     * plugin's own batching (it already sends one request per flush).
     * update() now pays this same fixed cost too (a batch of one), but
     * that's one query where it used to be several, not several where it
     * used to be one.
     *
     * Blocked (not written) only when all of: the flight already has a
     * recorded datalink authority (controlling_cid) that isn't this
     * caller; that authority controller is currently online on VATSIM;
     * and they currently hold at least one OzServer sector. That
     * combination means someone is actively working this flight right
     * now, so nobody else's plugin should be able to overwrite their data
     * underneath them. Without all three - no recorded authority yet
     * (free), the authority controller has disconnected, or they aren't
     * holding any sector - anyone may write, same as before this check
     * existed.
     *
     * @param  array<int, array<string, mixed>>  $flights
     * @return array<string, bool> callsign => whether it was written
     */
    private function upsertMany(array $vatsim, array $flights): array
    {
        $callsigns = array_column($flights, 'callsign');

        $existingByCallsign = FlightDataRecord::whereIn('callsign', $callsigns)
            ->get(['id', 'callsign', 'controlling_cid', 'controlling_callsign'])
            ->keyBy('callsign');

        // Both fetched once for the whole batch, not once per flight whose recorded authority
        // isn't this caller - see the class comment. Neither depends on which flight is being
        // checked, only on who currently holds sectors and who's currently online.
        $cidsWithSectors = SectorOwnership::pluck('controller_cid')->all();
        $vatsimClient = new VATSIMClient;

        $now = now();
        $results = [];

        foreach ($flights as $flight) {
            $callsign = $flight['callsign'];
            $existing = $existingByCallsign->get($callsign);

            if ($existing !== null && $existing->controlling_cid !== null && $existing->controlling_cid !== $vatsim['cid']) {
                // isControllerOnline, not searchCallsign: the latter pulls the whole multi-megabyte
                // datafeed back out of the cache, unserialises it and scans its controllers array
                // linearly - per call, still once per flight even after this loop stopped repeating
                // everything else. The lookup map is built at most once per request (see
                // VATSIMClient::onlineControllers), so a fifty-flight batch costs one table rather
                // than fifty scans.
                $authorityOnline = $vatsimClient->isControllerOnline($existing->controlling_callsign);
                $authorityHasSectors = in_array((int) $existing->controlling_cid, $cidsWithSectors, true);

                if ($authorityOnline && $authorityHasSectors) {
                    $results[$callsign] = false;

                    continue;
                }
            }

            $attributes = [
                ...collect($flight)->except('callsign')->all(),
                'last_seen_at' => $now,
            ];

            if ($existing !== null) {
                // Already loaded above - fill+save is a single UPDATE, rather than
                // updateOrCreate()'s own redundant existence SELECT before it writes.
                $existing->fill($attributes)->save();
            } else {
                FlightDataRecord::create(['callsign' => $callsign, ...$attributes]);
            }

            $results[$callsign] = true;
        }

        return $results;
    }
}
