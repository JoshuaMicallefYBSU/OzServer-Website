<?php

namespace App\Http\Controllers;

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

            $stillOnline = (new VATSIMClient)->searchCallsign($existing->controller_callsign, true);

            if ($stillOnline !== null && (int) $stillOnline->cid === $existing->controller_cid) {
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

        SectorOwnership::whereIn('sector_id', $covered->pluck('id'))->delete();

        $ownerships = $covered->map(fn (Sector $coveredSector) => SectorOwnership::create([
            'sector_id' => $coveredSector->id,
            'controller_cid' => $vatsim['cid'],
            'controller_callsign' => $vatsim['callsign'],
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

        SectorOwnershipRequest::where('sector_id', $sector->id)->delete();

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
            ->exists();

        if ($alreadyRequested) {
            return response()->json(['message' => 'You already have a pending request for this sector.'], 409);
        }

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

        return response()->json(['message' => 'Ownership transferred.']);
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

        return response()->json(['results' => $results]);
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

        if (! $isCurrentTarget) {
            return false;
        }

        $covered = $sector->coveredSectors();

        SectorOwnership::whereIn('sector_id', $covered->pluck('id'))
            ->where('controller_cid', $cid)
            ->update([
                'controller_cid' => $sectorOwnershipRequest->requesting_cid,
                'controller_callsign' => $sectorOwnershipRequest->requesting_callsign,
            ]);

        // Any other pending requests for this sector are moot now.
        SectorOwnershipRequest::where('sector_id', $sector->id)->delete();

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

        return response()->noContent();
    }

    /**
     * My pending requests in both directions - outgoing ("by_me") and
     * incoming against sectors I currently own ("from_me").
     */
    public function myRequests(Request $request)
    {
        $vatsim = $request->attributes->get('vatsim');

        return response()->json([
            'by_me' => SectorOwnershipRequest::with('sector')->where('requesting_cid', $vatsim['cid'])->get(),
            'from_me' => SectorOwnershipRequest::with('sector')->where('target_cid', $vatsim['cid'])->get(),
        ]);
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
        $vatsim = $request->attributes->get('vatsim');

        $sectors = Sector::whereHas('ownership', fn ($query) => $query->where('controller_cid', $vatsim['cid']))->get();

        return response()->json($sectors->map(fn (Sector $sector) => [
            'id' => $sector->id,
            'name' => $sector->name,
            'full_name' => $sector->full_name,
        ]));
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
        $vatsim = $request->attributes->get('vatsim');

        $sectors = Sector::whereHas('ownership', fn ($query) => $query->where('controller_cid', '!=', $vatsim['cid']))
            ->with('ownership')
            ->get();

        return response()->json($sectors->map(fn (Sector $sector) => [
            'name' => $sector->name,
            'full_name' => $sector->full_name,
            'type' => $sector->type,
            'callsign' => $sector->callsign,
            'frequency' => $sector->frequency,
            'owner' => [
                'cid' => $sector->ownership->controller_cid,
                'callsign' => $sector->ownership->controller_callsign,
            ],
        ]));
    }
}
