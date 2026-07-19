<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeSubscription = Subscription::with(['plan', 'template', 'doctor', 'preferences.mealOption', 'dailySelections.mealOption'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending', 'paused'])
            ->latest()
            ->first();

        $todayMeals = collect();
        $upcomingMeals = collect();

        if ($activeSubscription) {
            $todayMeals = $activeSubscription->dailySelections()
                ->with('mealOption')
                ->whereDate('meal_date', today())
                ->orderBy('meal_type')
                ->get();

            $upcomingMeals = $activeSubscription->dailySelections()
                ->with('mealOption')
                ->whereDate('meal_date', '>', today())
                ->orderBy('meal_date')
                ->orderBy('meal_type')
                ->take(12)
                ->get();
        }

        $recentConsultations = Consultation::with(['doctor', 'payment'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $recentPayments = Payment::whereHasMorph('payable', [Subscription::class, Consultation::class], function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->latest()->take(10)->get();

        $recentOrders = Order::with('items')
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        $subscriptionCount = Subscription::where('user_id', $user->id)->count();
        $activeCount = Subscription::where('user_id', $user->id)->where('status', 'active')->count();
        $consultationCount = Consultation::where('user_id', $user->id)->count();
        $orderCount = Order::where('user_id', $user->id)->count();
        $pendingPaymentCount = Payment::where('status', 'pending')
            ->whereHasMorph('payable', [Subscription::class, Consultation::class], fn ($q) => $q->where('user_id', $user->id))
            ->count();
        $paidPaymentCount = Payment::where('status', 'paid')
            ->whereHasMorph('payable', [Subscription::class, Consultation::class], fn ($q) => $q->where('user_id', $user->id))
            ->count();

        return response()->json([
            'user' => $user,
            'active_subscription' => $activeSubscription,
            'today_meals' => $todayMeals,
            'upcoming_meals' => $upcomingMeals,
            'recent_consultations' => $recentConsultations,
            'recent_payments' => $recentPayments,
            'recent_orders' => $recentOrders,
            'stats' => [
                'total_subscriptions' => $subscriptionCount,
                'active_subscriptions' => $activeCount,
                'total_consultations' => $consultationCount,
                'total_orders' => $orderCount,
                'pending_payments' => $pendingPaymentCount,
                'paid_payments' => $paidPaymentCount,
                'upcoming_meals' => $upcomingMeals->count(),
            ],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,paid,failed,refunded,cancelled',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $payments = Payment::whereHasMorph('payable', [Subscription::class, Consultation::class], function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return response()->json(['payments' => $payments]);
    }

    public function consultations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'nullable|in:pending,paid,requested,assigned,scheduled,completed,cancelled',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $consultations = Consultation::with(['doctor', 'payment'])
            ->where('user_id', $request->user()->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        return response()->json(['consultations' => $consultations]);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $request->user()->id,
            'address_line_1' => 'sometimes|string|max:255',
            'address_line_2' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'pincode' => 'sometimes|string|size:6',
        ]);

        $request->user()->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $request->user()->fresh(),
        ]);
    }
}
