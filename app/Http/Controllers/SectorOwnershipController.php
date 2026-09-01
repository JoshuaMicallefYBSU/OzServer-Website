<?php

namespace App\Http\Controllers;

use App\Models\FlightDataRecord;
use App\Models\Sector;
use App\Models\SectorOwnership;
use App\Models\SectorOwnershipRequest;
use App\Services\VATSIMClient;
use Illuminate\Http\Request;

class SectorOwnershipController extends Controller
{
    /**
     * Claim a sector (and every sector it's "responsible for" -
     * Sector::coveredSectors()) if the whole group is unowned. A sector
     * whose owner is no longer online under their claiming callsign is
     * treated as abandoned and replaced rather than blocking the claim.
     *
     * `exclude` (array of sector names, optional) is left out of the covered
     * group entirely - not touched, not conflict-checked - letting a caller
     * that already knows which of a primary's sub-sectors are contested
     * (from a previous 409's `conflicts`) claim everything else in one shot
     * while leaving those specific sub-sectors with their current owner.
     * E.g. GUN extending WOL while BLA already has WOL's own sub-sector SNO:
     * claiming WOL with SNO excluded gives GUN everything WOL covers except
     * SNO, which stays BLA's, rather than blocking the whole claim.
     */
    public function claim(Request $request, Sector $sector)
    {
        $vatsim = $request->attributes->get('vatsim');
        $exclude = array_filter((array) $request->input('exclude', []), 'is_string');

        $covered = $sector->coveredSectors()->reject(fn (Sector $s) => in_array($s->name, $exclude, true));

        // One client for the whole loop; its lookup map is built at most once.
        $vatsimClient = new VATSIMClient;
        $conflicts = [];

        foreach ($covered as $coveredSector) {
            $existing = $coveredSector->ownership;

            if ($existing === null) {
                continue;
            }

            // Already mine - re-claiming (e.g. re-pressing VSCS transmit, or the plugin's own
            // reconciliation re-asserting what it thinks it owns) is a harmless no-op, not a
            // conflict with myself.
            if ($existing->controller_cid === $vatsim['cid']) {
                continue;
            }

            // A position's own controller always inherits it. Logging in under
            // the sector's callsign is the strongest claim there is - stronger
            // than whoever extended into it while nobody was on it - so this is
            // a takeover, not a conflict, and never turns into a request. The
            // previous holder finds out the way they find out about any other
            // ownership change: their next /sectors/mine refresh drops it,
            // which pulls it back out of MMI and drops its VSCS line to Idle.
            //
            // Matched on callsign, because that is exactly what "logged in on
            // the position" means - cid identifies the person, not the position
            // they are currently occupying.
            if ($coveredSector->callsign !== null
                && strcasecmp((string) $coveredSector->callsign, (string) $vatsim['callsign']) === 0) {
                continue;
            }

            // Map lookup, not a datafeed scan - this runs once per covered sector, so a group
            // like ARL used to rescan the whole feed seven times for one claim.
            $ownerOnline = $vatsimClient->isControllerOnline(
                $existing->controller_callsign,
                $existing->controller_cid
            );

            // Offline but still inside their disconnect grace: they dropped out
            // rather than left (a clean exit releases explicitly - see
            // releaseAll), so their sectors are held for them and this is a
            // conflict, not an abandoned sector free for the taking. It becomes
            // a request, which they can accept if they do not come back.
            if ($ownerOnline || $existing->withinDisconnectGrace()) {
                $conflicts[] = [
                    'sector' => $coveredSector->name,
                    'owner' => [
                        'cid' => $existing->controller_cid,
                        'callsign' => $existing->controller_callsign,
                    ],
                ];
            }
        }

        if ($conflicts !== []) {
            return response()->json([
                'message' => count($conflicts) === 1
                    ? "Sector {$conflicts[0]['sector']} is already owned."
                    : 'Some of these sectors are already owned.',
                'conflicts' => $conflicts,
            ], 409);
        }

        // Only the rows this claim is actually entitled to take: unowned, already this
        // controller's, or left behind by an owner who is neither online nor still inside their
        // disconnect grace. Anything a live controller holds is left exactly where it is.
        //
        // This used to delete every covered row unconditionally the moment the conflict scan above
        // passed. That scan asks the VATSIM datafeed whether the current owner is online, and that
        // feed is cached for 15 seconds and lags a new connection by considerably longer - so a
        // routine re-claim could sail straight past it and silently delete ownership somebody else
        // had legitimately just been given. ARL is responsible for CNK, so the plugin re-asserting
        // ARL (which it does on every MMI change) took a freshly transferred CNK back off its new
        // owner, and handed the whole Armidale group back with it.
        //
        // Ownership is decided from the ownership table here, not from the datafeed. The datafeed
        // only ever decides whether a *conflict* is worth reporting.
        $takeable = $covered->filter(function (Sector $coveredSector) use ($vatsim) {
            $existing = $coveredSector->ownership;

            return $existing === null
                || $existing->controller_cid === $vatsim['cid']
                || ! $existing->withinDisconnectGrace();
        });

        SectorOwnership::whereIn('sector_id', $takeable->pluck('id'))->delete();

        $ownerships = $takeable->map(fn (Sector $coveredSector) => SectorOwnership::create([
            'sector_id' => $coveredSector->id,
            'controller_cid' => $vatsim['cid'],
            'controller_callsign' => $vatsim['callsign'],
            // Claiming proves this controller is here, so the grace window starts now rather than
            // inheriting whatever the previous row happened to carry.
            'last_seen_online_at' => now(),
        ]));

        return response()->json($ownerships, 201);
    }

