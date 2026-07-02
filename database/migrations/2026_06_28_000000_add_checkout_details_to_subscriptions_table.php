<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->json('health_details')->nullable()->after('delivery_pincode');
            $table->string('delivery_address_line_1')->nullable()->after('health_details');
            $table->string('delivery_address_line_2')->nullable()->after('delivery_address_line_1');
            $table->string('delivery_city')->nullable()->after('delivery_address_line_2');
            $table->string('delivery_state')->nullable()->after('delivery_city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'health_details',
                'delivery_address_line_1',
                'delivery_address_line_2',
                'delivery_city',
                'delivery_state',
            ]);
        });
    }
};
