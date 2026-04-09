<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Services\Sepio\SepioOnboardingService;
use Illuminate\Console\Command;

class DebugSepioUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:sepio-upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $customer = Customer::find(7);
        $doc = $customer->documents()->where('doc_type', 'gst_cert')->first();
        $doc->update(['sepio_file_name' => null]);

        app(SepioOnboardingService::class)->uploadDocument($customer, $doc);

        $this->info('Done. Check Telescope → HTTP Client.');
    }
}