    /**
     * Release a sector and everything it covers. Only the current owner of
     * the primary sector may release it. Any pending requests against it
     * are cleared too - nothing left to accept/reject once it's unowned.
     */
    public function release(Request $request, Sector $sector)
    {
        $vatsim = $request->attributes->get('vatsim');

        $existing = $sector->ownership;

        if ($existing === null) {
            return response()->json(['message' => 'Sector is not currently owned.'], 404);
        }

        if ($existing->controller_cid !== $vatsim['cid']) {
            return response()->json(['message' => 'Only the current owner may release this sector.'], 403);
        }

        $covered = $sector->coveredSectors();

        SectorOwnership::whereIn('sector_id', $covered->pluck('id'))
            ->where('controller_cid', $vatsim['cid'])
            ->delete();

        SectorOwnershipRequest::where('sector_id', $sector->id)->pending()->delete();

        // Only once every sector's gone, not just this one - releasing TWR
        // while still holding APP doesn't mean they've lost authority over
        // anything, since a flight's controlling_cid isn't tied to any
        // particular sector.
        if (! SectorOwnership::where('controller_cid', $vatsim['cid'])->exists()) {
            FlightDataRecord::releaseAuthorityFor($vatsim['cid']);
        }

        return response()->noContent();
    }

    /**
     * Give up every sector this controller owns, right now.
     *
     * The plugin calls this on a *graceful* disconnect only - closing vatSys
     * while connected, or pressing Disconnect. An ungraceful disconnect (a
     * crash, a dropped connection) sends nothing at all, and that silence is
     * exactly what distinguishes the two: RefreshVatsimLiveDataJob then holds
     * those sectors for SectorOwnership::DISCONNECT_GRACE_MINUTES so
     * reconnecting picks up where they left off.
     */
    public function releaseAll(Request $request)
    {
        $vatsim = $request->attributes->get('vatsim');

        $sectorIds = SectorOwnership::where('controller_cid', $vatsim['cid'])
            ->where('controller_callsign', $vatsim['callsign'])
            ->pluck('sector_id');

        if ($sectorIds->isEmpty()) {
            return response()->noContent();
        }

        SectorOwnership::whereIn('sector_id', $sectorIds)
            ->where('controller_cid', $vatsim['cid'])
            ->where('controller_callsign', $vatsim['callsign'])
            ->delete();

        // Nothing left to accept or reject against a sector nobody owns. Also
        // clears this controller's own outgoing requests: they have gone.
        SectorOwnershipRequest::whereIn('sector_id', $sectorIds)->delete();
        SectorOwnershipRequest::where('requesting_cid', $vatsim['cid'])->delete();

        // Every sector just went at once, so unlike release() there's no need
        // to check what's left - they hold nothing now.
        FlightDataRecord::releaseAuthorityFor($vatsim['cid']);

        return response()->noContent();
    }

