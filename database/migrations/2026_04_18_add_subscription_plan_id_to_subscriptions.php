<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'subscription_plan_id')) {
                $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete()->after('user_id');
            }
        });

        // Migrate existing data: create subscription plans from existing subscription records
        $subscriptions = DB::table('subscriptions')->distinct()->get(['meal_plan_template_id', 'period']);
        
        foreach ($subscriptions as $subscription) {
            if ($subscription->meal_plan_template_id || $subscription->period) {
                $existingPlan = DB::table('subscription_plans')
                    ->where('meal_plan_template_id', $subscription->meal_plan_template_id)
                    ->where('period', $subscription->period)
                    ->first();

                if (!$existingPlan) {
                    $planId = DB::table('subscription_plans')->insertGetId([
                        'name' => "Plan - {$subscription->period}",
                        'description' => null,
                        'meal_plan_template_id' => $subscription->meal_plan_template_id,
                        'period' => $subscription->period,
                        'is_active' => true,
                        'created_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $planId = $existingPlan->id;
                }

                // Link existing subscriptions to the plan
                DB::table('subscriptions')
                    ->where('meal_plan_template_id', $subscription->meal_plan_template_id)
                    ->where('period', $subscription->period)
                    ->update(['subscription_plan_id' => $planId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeignIdFor('subscription_plans');
            $table->dropColumn('subscription_plan_id');
        });
    }
};
