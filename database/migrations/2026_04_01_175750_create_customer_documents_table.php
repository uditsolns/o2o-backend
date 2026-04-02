<?php

use App\Enums\CustomerDocType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('doc_type', CustomerDocType::values());
            $table->string('doc_number')->nullable();
            $table->string('file_name');
            $table->string('url');
            $table->string('sepio_file_name')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'doc_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
    }
};
