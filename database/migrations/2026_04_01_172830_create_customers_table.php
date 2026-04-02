<?php

use App\Enums\CompanyType;
use App\Enums\CustomerOnboardingStatus;
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
            $table->enum('company_type', CompanyType::values())->nullable();
            $table->string('industry_type', 100)->nullable();
            $table->enum('onboarding_status', CustomerOnboardingStatus::values())
                ->default(CustomerOnboardingStatus::Pending->value);
            $table->string('sepio_company_id', 100)->nullable();
            $table->string('sepio_token')->nullable();
            $table->timestamp('sepio_token_expires_at')->nullable();
            $table->string('gst_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();
            $table->string('iec_number', 20)->unique();
            $table->string('cin_number', 25)->nullable();
            $table->string('tin_number', 30)->nullable();
            $table->string('cha_number', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('billing_address')->nullable();
            $table->string('billing_landmark')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 100)->nullable();
            $table->string('billing_pincode', 10)->nullable();
            $table->string('billing_country', 100)->default('India');
            $table->string('primary_contact_name')->nullable();
            $table->string('primary_contact_email')->nullable();
            $table->string('primary_contact_mobile', 20)->nullable();
            $table->string('alternate_contact_name')->nullable();
            $table->string('alternate_contact_phone', 20)->nullable();
            $table->string('alternate_contact_email')->nullable();
            $table->timestamp('il_approved_at')->nullable();
            $table->text('il_remarks')->nullable();
            $table->timestamps();

            $table->index('onboarding_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
