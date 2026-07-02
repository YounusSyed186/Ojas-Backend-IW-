<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\CustomerMealPlan;
use App\Models\MealPlanTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $doctor = $request->user();
        $scope = $request->query('scope', 'assigned');

        $query = Consultation::query()->with(['customer:id,name,email,phone,pincode', 'payment']);

        if ($scope === 'available') {
            $query->whereNull('doctor_id')
                ->whereIn('status', ['requested', 'pending'])
                ->where('payment_status', 'paid');
        } else {
            $query->where(function ($builder) use ($doctor): void {
                $builder->where('doctor_id', $doctor->id)
                    ->orWhereNull('doctor_id');
            });
        }

        $consultations = $this->applyListQuery(
            $query,
            $request,
            ['customer.name', 'status'],
            ['status', 'payment_status'],
        );

        return response()->json(['consultations' => $consultations]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $doctor = $request->user();

        $consultation = Consultation::with(['customer', 'payment', 'fee'])
            ->where(function ($query) use ($doctor): void {
                $query->where('doctor_id', $doctor->id)
                    ->orWhereNull('doctor_id');
            })
            ->findOrFail($id);

        $healthProfile = $consultation->customer?->subscriptions()
            ->latest()
            ->first(['health_details', 'delivery_pincode', 'status']);

        return response()->json([
            'consultation' => $consultation,
            'customer_health_profile' => $healthProfile,
        ]);
    }

    public function accept(Request $request, int $id): JsonResponse
    {
        $doctor = $request->user();

        $consultation = Consultation::whereNull('doctor_id')
            ->whereIn('status', ['requested', 'pending'])
            ->findOrFail($id);

        $consultation->update([
            'doctor_id' => $doctor->id,
            'status' => 'requested',
        ]);

        return response()->json([
            'message' => 'Consultation accepted successfully.',
            'consultation' => $consultation->fresh(['customer', 'payment']),
        ]);
    }

    public function schedule(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'scheduled_for' => 'required|date|after:now',
        ]);

        $consultation = $this->findOwnedConsultation($request, $id);
        $consultation->update([
            'scheduled_for' => $validated['scheduled_for'],
            'status' => 'scheduled',
        ]);

        return response()->json([
            'message' => 'Consultation scheduled successfully.',
            'consultation' => $consultation->fresh(['customer', 'payment']),
        ]);
    }

    public function addNotes(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'doctor_notes' => 'required|string',
            'mark_completed' => 'sometimes|boolean',
        ]);

        $consultation = $this->findOwnedConsultation($request, $id);
        $consultation->update([
            'doctor_notes' => $validated['doctor_notes'],
            'status' => ($validated['mark_completed'] ?? true) ? 'completed' : $consultation->status,
        ]);

        return response()->json([
            'message' => 'Notes saved successfully.',
            'consultation' => $consultation->fresh(['customer', 'payment']),
        ]);
    }

    public function assignPlan(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'meal_plan_template_id' => 'required|exists:meal_plan_templates,id',
        ]);

        $consultation = $this->findOwnedConsultation($request, $id);

        MealPlanTemplate::findOrFail($validated['meal_plan_template_id']);

        CustomerMealPlan::updateOrCreate(
            [
                'user_id' => $consultation->user_id,
                'meal_plan_template_id' => $validated['meal_plan_template_id'],
            ],
            [
                'assigned_by' => $request->user()->id,
                'assigned_on' => now()->toDateString(),
                'is_active' => true,
            ]
        );

        $consultation->update(['status' => 'plan_assigned']);

        return response()->json([
            'message' => 'Meal plan assigned successfully.',
            'consultation' => $consultation->fresh(['customer', 'payment']),
        ]);
    }

    public function markCompleted(Request $request, int $id): JsonResponse
    {
        $consultation = $this->findOwnedConsultation($request, $id);
        $consultation->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Consultation marked as completed.',
            'consultation' => $consultation->fresh(['customer', 'payment']),
        ]);
    }

    public function mealTemplates(): JsonResponse
    {
        $templates = MealPlanTemplate::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json(['templates' => $templates]);
    }

    private function findOwnedConsultation(Request $request, int $id): Consultation
    {
        return Consultation::where('doctor_id', $request->user()->id)->findOrFail($id);
    }
}