    /**
     * Request an already-owned sector from its current owner. Unowned
     * sectors should be claimed directly instead - this is only for
     * sectors someone else already holds.
     */
    public function request(Request $request, Sector $sector)
    {
        $vatsim = $request->attributes->get('vatsim');

        $existing = $sector->ownership;

        if ($existing === null) {
            return response()->json([
                'message' => 'Sector is unclaimed - claim it directly instead of requesting it.',
            ], 400);
        }

        if ($existing->controller_cid === $vatsim['cid']) {
            return response()->json(['message' => 'You already own this sector.'], 400);
        }

        $alreadyRequested = SectorOwnershipRequest::where('sector_id', $sector->id)
            ->where('requesting_cid', $vatsim['cid'])
            ->pending()
            ->exists();

        if ($alreadyRequested) {
            return response()->json(['message' => 'You already have a pending request for this sector.'], 409);
        }

        // Asking again is allowed, but unique(sector_id, requesting_cid) means
        // an earlier rejection they never collected would block the insert -
        // and permanently lock them out of that sector if they were offline
        // when it was rejected. Making the new request supersedes it.
        SectorOwnershipRequest::where('sector_id', $sector->id)
            ->where('requesting_cid', $vatsim['cid'])
            ->whereNotNull('rejected_at')
            ->delete();

        $sectorOwnershipRequest = SectorOwnershipRequest::create([
            'sector_id' => $sector->id,
            'requesting_cid' => $vatsim['cid'],
            'requesting_callsign' => $vatsim['callsign'],
            'target_cid' => $existing->controller_cid,
            'target_callsign' => $existing->controller_callsign,
        ]);

        return response()->json($sectorOwnershipRequest, 201);
    }

    /**
     * Accept an incoming request - transfers the sector (and everything it
     * covers) to the requester. Only the sector's current owner, and only
     * if they're still that request's target, may accept.
     */
    public function accept(Request $request, SectorOwnershipRequest $sectorOwnershipRequest)
    {
        $vatsim = $request->attributes->get('vatsim');

        if (! $this->acceptOne($vatsim['cid'], $sectorOwnershipRequest)) {
            return response()->json(['message' => "Only the sector's current owner may accept this request."], 403);
        }

        return response()->json([
            'message' => 'Ownership transferred.',
            'sync' => $this->syncPayload($vatsim),
        ]);
    }

    /**
     * Accept several incoming requests in one call, processed sequentially
     * in the given order - the plugin's "accept all selected" action.
     * Firing separate accept() calls back-to-back for a batch left a window
     * where each one's own claim/refresh cascade (MMI.SectorsControlledChanged
     * -> re-claim -> refresh Owned, all client-side and asynchronous) could
     * still be in flight when the next one landed, occasionally leaving a
     * request row undeleted even though its sector's authority had already
     * moved on. Going through one request removes that race by
     * construction: only one accept is ever actually running server-side at
     * a time, so each one's own SectorOwnershipRequest::delete() completes
     * before the next starts.
     */
    public function acceptBatch(Request $request)
    {
        $vatsim = $request->attributes->get('vatsim');
        $ids = array_values(array_filter((array) $request->input('request_ids', []), 'is_numeric'));

        $results = collect($ids)->map(function ($id) use ($vatsim) {
            $sectorOwnershipRequest = SectorOwnershipRequest::find($id);

            if ($sectorOwnershipRequest === null) {
                return ['request_id' => (int) $id, 'accepted' => false, 'message' => 'Request no longer exists.'];
            }

            $sectorName = $sectorOwnershipRequest->sector->name;
            $accepted = $this->acceptOne($vatsim['cid'], $sectorOwnershipRequest);

            return [
                'request_id' => (int) $id,
                'sector' => $sectorName,
                'accepted' => $accepted,
                'message' => $accepted ? 'Ownership transferred.' : "Only the sector's current owner may accept this request.",
            ];
        });

        // The resulting state travels back with the result of the action that caused it. The plugin
        // used to POST and then immediately GET /sectors/sync to find out what had changed, so every
        // accept cost two sequential round trips - and the second could queue behind an in-flight
        // poll before it even started. Returning it here makes the whole thing one round trip.
        return response()->json([
            'results' => $results,
            'sync' => $this->syncPayload($vatsim),
        ]);
    }

