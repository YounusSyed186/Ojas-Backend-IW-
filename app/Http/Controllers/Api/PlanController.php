<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->with('template:id,name')
            ->orderBy('price')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => $this->formatPlan($plan));

        return response()->json([
            'plans' => $plans,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $plan = SubscriptionPlan::with('template')->find($id);

        if (! $plan) {
            return response()->json(['message' => 'Plan not found.'], 404);
        }

        return response()->json([
            'plan' => $this->formatPlan($plan),
        ]);
    }

    private function formatPlan(SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'desc' => $plan->description,
            'long' => $plan->description,
            'period' => $plan->period,
            'price' => $plan->price,
            'meal_plan_template_id' => $plan->meal_plan_template_id,
            'features' => $plan->features ?? [],
            'featured' => (bool) ($plan->featured ?? false),
            'badge' => $plan->badge,
            'is_active' => $plan->is_active,
            'template' => $plan->relationLoaded('template') ? $plan->template : null,
        ];
    }
}
