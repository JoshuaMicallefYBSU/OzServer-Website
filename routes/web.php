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
// checked server-side and held in the session; the message routes forward to the OzServer API
// with a token only this server holds.
Route::get('/console', [ConsoleController::class, 'show']);
Route::post('/console/unlock', [ConsoleController::class, 'unlock'])->middleware('throttle:10,1');
Route::post('/console/lock', [ConsoleController::class, 'lock']);
Route::prefix('/console/api')->middleware('throttle:60,1')->group(function () {
    Route::get('/messages', [ConsoleController::class, 'messages']);
    Route::post('/messages', [ConsoleController::class, 'send']);
    Route::post('/messages/{id}/withdraw', [ConsoleController::class, 'withdraw'])->whereNumber('id');
    Route::get('/clients', [ConsoleController::class, 'clients']);
});
