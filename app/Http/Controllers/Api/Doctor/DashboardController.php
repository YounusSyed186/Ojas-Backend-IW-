<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\CustomerMealPlan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user();

        $assignedCount = Consultation::where('doctor_id', $doctor->id)->count();
        $availableCount = Consultation::whereNull('doctor_id')
            ->whereIn('status', ['requested', 'pending'])
            ->where('payment_status', 'paid')
            ->count();
        $scheduledToday = Consultation::where('doctor_id', $doctor->id)
            ->whereDate('scheduled_for', today())
            ->count();
        $completedThisWeek = Consultation::where('doctor_id', $doctor->id)
            ->whereIn('status', ['completed', 'plan_assigned'])
            ->where('updated_at', '>=', now()->startOfWeek())
            ->count();

        $recentAssigned = Consultation::with(['customer:id,name,phone,pincode', 'payment'])
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->take(5)
            ->get();

        $availableRequests = Consultation::with(['customer:id,name,phone', 'payment'])
            ->whereNull('doctor_id')
            ->whereIn('status', ['requested', 'pending'])
            ->where('payment_status', 'paid')
            ->latest()
            ->take(5)
            ->get();

        $activeSubscriptions = Subscription::with(['customer:id,name', 'plan:id,name'])
            ->where('doctor_id', $doctor->id)
            ->where('status', 'active')
            ->latest()
            ->take(5)
            ->get();

        $recentMealPlans = CustomerMealPlan::with(['customer:id,name', 'template:id,name'])
            ->where('assigned_by', $doctor->id)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'stats' => [
                'assigned_consultations' => $assignedCount,
                'available_requests' => $availableCount,
                'scheduled_today' => $scheduledToday,
                'completed_this_week' => $completedThisWeek,
            ],
            'recent_assigned' => $recentAssigned,
            'available_requests' => $availableRequests,
            'active_subscriptions' => $activeSubscriptions,
            'recent_meal_plans' => $recentMealPlans,
        ]);
    }
}
