<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $plans = $this->applyListQuery(
            SubscriptionPlan::query()->with('template:id,name'),
            $request,
            ['name', 'description', 'period'],
            ['is_active', 'period'],
        );

        return response()->json(['plans' => $plans]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'period' => 'sometimes|string|max:50',
            'price' => 'sometimes|nullable|numeric|min:0',
            'meal_plan_template_id' => 'sometimes|nullable|exists:meal_plan_templates,id',
            'features' => 'sometimes|nullable|array',
            'featured' => 'sometimes|boolean',
            'badge' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update($validated);

        return response()->json([
            'message' => 'Plan updated successfully.',
            'plan' => $plan->fresh('template'),
        ]);
    }
}
