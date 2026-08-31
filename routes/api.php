<?php
use App\Http\Controllers\AfvTransceiverController;
use App\Http\Controllers\APIController;
use App\Http\Controllers\AtisController;
use App\Http\Controllers\FlightDataRecordController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\SectorOwnershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/flights/live', [APIController::class, 'AFVTransievers']);

    Route::post('/fdr', [FlightDataRecordController::class, 'update'])->middleware('plugin.token');
    Route::post('/fdr/batch', [FlightDataRecordController::class, 'batchUpdate'])->middleware('plugin.token');

    Route::post('/atis', [AtisController::class, 'update'])->middleware('plugin.token');
    Route::get('/atis/{icao}', [AtisController::class, 'show']);

    Route::get('/afv/transceivers', [AfvTransceiverController::class, 'index']);

    Route::get('/sectors/mine', [SectorOwnershipController::class, 'mine'])->middleware('plugin.token');

    Route::get('/sectors/controlled', [SectorOwnershipController::class, 'controlled'])->middleware('plugin.token');

    Route::prefix('sectors/{sector:name}')->group(function () {
        Route::post('/claim', [SectorOwnershipController::class, 'claim'])->middleware('plugin.token');
        Route::post('/release', [SectorOwnershipController::class, 'release'])->middleware('plugin.token');
        Route::post('/request', [SectorOwnershipController::class, 'request'])->middleware('plugin.token');
    });

    Route::get('/sector-requests', [SectorOwnershipController::class, 'myRequests'])->middleware('plugin.token');
    Route::post('/sector-requests/accept-batch', [SectorOwnershipController::class, 'acceptBatch'])->middleware('plugin.token');

    Route::prefix('sector-requests/{sectorOwnershipRequest}')->group(function () {
        Route::post('/accept', [SectorOwnershipController::class, 'accept'])->middleware('plugin.token');
        Route::post('/reject', [SectorOwnershipController::class, 'reject'])->middleware('plugin.token');
        Route::post('/cancel', [SectorOwnershipController::class, 'cancel'])->middleware('plugin.token');
    });

    Route::prefix('map')->group(function () {
        Route::get('/sectors', [MapController::class, 'sectors']);
        Route::get('/aircraft', [MapController::class, 'aircraft']);
        Route::get('/controllers', [MapController::class, 'controllers']);
        Route::get('/atis', [MapController::class, 'atis']);
    });
});
