<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\DailyMealSelection;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $revenue = Payment::where('status', 'paid')->sum('amount');
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $todayDeliveries = DailyMealSelection::whereDate('meal_date', today())->count();
        $pendingConsultations = Consultation::whereIn('status', ['pending', 'requested'])->count();
        $failedPayments = Payment::where('status', 'failed')->count();

        $topMealPlans = Subscription::query()
            ->select('meal_plan_template_id', DB::raw('count(*) as subscriptions_count'))
            ->whereNotNull('meal_plan_template_id')
            ->groupBy('meal_plan_template_id')
            ->orderByDesc('subscriptions_count')
            ->with('template:id,name')
            ->limit(5)
            ->get();

        $doctorPerformance = Consultation::query()
            ->select('doctor_id', DB::raw('count(*) as completed_count'))
            ->whereIn('status', ['completed', 'plan_assigned'])
            ->whereNotNull('doctor_id')
            ->groupBy('doctor_id')
            ->orderByDesc('completed_count')
            ->with('doctor:id,name')
            ->limit(5)
            ->get();

        $customerGrowth = User::where('role', 'customer')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return response()->json([
            'stats' => [
                'revenue' => (float) $revenue,
                'active_subscriptions' => $activeSubscriptions,
                'today_deliveries' => $todayDeliveries,
                'pending_consultations' => $pendingConsultations,
                'failed_payments' => $failedPayments,
                'customer_growth' => $customerGrowth,
            ],
            'top_meal_plans' => $topMealPlans,
            'doctor_performance' => $doctorPerformance,
        ]);
    }
}
