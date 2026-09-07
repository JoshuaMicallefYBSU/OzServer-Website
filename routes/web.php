<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);

Route::get('/ops', function () {
    return view('map');
});

Route::get('/console', function () {
    // No password configured means no route - safer than serving a page whose gate a blank
    // submission would pass. See config/services.php's own comment on what this password is (and
    // isn't) protecting.
    abort_if(blank(config('services.ozserver_api.console_password')), 404);

    return view('console');
});
