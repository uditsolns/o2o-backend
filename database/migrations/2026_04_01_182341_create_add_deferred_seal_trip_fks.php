<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seals', function (Blueprint $table) {
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->foreign('seal_id')->references('id')->on('seals')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['seal_id']);
        });
        Schema::table('seals', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });
    }
};
