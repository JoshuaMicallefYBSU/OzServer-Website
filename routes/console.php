<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncVatsysDatasetJob;
use App\Jobs\AFVTransieversUpdate;
use App\Jobs\ReleaseStaleSectorOwnershipsJob;

Schedule::job(new SyncVatsysDatasetJob)->dailyAt('10:15');
Schedule::job(new AFVTransieversUpdate)->everyMinute()->withoutOverlapping();
Schedule::job(new ReleaseStaleSectorOwnershipsJob)->everyMinute()->withoutOverlapping();