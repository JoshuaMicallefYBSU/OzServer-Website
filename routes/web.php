<?php

use App\Http\Controllers\ConsoleController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);

Route::get('/live-map', function () {
    return view('map');
});

// The controller guide. The plugin's "OzServer How-To" menu button opens it in vatSys's browser.
Route::view('/how-to', 'how-to');

// The map used to live at /ops, then /map; old links and bookmarks still land on it, query string
// included.
foreach (['/ops', '/map'] as $oldMapUrl) {
    Route::get($oldMapUrl, fn () => redirect()->to(
        '/live-map'.(request()->getQueryString() ? '?'.request()->getQueryString() : ''), 301));
}

// No password configured means no console (ConsoleController answers 404). The password is
// checked server-side and held in the session; the message and log routes forward to the OzServer
// API with tokens only this server holds.
//
// Each throttle is named: unnamed ones are all counted together per address, so a console's own
// polling used up the attempts allowed on the password.
Route::get('/console', [ConsoleController::class, 'show']);
Route::post('/console/unlock', [ConsoleController::class, 'unlock'])->middleware('throttle:10,1,console-unlock');
Route::post('/console/lock', [ConsoleController::class, 'lock']);
Route::prefix('/console/api')->middleware('throttle:60,1,console-api')->group(function () {
    Route::get('/messages', [ConsoleController::class, 'messages']);
    Route::post('/messages', [ConsoleController::class, 'send']);
    // At most 15 digits: a longer id would not fit the int the controller takes.
    Route::post('/messages/{id}/withdraw', [ConsoleController::class, 'withdraw'])->where('id', '[0-9]{1,15}');
    Route::get('/clients', [ConsoleController::class, 'clients']);
});
// The log feed is read every 2 seconds by each open console (30 a minute), so it is counted apart
// from the routes above: room for three consoles behind one address, and their history loads.
Route::get('/console/api/logs', [ConsoleController::class, 'logs'])->middleware('throttle:120,1,console-logs');
