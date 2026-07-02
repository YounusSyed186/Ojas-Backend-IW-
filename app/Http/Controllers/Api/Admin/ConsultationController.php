<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Concerns\AppliesListQuery;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    use AppliesListQuery;

    public function index(Request $request): JsonResponse
    {
        $consultations = $this->applyListQuery(
            Consultation::query()->with(['customer:id,name,email,phone', 'doctor:id,name', 'payment']),
            $request,
            ['customer.name', 'doctor.name', 'status', 'payment_status'],
            ['status', 'payment_status'],
        );

        return response()->json(['consultations' => $consultations]);
    }

    public function assignDoctor(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:users,id',
        ]);

        $doctor = User::where('id', $validated['doctor_id'])
            ->where('role', 'doctor')
            ->where('status', 'active')
            ->firstOrFail();

        $consultation = Consultation::findOrFail($id);
        $consultation->update([
            'doctor_id' => $doctor->id,
            'status' => $consultation->status === 'pending' ? 'requested' : $consultation->status,
        ]);

        return response()->json([
            'message' => 'Doctor assigned successfully.',
            'consultation' => $consultation->fresh(['customer', 'doctor', 'payment']),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:pending,requested,scheduled,completed,cancelled,plan_assigned',
            'payment_status' => 'sometimes|string|in:pending,paid,refunded,failed',
            'scheduled_for' => 'sometimes|nullable|date',
        ]);

        $consultation = Consultation::findOrFail($id);
        $consultation->update($validated);

        return response()->json([
            'message' => 'Consultation updated successfully.',
            'consultation' => $consultation->fresh(['customer', 'doctor', 'payment']),
        ]);
    }
}
