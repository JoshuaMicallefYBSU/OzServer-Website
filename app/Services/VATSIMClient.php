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
     * blocked indefinitely - ReleaseStaleSectorOwnershipsJob and
     * AFVTransieversUpdate both run inline inside the scheduler's process
     * (see their class docs), and an uncaught exception or unbounded wait
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