    /**
     * Shared accept logic for accept()/acceptBatch() - transfers
     * sectorOwnershipRequest's sector (and everything it covers) to the
     * requester if $cid is still the sector's current owner and this
     * request's target. Returns whether the transfer happened.
     */
    private function acceptOne(int $cid, SectorOwnershipRequest $sectorOwnershipRequest): bool
    {
        $sector = $sectorOwnershipRequest->sector;
        $currentOwnership = $sector->ownership;

        $isCurrentTarget = $sectorOwnershipRequest->target_cid === $cid
            && $currentOwnership !== null
            && $currentOwnership->controller_cid === $cid;

        // Already decided - rejecting then accepting the same row would hand
        // over a sector on a request the owner had explicitly refused.
        if (! $isCurrentTarget || $sectorOwnershipRequest->rejected_at !== null) {
            return false;
        }

        $covered = $sector->coveredSectors();

        SectorOwnership::whereIn('sector_id', $covered->pluck('id'))
            ->where('controller_cid', $cid)
            ->update([
                'controller_cid' => $sectorOwnershipRequest->requesting_cid,
                'controller_callsign' => $sectorOwnershipRequest->requesting_callsign,
                // Restamped for the new owner. Without this the row kept the *previous* owner's
                // last_seen_online_at, which could already be older than the disconnect grace - so
                // a sector transferred to a controller who is demonstrably here (they just asked
                // for it) was immediately takeable by the next claim that covered it.
                'last_seen_online_at' => now(),
            ]);

        // Any other pending requests for this sector are moot now. Rejected
        // ones are left alone - they are already decided and are only waiting
        // to be collected by the controller they were rejected on.
        SectorOwnershipRequest::where('sector_id', $sector->id)->pending()->delete();

        // The old owner just lost this sector to the transfer above - if that
        // was their last one, same as release() dropping them to zero.
        if (! SectorOwnership::where('controller_cid', $cid)->exists()) {
            FlightDataRecord::releaseAuthorityFor($cid);
        }

        return true;
    }

    /**
     * Reject an incoming request - no ownership change, just closes it out.
     */
    public function reject(Request $request, SectorOwnershipRequest $sectorOwnershipRequest)
    {
        $vatsim = $request->attributes->get('vatsim');

        if ($sectorOwnershipRequest->target_cid !== $vatsim['cid']) {
            return response()->json(['message' => "Only the sector's current owner may reject this request."], 403);
        }

        // Flagged rather than deleted, so the requesting controller's next
        // myRequests() poll can tell them they were denied. Deleting it here
        // made a denial indistinguishable from an accept, a cancel or a stale
        // prune - the row just vanished from their list either way. They
        // acknowledge it (below), which is what finally removes it.
        $sectorOwnershipRequest->update(['rejected_at' => now()]);

        return response()->json(['sync' => $this->syncPayload($vatsim)]);
    }

    /**
     * Acknowledge a rejection of my own request - the requesting plugin calls
     * this once it has told the controller they were denied, which is what
     * finally removes the row. Kept separate from cancel() because only the
     * requester can be the one who has actually seen it, and because deleting
     * it is the whole point rather than a side effect.
     */
    public function acknowledgeRejection(Request $request, SectorOwnershipRequest $sectorOwnershipRequest)
    {
        $vatsim = $request->attributes->get('vatsim');

        if ($sectorOwnershipRequest->requesting_cid !== $vatsim['cid']) {
            return response()->json(['message' => 'Only the requester may acknowledge this rejection.'], 403);
        }

        if ($sectorOwnershipRequest->rejected_at === null) {
            return response()->json(['message' => 'That request has not been rejected.'], 400);
        }

        $sectorOwnershipRequest->delete();

        return response()->noContent();
    }

    /**
     * Cancel my own outgoing request - no ownership change.
     */
    public function cancel(Request $request, SectorOwnershipRequest $sectorOwnershipRequest)
    {
        $vatsim = $request->attributes->get('vatsim');

        if ($sectorOwnershipRequest->requesting_cid !== $vatsim['cid']) {
            return response()->json(['message' => 'Only the requester may cancel this request.'], 403);
        }

        $sectorOwnershipRequest->delete();

        return response()->json(['sync' => $this->syncPayload($vatsim)]);
    }

    /**
     * My pending requests in both directions - outgoing ("by_me") and
     * incoming against sectors I currently own ("from_me").
     */
    public function myRequests(Request $request)
    {
        return response()->json($this->requestsPayload($request->attributes->get('vatsim')));
    }

