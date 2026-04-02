<?php

use App\Enums\TripDocType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trip_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('doc_type', TripDocType::values());
            $table->string('file_name');
            $table->text('url');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_documents');
    }
};
