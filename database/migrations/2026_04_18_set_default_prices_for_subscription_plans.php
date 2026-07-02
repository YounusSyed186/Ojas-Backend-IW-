<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $pricingMap = [
            'one_day' => 499,
            'weekly' => 2999,
            'monthly' => 10999,
            'quarterly' => 29999,
        ];

        foreach ($pricingMap as $period => $price) {
            DB::table('subscription_plans')
                ->where('period', $period)
                ->where('price', 0)
                ->update(['price' => $price]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('subscription_plans')->update(['price' => 0]);
    }
};
