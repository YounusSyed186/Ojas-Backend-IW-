<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationFee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultationController extends Controller
{
    /**
     * List user's consultations
     */
    public function index(Request $request): JsonResponse
    {
        $consultations = Consultation::with(['doctor', 'fee', 'payment'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json(['consultations' => $consultations]);
    }

    /**
     * Book a new consultation
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'doctor_id' => 'nullable|exists:users,id',
            'preferred_slot_at' => 'nullable|date',
            'request_notes' => 'nullable|string|max:1000',
        ]);

        // Get active consultation fee
        $fee = ConsultationFee::where('is_active', true)->first();
        if (!$fee) {
            return response()->json([
                'message' => 'Consultation fees not configured. Please contact support.',
            ], 400);
        }

        $consultation = Consultation::create([
            'user_id' => $request->user()->id,
            'doctor_id' => $validated['doctor_id'] ?? null,
            'consultation_fee_id' => $fee->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'preferred_slot_at' => $validated['preferred_slot_at'] ?? null,
            'request_notes' => $validated['request_notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Consultation booked successfully.',
            'consultation' => $consultation->load(['fee']),
            'fee' => $fee,
        ], 201);
    }

    /**
     * Get consultation details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $consultation = Consultation::with(['doctor', 'fee', 'payment', 'customer'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['consultation' => $consultation]);
    }

    /**
     * Cancel a consultation
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $consultation = Consultation::where('user_id', $request->user()->id)
            ->whereIn('status', ['pending'])
            ->findOrFail($id);

        $consultation->update([
            'status' => 'cancelled',
            'payment_status' => 'refunded',
        ]);

        return response()->json([
            'message' => 'Consultation cancelled successfully.',
            'consultation' => $consultation,
        ]);
    }

    /**
     * Get available doctors for booking
     */
    public function availableDoctors(): JsonResponse
    {
        $doctors = User::where('role', 'doctor')
            ->where('status', 'active')
            ->get(['id', 'name', 'email']);

        return response()->json([
            'doctors' => $doctors,
        ]);
    }

    /**
     * Get current consultation fee
     */
    public function fee(): JsonResponse
    {
        $fee = ConsultationFee::where('is_active', true)->first();

        if (!$fee) {
            return response()->json([
                'message' => 'Consultation fees not configured.',
            ], 404);
        }

        return response()->json([
            'fee' => $fee,
        ]);
    }
}
