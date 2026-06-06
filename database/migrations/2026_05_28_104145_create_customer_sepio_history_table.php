<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sepio_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->enum('actor_type', ['user', 'system']);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->json('rejected_documents')->nullable()
                ->comment('Array of doc type strings rejected by Sepio');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sepio_history');
    }
};
