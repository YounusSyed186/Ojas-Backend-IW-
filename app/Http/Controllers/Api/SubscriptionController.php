<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::with(['plan', 'template', 'preferences'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['subscriptions' => $subscriptions]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::with(['plan', 'template', 'preferences', 'dailySelections'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['subscription' => $subscription]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'delivery_pincode' => 'required|string|size:6',
            'delivery_address_line_1' => 'nullable|string|max:255',
            'delivery_address_line_2' => 'nullable|string|max:255',
            'delivery_city' => 'nullable|string|max:120',
            'delivery_state' => 'nullable|string|max:120',
            'meal_preferences' => 'nullable|array',
            'meal_preferences.*.meal_type' => 'required|string',
            'meal_preferences.*.meal_option_id' => 'required|exists:meal_options,id',
            'health_details' => 'nullable|array',
            'health_details.age' => 'nullable|integer|min:1|max:150',
            'health_details.weight' => 'nullable|numeric|min:20|max:300',
            'health_details.height' => 'nullable|numeric|min:50|max:300',
            'health_details.goal' => 'nullable|string',
            'health_details.allergies' => 'nullable|string',
            'health_details.medical_conditions' => 'nullable|string',
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);

        $subscription = $this->subscriptionService->createSubscriptionFromPlan(
            $request->user(),
            $plan,
            $validated['delivery_pincode']
        );

        $subscription->update([
            'health_details' => $validated['health_details'] ?? null,
            'delivery_address_line_1' => $validated['delivery_address_line_1'] ?? null,
            'delivery_address_line_2' => $validated['delivery_address_line_2'] ?? null,
            'delivery_city' => $validated['delivery_city'] ?? null,
            'delivery_state' => $validated['delivery_state'] ?? null,
        ]);

        // Store meal preferences if provided
        if (!empty($validated['meal_preferences'])) {
            foreach ($validated['meal_preferences'] as $pref) {
                $subscription->preferences()->create([
                    'meal_type' => $pref['meal_type'],
                    'meal_option_id' => $pref['meal_option_id'],
                ]);
            }
        }

        return response()->json([
            'message' => 'Subscription created successfully.',
            'subscription' => $subscription->load(['plan', 'preferences']),
        ], 201);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'pending'])
            ->findOrFail($id);

        $subscription->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Subscription cancelled successfully.',
            'subscription' => $subscription,
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($id);

        $subscription->update(['status' => 'paused']);

        return response()->json([
            'message' => 'Subscription paused successfully.',
            'subscription' => $subscription->fresh(),
        ]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::where('user_id', $request->user()->id)
            ->where('status', 'paused')
            ->findOrFail($id);

        $subscription->update(['status' => 'active']);

        return response()->json([
            'message' => 'Subscription resumed successfully.',
            'subscription' => $subscription->fresh(),
        ]);
    }
}
