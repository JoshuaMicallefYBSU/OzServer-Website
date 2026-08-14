<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncVatsysDatasetJob;
use App\Jobs\AFVTransieversUpdate;

Schedule::job(new SyncVatsysDatasetJob)->everyMinute();

// Laravel's scheduler can't go below 1-minute granularity, so this loops
// 4 times with a 15s sleep between each call to approximate every 15s.
Schedule::call(function () {
    for ($i = 0; $i < 4; $i++) {
        (new AFVTransieversUpdate)->handle();

        if ($i < 3) {
            sleep(15);
        }
    }
})->everyMinute()->name('afv-transceivers-poll')->withoutOverlapping();
