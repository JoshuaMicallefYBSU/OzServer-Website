<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncVatsysDatasetJob;
use App\Jobs\AFVTransieversUpdate;
use App\Jobs\ReleaseStaleSectorOwnershipsJob;
use App\Jobs\PruneFlightDataRecordsJob;
use App\Jobs\PruneStaleAtisJob;
use App\Jobs\PruneRejectedSectorRequestsJob;

// withoutOverlapping()'s expiry (minutes) bounds how long a killed/hung run can
// wedge the next tick's mutex for - these each finish in ~45s in the normal
// case, so 3 minutes is generous headroom without risking a day-long stall
// (the untimed HTTP calls that used to make an indefinite hang possible here
// have since been given explicit timeouts in VATSIMClient).
Schedule::job(new SyncVatsysDatasetJob)->dailyAt('10:15');
Schedule::job(new AFVTransieversUpdate)->everyMinute()->withoutOverlapping(3);
Schedule::job(new ReleaseStaleSectorOwnershipsJob)->everyMinute()->withoutOverlapping(3);
Schedule::job(new PruneFlightDataRecordsJob)->everyFiveMinutes();
Schedule::job(new PruneStaleAtisJob)->everyFiveMinutes();
Schedule::job(new PruneRejectedSectorRequestsJob)->everyFiveMinutes();
