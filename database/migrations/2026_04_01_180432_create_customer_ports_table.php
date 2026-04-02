<?php

use App\Enums\PortCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('port_id')->constrained()->restrictOnDelete();
            $table->enum('port_category', PortCategory::values());
            $table->string('name');
            $table->string('code', 20);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->integer('geo_fence_radius')->default(2000);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['customer_id', 'port_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_ports');
    }
};
