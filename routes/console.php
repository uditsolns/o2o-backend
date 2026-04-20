<?php

use App\Console\Commands\SeedSepioPortsCommand;
use App\Jobs\FastTagPollJob;
use App\Jobs\SepioOrderStatusSyncJob;
use App\Jobs\SepioSealAllocationPollJob;
use App\Jobs\SepioSealStatusSyncJob;
use App\Jobs\SepioVerificationStatusPollJob;
use App\Jobs\VesselAisPollJob;
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

// FastTag: poll every 15 min for active road/multimodal trips
Schedule::job(FastTagPollJob::class)
    ->everyFifteenMinutes();

// AIS vessel position: every 30 min for active sea legs
Schedule::job(VesselAisPollJob::class)->everyThirtyMinutes();
