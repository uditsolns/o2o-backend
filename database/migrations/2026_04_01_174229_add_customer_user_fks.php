<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('il_approved_by_id')->nullable()->after('il_remarks')->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->after('il_approved_by_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['il_approved_by_id']);
            $table->dropForeign(['created_by_id']);
            $table->dropColumn(['il_approved_by_id', 'created_by_id']);
        });
    }
};
