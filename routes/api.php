<?php
use App\Http\Controllers\AfvTransceiverController;
use App\Http\Controllers\APIController;
use App\Http\Controllers\FlightDataRecordController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\SectorOwnershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/flights/live', [APIController::class, 'AFVTransievers']);

    Route::post('/fdr', [FlightDataRecordController::class, 'update'])->middleware('vatsim.verified');

    Route::get('/afv/transceivers', [AfvTransceiverController::class, 'index']);

    Route::prefix('sectors/{sector:name}')->group(function () {
        Route::post('/claim', [SectorOwnershipController::class, 'claim'])->middleware('vatsim.verified');
        Route::post('/release', [SectorOwnershipController::class, 'release'])->middleware('vatsim.verified');
        Route::post('/request', [SectorOwnershipController::class, 'request'])->middleware('vatsim.verified');
    });

    Route::get('/sector-requests', [SectorOwnershipController::class, 'myRequests'])->middleware('vatsim.verified');

    Route::prefix('sector-requests/{sectorOwnershipRequest}')->group(function () {
        Route::post('/accept', [SectorOwnershipController::class, 'accept'])->middleware('vatsim.verified');
        Route::post('/reject', [SectorOwnershipController::class, 'reject'])->middleware('vatsim.verified');
        Route::post('/cancel', [SectorOwnershipController::class, 'cancel'])->middleware('vatsim.verified');
    });

    Route::prefix('map')->group(function () {
        Route::get('/sectors', [MapController::class, 'sectors']);
        Route::get('/aircraft', [MapController::class, 'aircraft']);
        Route::get('/controllers', [MapController::class, 'controllers']);
    });
});
