<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $subscriptions = $this->applyListQuery(
            Subscription::query()->with(['customer:id,name,email', 'plan:id,name', 'template:id,name', 'payment']),
            $request,
            ['customer.name', 'status', 'delivery_pincode'],
            ['status', 'period'],
        );

        return response()->json(['subscriptions' => $subscriptions]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,active,paused,cancelled,expired',
            'delivery_pincode' => 'sometimes|string|size:6',
        ]);

        $subscription = Subscription::findOrFail($id);
        $subscription->update($validated);

        return response()->json([
            'message' => 'Subscription updated successfully.',
            'subscription' => $subscription->fresh(['customer', 'plan', 'template', 'payment']),
        ]);
    }
}
