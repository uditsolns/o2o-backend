<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->boolean('is_routing_inconclusive')
                ->default(false)
                ->after('transportation_status')
                ->comment('Set when Kpler returns routing_data_inconclusive');

            $table->timestamp('transportation_status_updated_at')
                ->nullable()
                ->after('is_routing_inconclusive')
                ->comment('When transportation_status last changed');
        });
    }

    public function down(): void
    {
        Schema::table('trip_container_tracking', function (Blueprint $table) {
            $table->dropColumn(['is_routing_inconclusive', 'transportation_status_updated_at']);
        });
    }
};