    /**
     * Sectors currently owned by the requesting controller - the
     * authoritative "what do I actually own" check, used to refresh the
     * plugin's Owned list every time its Sectors window opens rather than
     * trust locally-accumulated state that can drift out of sync (a claim
     * from a previous vatSys session, an ownership released some other way,
     * a local claim call that failed silently, ...).
     */
    public function mine(Request $request)
    {
        return response()->json($this->minePayload($request->attributes->get('vatsim')));
    }

    /**
     * The three payload shapes below back both the individual endpoints and
     * sync(). Shared rather than duplicated: the plugin parses one set of
     * DTOs for both, so a drift between them would be a wire-format bug that
     * only shows up on whichever route happens to be in use.
     */
    private function minePayload(array $vatsim)
    {
        return Sector::whereHas('ownership', fn ($query) => $query->where('controller_cid', $vatsim['cid']))
            ->get()
            ->map(fn (Sector $sector) => [
                'id' => $sector->id,
                'name' => $sector->name,
                'full_name' => $sector->full_name,
            ]);
    }

    private function controlledPayload(array $vatsim)
    {
        return Sector::whereHas('ownership', fn ($query) => $query->where('controller_cid', '!=', $vatsim['cid']))
            ->with('ownership')
            ->get()
            ->map(fn (Sector $sector) => [
                'name' => $sector->name,
                'full_name' => $sector->full_name,
                'type' => $sector->type,
                'callsign' => $sector->callsign,
                'frequency' => $sector->frequency,
                'owner' => [
                    'cid' => $sector->ownership->controller_cid,
                    'callsign' => $sector->ownership->controller_callsign,
                ],
            ]);
    }

    private function requestsPayload(array $vatsim): array
    {
        return [
            // Includes this controller's own rejected requests: nothing else
            // ever tells them a request was denied. The plugin reports them
            // and then acknowledges them, which deletes them.
            'by_me' => SectorOwnershipRequest::with('sector')
                ->where('requesting_cid', $vatsim['cid'])
                ->get(),
            // Only things still awaiting this controller's decision - a
            // request they already rejected is not theirs to act on again.
            'from_me' => SectorOwnershipRequest::with('sector')
                ->where('target_cid', $vatsim['cid'])
                ->pending()
                ->get(),
        ];
    }

    /**
     * Commits one Apply: releases, then claims, then requests, in a single
     * call, answering with the resulting state.
     *
     * The plugin used to send one POST per sector and then a GET to find out
     * the result, so applying three staged sectors was four sequential round
     * trips - each one paying full latency before the next could start, with
     * the lists frozen until the last returned. Ordering matters and is kept:
     * releases first, because a sector freed by one of them may be exactly
     * what a later claim or request is for.
     *
     * Each leg reuses the same single-sector logic, so behaviour is identical
     * to making the calls individually - including a claim that collides
     * leaving the contested sub-sectors with their owner rather than
     * requesting them behind the controller's back.
     */
    public function commit(Request $request)
    {
        $vatsim = $request->attributes->get('vatsim');

        $names = fn (string $key) => array_values(array_filter(
            (array) $request->input($key, []),
            'is_string'
        ));

        $result = ['claimed' => [], 'released' => [], 'requested' => [], 'skipped' => [], 'failed' => []];

        foreach ($names('release') as $name) {
            $sector = Sector::where('name', $name)->first();

            if ($sector === null || $sector->ownership === null
                || $sector->ownership->controller_cid !== $vatsim['cid']) {
                $result['failed'][] = $name;

                continue;
            }

            SectorOwnership::whereIn('sector_id', $sector->coveredSectors()->pluck('id'))
                ->where('controller_cid', $vatsim['cid'])
                ->delete();

            SectorOwnershipRequest::where('sector_id', $sector->id)->pending()->delete();
            $result['released'][] = $name;
        }

        foreach ($names('claim') as $name) {
            $sector = Sector::where('name', $name)->first();

            if ($sector === null) {
                $result['failed'][] = $name;

                continue;
            }

            [$claimed, $skipped] = $this->claimCovered($vatsim, $sector);
            $result['claimed'] = array_merge($result['claimed'], $claimed);
            $result['skipped'] = array_merge($result['skipped'], $skipped);
        }

        foreach ($names('request') as $name) {
            $sector = Sector::where('name', $name)->first();
            $existing = $sector?->ownership;

            if ($sector === null || $existing === null || $existing->controller_cid === $vatsim['cid']) {
                $result['failed'][] = $name;

                continue;
            }

            // Asking again is allowed; an uncollected rejection would otherwise
            // block the insert on unique(sector_id, requesting_cid).
            SectorOwnershipRequest::where('sector_id', $sector->id)
                ->where('requesting_cid', $vatsim['cid'])
                ->whereNotNull('rejected_at')
                ->delete();

            $alreadyPending = SectorOwnershipRequest::where('sector_id', $sector->id)
                ->where('requesting_cid', $vatsim['cid'])
                ->pending()
                ->exists();

            if (! $alreadyPending) {
                SectorOwnershipRequest::create([
                    'sector_id' => $sector->id,
                    'requesting_cid' => $vatsim['cid'],
                    'requesting_callsign' => $vatsim['callsign'],
                    'target_cid' => $existing->controller_cid,
                    'target_callsign' => $existing->controller_callsign,
                ]);
            }

            $result['requested'][] = $name;
        }

        return response()->json([
            'result' => $result,
            'sync' => $this->syncPayload($vatsim),
        ]);
    }

