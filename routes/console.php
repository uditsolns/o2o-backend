<?php

use App\Console\Commands\SeedSepioPortsCommand;
use App\Jobs\SepioSealAllocationPollJob;
use App\Jobs\SepioSealStatusSyncJob;
use App\Jobs\SepioVerificationStatusPollJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command(SeedSepioPortsCommand::class)
    ->weekly()->sundays()->at('02:00');

Schedule::job(SepioVerificationStatusPollJob::class)
    ->everyThirtyMinutes();

Schedule::job(SepioSealAllocationPollJob::class)
    ->hourly();

Schedule::job(SepioSealStatusSyncJob::class)
    ->everyFifteenMinutes();
