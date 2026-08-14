<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncVatsysDatasetJob;
use App\Jobs\AFVTransieversUpdate;
use App\Jobs\ReleaseStaleSectorOwnershipsJob;

Schedule::job(new SyncVatsysDatasetJob)->dailyAt('10:15');

// Neither job is queued (ShouldQueue) - this environment only runs
// schedule:run on a cron trigger, no persistent queue worker, so a
// self-requeuing queued job would just sit unpicked. Schedule::job() runs
// a non-queued job synchronously inline, and each job loops its own work
// 4x ~15s apart internally (~45s total) to approximate a 15s cadence
// within Laravel's 1-minute scheduler floor - see the jobs themselves.
Schedule::job(new AFVTransieversUpdate)->everyMinute()->withoutOverlapping();
Schedule::job(new ReleaseStaleSectorOwnershipsJob)->everyMinute()->withoutOverlapping();
