<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('mobile', 20)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->enum('status', UserStatus::values())->default(UserStatus::Invited->value);
            $table->timestamp('last_login_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
