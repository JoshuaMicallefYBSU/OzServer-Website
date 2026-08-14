<?php

namespace App\Console\Commands;

use App\Jobs\SyncVatsysDatasetJob;
use Illuminate\Console\Command;

class SyncVatsysDataset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dataset:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync sectors, volumes, positions, and airspace data from the vatSys Australia dataset';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        SyncVatsysDatasetJob::dispatch();

        $this->info('Dataset sync job dispatched.');
    }
}
