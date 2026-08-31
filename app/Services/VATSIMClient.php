<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;

class VATSIMClient
{
    private const HTTP_TIMEOUT_SECONDS = 10;

    /**
     * A hung or failed request here must not be allowed to leave the caller
     * blocked indefinitely - RefreshVatsimLiveDataJob runs both the AFV feed
     * fetch and the stale-ownership release inline inside the scheduler's
     * process (see its class doc), and an uncaught exception or unbounded wait
     * there can outlive the shared host's process limits, getting the
     * process killed before it releases its ->withoutOverlapping() mutex -
     * silently wedging the job for the lock's full expiry.
     */
    public function getVATSIMData()
    {
        try {
            $client = new Client(['connect_timeout' => self::HTTP_TIMEOUT_SECONDS, 'timeout' => self::HTTP_TIMEOUT_SECONDS]);
            $responseStatus = $client->get('https://status.vatsim.net/status.json');
            $dataUrl = json_decode($responseStatus->getBody())->data->v3[0];

            $response = $client->get($dataUrl);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody());
            }
        } catch (GuzzleException) {
            return null;
        }
    }

    public function getCurrentData()
    {
        return Cache::remember('vatsimdata', 15, function () {
            return $this->getVATSIMData();
        });
    }

    /**
     * Callsign (upper-cased) => CID for every controller currently online,
     * or null when the datafeed is unavailable.
     *
     * searchCallsign() answers the same question by pulling the whole
     * multi-megabyte datafeed back out of the cache, unserialising it, and
     * scanning its controllers array linearly - per call. That is affordable
     * once. It is not affordable from inside a loop, and every hot path did
     * exactly that: SectorOwnershipController::claim ran it once per covered
     * sector (seven for a group like ARL), FlightDataRecordController::upsert
     * once per flight in a batch - pushed every five seconds by every
     * connected client - and the sector-ownership release once per owner,
     * four times a minute (back when that logic looped sub-minute like the
     * AFV fetch still does; it's since settled to once per invocation -
     * see RefreshVatsimLiveDataJob). Together they re-read and re-scanned
     * that payload thousands of times a minute, which is what pinned the
     * host CPU.
     *
     * The derived map is cached instead - a few hundred short strings rather
     * than the entire feed - and memoised for the life of the request, so a
     * batch of fifty flights costs one lookup table rather than fifty scans.
     */
    private static ?array $onlineControllers = null;

    public function onlineControllers(): ?array
    {
        if (self::$onlineControllers !== null) {
            return self::$onlineControllers;
        }

        $map = Cache::remember('vatsimcontrollers', 15, function () {
            $data = $this->getCurrentData();

            if ($data === null || ! isset($data->controllers)) {
                // Cached as null so a feed outage doesn't get read as "nobody
                // is online" - callers have to be able to tell those apart.
                return null;
            }

            $built = [];

            foreach ($data->controllers as $controller) {
                $built[strtoupper((string) $controller->callsign)] = (int) $controller->cid;
            }

            return $built;
        });

        return self::$onlineControllers = $map;
    }

    /**
     * Whether this callsign is online, optionally requiring it to be the same
     * person. Returns false when the feed is unavailable, matching what
     * searchCallsign() returned in that case.
     */
    public function isControllerOnline(?string $callsign, ?int $cid = null): bool
    {
        if ($callsign === null || $callsign === '') {
            return false;
        }

        $map = $this->onlineControllers();

        if ($map === null) {
            return false;
        }

        $key = strtoupper($callsign);

        return array_key_exists($key, $map) && ($cid === null || $map[$key] === $cid);
    }

    public function searchCallsign($callsign, $precise)
    {
        $data = $this->getCurrentData();

        // Datafeed unavailable - return an empty result rather than crashing
        if ($data === null || ! isset($data->controllers)) {
            return $precise ? null : [];
        }

        $controllers = [];

        foreach ($data->controllers as $controller) {
            if ($precise) {
                if ($controller->callsign == $callsign) {
                    return $controller;
                }
            } else {
                $controllerCallsignParts = explode('_', $controller->callsign);
                $callsignParts = explode('_', $callsign);
                if (($controllerCallsignParts[0] === $callsignParts[0]) && (end($controllerCallsignParts) === end($callsignParts))) {
                    array_push($controllers, $controller);
                }
            }
        }

        if ($precise) {
            return null;
        }

        return $controllers;
    }

    public function getAFVTransievers()
    {
        try {
            $client = new Client(['connect_timeout' => self::HTTP_TIMEOUT_SECONDS, 'timeout' => self::HTTP_TIMEOUT_SECONDS]);
            $response = $client->get('https://data.vatsim.net/v3/transceivers-data.json');

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody());
            }
        } catch (GuzzleException) {
            return null;
        }
    }

    public function getPilots()
    {
        $data = $this->getVATSIMData();
        return $data->pilots ?? []; // Ensures it always returns an array
    }
}