    /**
     * Claims what this controller is entitled to out of a sector's covered
     * group, returning [claimed names, skipped names]. Shared by commit() and
     * claim() so the two can never disagree about what a claim takes.
     */
    private function claimCovered(array $vatsim, Sector $sector): array
    {
        $vatsimClient = new VATSIMClient;
        $skipped = [];

        $takeable = $sector->coveredSectors()->filter(function (Sector $coveredSector) use ($vatsim, $vatsimClient, &$skipped) {
            $existing = $coveredSector->ownership;

            if ($existing === null || $existing->controller_cid === $vatsim['cid']) {
                return true;
            }

            // A position's own controller always inherits it - see claim().
            if ($coveredSector->callsign !== null
                && strcasecmp((string) $coveredSector->callsign, (string) $vatsim['callsign']) === 0) {
                return true;
            }

            $held = $vatsimClient->isControllerOnline($existing->controller_callsign, $existing->controller_cid)
                || $existing->withinDisconnectGrace();

            if ($held) {
                $skipped[] = $coveredSector->name;
            }

            return ! $held;
        });

        SectorOwnership::whereIn('sector_id', $takeable->pluck('id'))->delete();

        foreach ($takeable as $coveredSector) {
            SectorOwnership::create([
                'sector_id' => $coveredSector->id,
                'controller_cid' => $vatsim['cid'],
                'controller_callsign' => $vatsim['callsign'],
                'last_seen_online_at' => now(),
            ]);
        }

        return [$takeable->pluck('name')->all(), $skipped];
    }

    /**
     * Everything the plugin's poll needs, in one request.
     *
     * The Sectors window polls every two seconds while it is open, and used
     * to issue three separate GETs per tick - /sectors/mine, /sectors/
     * controlled and /sector-requests - each booting the framework, resolving
     * the route, and running the plugin-token middleware for itself. With
     * several controllers connected that is the bulk of the request volume
     * this API sees, and none of it was work the database found expensive;
     * it was per-request overhead, three times over, for data that always
     * gets consumed together.
     *
     * The individual routes are deliberately kept: they are a smaller,
     * clearer contract for anything that only wants one of the three.
     */
    public function sync(Request $request)
    {
        return response()->json($this->syncPayload($request->attributes->get('vatsim')));
    }

    /**
     * The same shape GET /sectors/sync returns, also attached to the
     * response of every action that changes ownership or the request set -
     * accept, acceptBatch, reject, cancel. The caller of an action already
     * knows something changed; making it ask again is a wasted round trip on
     * exactly the path where latency is most visible.
     */
    private function syncPayload(array $vatsim): array
    {
        return [
            'mine' => $this->minePayload($vatsim),
            'controlled' => $this->controlledPayload($vatsim),
            'requests' => $this->requestsPayload($vatsim),
        ];
    }

    /**
     * Sectors currently owned by someone other than the caller - the
     * plugin's "Controlled" list. Not restricted to the map's TWR/APP/DEP/
     * ENR subset - ownership (and Flow/GND/DEL positions with it) isn't
     * type-restricted, so this reflects every claimed sector regardless of
     * type, letting the plugin group them (Flow/Centre/Approach/Tower) how
     * it needs to.
     */
    public function controlled(Request $request)
    {
        return response()->json($this->controlledPayload($request->attributes->get('vatsim')));
    }
}
