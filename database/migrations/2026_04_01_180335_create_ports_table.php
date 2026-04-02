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
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->default('India');
            $table->enum('port_category', PortCategory::values());
            $table->integer('sepio_id')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->integer('geo_fence_radius')->default(2000);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};
