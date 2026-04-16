<?php

use App\Enums\SealOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seal_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('ordered_by_id')->constrained('users')->restrictOnDelete();
            $table->string('order_ref', 30)->unique();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('seal_cost', 12, 2);
            $table->decimal('freight_amount', 10, 2)->default(0);
            $table->decimal('gst_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->enum('payment_type', ['cash', 'credit', 'advance_balance'])->default('cash');
            $table->foreignId('billing_location_id')->nullable()->constrained('customer_locations')->nullOnDelete();
            $table->foreignId('shipping_location_id')->nullable()->constrained('customer_locations')->nullOnDelete();
            $table->string('receiver_name')->nullable();
            $table->string('receiver_contact', 20)->nullable();
            $table->enum('status', SealOrderStatus::values())->default(SealOrderStatus::IlPending->value);
            $table->foreignId('il_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('il_approved_at')->nullable();
            $table->text('il_remarks')->nullable();
            $table->text('il_remark_file_url')->nullable();
            $table->string('sepio_order_id', 100)->nullable()->unique();
            $table->string('sepio_billing_address_id', 100)->nullable();
            $table->string('sepio_shipping_address_id', 100)->nullable();
            $table->json('sepio_order_ports')->nullable();
            $table->string('courier_name')->nullable();
            $table->string('courier_docket_number', 100)->nullable();
            $table->timestamp('seals_dispatched_at')->nullable();
            $table->timestamp('seals_delivered_at')->nullable();
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // TODO: use seals_dispatched_at & seals_delivered_at columns

            $table->index(['customer_id', 'status']);
            $table->index('sepio_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seal_orders');
    }
};
