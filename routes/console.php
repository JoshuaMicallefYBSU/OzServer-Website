<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncVatsysDatasetJob;
use App\Jobs\AFVTransieversUpdate;
use App\Jobs\ReleaseStaleSectorOwnershipsJob;

Schedule::job(new SyncVatsysDatasetJob)->dailyAt('10:15');

// Both jobs actually run on a true ~15s cadence via self-requeuing
// (each dispatches itself again with a 15s delay at the end of its own
// handle() - see the jobs themselves). These checks just restart the
// chain if it ever goes quiet - cheap (a single cache read), so no more
// blocking schedule:run for up to 45s like the old sleep-loop did.
Schedule::call(function () {
    if (! AFVTransieversUpdate::isRunning()) {
        AFVTransieversUpdate::dispatch();
    }
})->everyMinute()->name('afv-transceivers-poll-healthcheck');

Schedule::call(function () {
    if (! ReleaseStaleSectorOwnershipsJob::isRunning()) {
        ReleaseStaleSectorOwnershipsJob::dispatch();
    }
})->everyMinute()->name('release-stale-sectors-healthcheck');