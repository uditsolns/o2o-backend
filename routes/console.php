<?php

use App\Console\Commands\SeedSepioPortsCommand;
use App\Jobs\SepioOrderStatusSyncJob;
use App\Jobs\SepioSealAllocationPollJob;
use App\Jobs\SepioSealStatusSyncJob;
use App\Jobs\SepioVerificationStatusPollJob;
use Illuminate\Support\Facades\Schedule;

Schedule::command('telescope:prune')->daily();

Schedule::command(SeedSepioPortsCommand::class)
    ->weekly()->sundays()->at('02:00');

// KYC verification status: poll every 30 min until Sepio approves/rejects docs
Schedule::job(SepioVerificationStatusPollJob::class)
    ->everyThirtyMinutes();

// Order status sync: advance orders through Placed → In progress → In Transit.
Schedule::job(SepioOrderStatusSyncJob::class)
    ->everyFifteenMinutes();

// Seal allocation: once Sepio dispatches, grab the seal range, ingest and complete the order.
Schedule::job(SepioSealAllocationPollJob::class)
    ->hourly();

// Seal scan status: update sepio_status + scan logs for active trip seals
Schedule::job(SepioSealStatusSyncJob::class)
    ->everyFifteenMinutes();
