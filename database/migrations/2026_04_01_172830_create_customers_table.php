<?php

use App\Enums\CompanyType;
use App\Enums\CustomerOnboardingStatus;
use App\Enums\SepioStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('company_name');
            $table->string('email')->unique();
            $table->string('mobile', 20);

            // Company
            $table->enum('company_type', CompanyType::values())->nullable();
            $table->string('industry_type', 100)->nullable();

            // Onboarding lifecycle
            $table->enum('onboarding_status', CustomerOnboardingStatus::values())
                ->default(CustomerOnboardingStatus::Pending->value);

            // Sepio integration
            $table->string('sepio_company_id', 100)->nullable();
            $table->text('sepio_token')->nullable();
            $table->timestamp('sepio_token_expires_at')->nullable();
            $table->text('sepio_credentials')->nullable()
                ->comment('Encrypted {email, password} used for Sepio login');

            // Statutory
            $table->string('gst_number', 20)->nullable();
            $table->string('iec_number', 20)->nullable()->unique();

            // Activation
            $table->boolean('is_active')->default(true);

            // Sepio-specific lifecycle — tracked separately from platform onboarding
            $table->boolean('sepio_enabled')->default(false)
                ->comment('Whether this customer has Sepio seal integration enabled');
            $table->enum('sepio_status', SepioStatus::values())->nullable()
                ->comment('Tracks Sepio-specific lifecycle separately from platform onboarding');

            // Billing address
            $table->text('billing_address')->nullable();
            $table->string('billing_landmark')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_pincode', 10)->nullable();
            $table->string('billing_country', 100)->default('India');

            // Contacts
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->string('primary_contact_mobile', 20)->nullable();
            $table->string('alternate_contact_name')->nullable();
            $table->string('alternate_contact_phone', 20)->nullable();
            $table->string('alternate_contact_email')->nullable();

            // IL approval decision
            $table->timestamp('il_approved_at')->nullable();
            $table->text('il_remarks')->nullable();

            // IL insurance policy snapshot — captured when Sepio onboarding completes
            $table->string('il_policy_number', 100)->nullable();
            $table->date('il_policy_expiry')->nullable();
            $table->decimal('sum_insured', 15, 2)->nullable();
            $table->decimal('gwp', 15, 2)->nullable();

            // FKs onto users are deferred to a separate migration because users
            // doesn't exist yet at this point in the migration order.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
