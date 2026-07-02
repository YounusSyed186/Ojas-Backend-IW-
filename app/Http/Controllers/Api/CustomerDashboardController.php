<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $activeSubscription = Subscription::with(['plan', 'template', 'preferences.mealOption', 'dailySelections.mealOption'])
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'pending'])
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

        $subscriptionCount = Subscription::where('user_id', $user->id)->count();
        $activeCount = Subscription::where('user_id', $user->id)->where('status', 'active')->count();

        return response()->json([
            'user' => $user,
            'active_subscription' => $activeSubscription,
            'today_meals' => $todayMeals,
            'upcoming_meals' => $upcomingMeals,
            'recent_consultations' => $recentConsultations,
            'recent_payments' => $recentPayments,
            'stats' => [
                'total_subscriptions' => $subscriptionCount,
                'active_subscriptions' => $activeCount,
                'pending_payments' => $recentPayments->where('status', 'pending')->count(),
                'paid_payments' => $recentPayments->where('status', 'paid')->count(),
            ],
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $payments = Payment::whereHasMorph('payable', [Subscription::class, Consultation::class], function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })->latest()->paginate(20);

        return response()->json(['payments' => $payments]);
    }

    public function consultations(Request $request): JsonResponse
    {
        $consultations = Consultation::with(['doctor', 'payment'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

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
