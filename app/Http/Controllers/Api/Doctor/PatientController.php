<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\CustomerMealPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user();
        $search = $request->query('search');
        $page = max((int) $request->query('page', 1), 1);
        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        // Get unique customer IDs from consultations assigned to this doctor
        $customerIds = Consultation::where('doctor_id', $doctor->id)
            ->pluck('user_id')
            ->unique();

        // Also get customer IDs from subscriptions assigned to this doctor
        $subscriptionCustomerIds = Subscription::where('doctor_id', $doctor->id)
            ->pluck('user_id')
            ->unique();

        $customerIds = $customerIds->merge($subscriptionCustomerIds)->unique();

        $query = User::whereIn('id', $customerIds)
            ->where(function (Builder $q) use ($search): void {
                if ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                }
            })
            ->orderBy('name');

        $patients = $query->paginate($perPage, ['id', 'name', 'email', 'phone', 'status'], 'page', $page);

        // Augment with subscription/meal plan info
        $patients->getCollection()->transform(function (User $customer) use ($doctor) {
            $subscription = Subscription::where('user_id', $customer->id)
                ->where('doctor_id', $doctor->id)
                ->latest()
                ->first(['status', 'start_date', 'end_date']);

            $mealPlan = CustomerMealPlan::with('template:id,name')
                ->where('user_id', $customer->id)
                ->where('assigned_by', $doctor->id)
                ->latest()
                ->first();

            $lastConsultation = Consultation::where('user_id', $customer->id)
                ->where('doctor_id', $doctor->id)
                ->latest('created_at')
                ->first(['created_at']);

            $upcomingConsultation = Consultation::where('user_id', $customer->id)
                ->where('doctor_id', $doctor->id)
                ->whereIn('status', ['scheduled'])
                ->where('scheduled_for', '>=', now())
                ->latest('scheduled_for')
                ->first(['scheduled_for']);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status,
                'subscription_status' => $subscription?->status,
                'current_meal_plan' => $mealPlan?->template?->name,
                'last_consultation' => $lastConsultation?->created_at,
                'upcoming_consultation' => $upcomingConsultation?->scheduled_for,
            ];
        });

        return response()->json(['patients' => $patients]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $doctor = $request->user();

        $customer = User::findOrFail($id);

        $subscription = Subscription::with('plan:id,name')
            ->where('user_id', $customer->id)
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->first();

        $mealPlan = CustomerMealPlan::with('template:id,name')
            ->where('user_id', $customer->id)
            ->where('assigned_by', $doctor->id)
            ->latest()
            ->first();

        $consultations = Consultation::where('user_id', $customer->id)
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->get(['id', 'status', 'doctor_notes', 'created_at']);

        return response()->json([
            'patient' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status,
            ],
            'subscription' => $subscription,
            'meal_plan' => $mealPlan,
            'consultations' => $consultations,
        ]);
    }
}