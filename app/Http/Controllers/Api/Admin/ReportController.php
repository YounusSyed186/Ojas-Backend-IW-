<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        $revenueTotal = Payment::where('status', 'paid')->sum('amount');
        $revenueThisMonth = Payment::where('status', 'paid')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount');

        $subscriptionsByPlan = SubscriptionPlan::query()
            ->select('id', 'name')
            ->withCount('subscriptions')
            ->orderByDesc('subscriptions_count')
            ->get();

        $consultationsByStatus = Consultation::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $paymentsByStatus = Payment::query()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $topDoctors = Consultation::query()
            ->select('doctor_id', DB::raw('count(*) as completed_count'))
            ->whereIn('status', ['completed', 'plan_assigned'])
            ->whereNotNull('doctor_id')
            ->groupBy('doctor_id')
            ->orderByDesc('completed_count')
            ->with('doctor:id,name')
            ->limit(10)
            ->get();

        $topPlans = Subscription::query()
            ->select('subscription_plan_id', DB::raw('count(*) as subscriptions_count'))
            ->whereNotNull('subscription_plan_id')
            ->groupBy('subscription_plan_id')
            ->orderByDesc('subscriptions_count')
            ->with('plan:id,name')
            ->limit(10)
            ->get();

        $customerGrowth = User::where('role', 'customer')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $totalCustomers = User::where('role', 'customer')->count();
        $totalDoctors = User::where('role', 'doctor')->count();
        $totalUsers = User::count();

        return response()->json([
            'revenue' => [
                'total' => (float) $revenueTotal,
                'this_month' => (float) $revenueThisMonth,
            ],
            'subscriptions_by_plan' => $subscriptionsByPlan,
            'consultations_by_status' => $consultationsByStatus,
            'payments_by_status' => $paymentsByStatus,
            'top_doctors' => $topDoctors,
            'top_plans' => $topPlans,
            'customer_growth' => $customerGrowth,
            'total_customers' => $totalCustomers,
            'total_doctors' => $totalDoctors,
            'total_users' => $totalUsers,
        ]);
    }
}