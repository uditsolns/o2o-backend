<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Seal usage toggle — null means "not decided", false = no seal, true = sepio seal
            $table->boolean('uses_sepio_seal')->default(false)->after('seal_id');

            // Shipping bill — separate from invoice and eway bill
            $table->string('shipping_bill_no', 20)->nullable()->after('eway_bill_validity_date');
            $table->date('shipping_bill_date')->nullable()->after('shipping_bill_no');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['uses_sepio_seal', 'shipping_bill_no', 'shipping_bill_date']);
        });
    }
};
