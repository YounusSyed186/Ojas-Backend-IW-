<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\ConsultationFee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    public function create(): View
    {
        return view('customer.consultations.create', [
            'fee' => ConsultationFee::query()->where('is_active', true)->latest()->first(),
        ]);
    }

    public function store(Request $request, PaymentGatewayInterface $paymentGateway): RedirectResponse
    {
        $validated = $request->validate([
            'preferred_slot_at' => ['required', 'date', 'after:now'],
            'request_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $fee = ConsultationFee::query()->where('is_active', true)->latest()->firstOrFail();

        $consultation = Consultation::create([
            'user_id' => $request->user()->id,
            'consultation_fee_id' => $fee->id,
            'preferred_slot_at' => $validated['preferred_slot_at'],
            'request_notes' => $validated['request_notes'] ?? null,
            'status' => 'requested',
            'payment_status' => 'paid',
        ]);

        $paymentGateway->charge($consultation, (float) $fee->amount, $fee->currency, [
            'type' => 'consultation',
        ]);

        return redirect()->route('customer.dashboard')->with('status', 'Consultation booked and payment recorded.');
    }
}
