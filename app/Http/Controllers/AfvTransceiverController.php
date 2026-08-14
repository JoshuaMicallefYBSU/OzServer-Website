<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class AfvTransceiverController extends Controller
{
    /**
     * Raw passthrough of the cached VATSIM AFV transceiver feed, refreshed
     * every ~15s by App\Jobs\AFVTransieversUpdate - lets external plugins
     * read it from here instead of hitting VATSIM directly.
     */
    public function index()
    {
        if (! Storage::exists('afv-transceivers.json')) {
            return response()->json([]);
        }

        return response(Storage::get('afv-transceivers.json'))
            ->header('Content-Type', 'application/json');
    }
}
