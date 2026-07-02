<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function createOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
        ]);

        $subscription = Subscription::with('plan')
            ->where('user_id', $request->user()->id)
            ->findOrFail($validated['subscription_id']);

        $existingPaidPayment = Payment::query()
            ->where('payable_type', Subscription::class)
            ->where('payable_id', $subscription->id)
            ->where('gateway', 'razorpay')
            ->where('status', 'paid')
            ->exists();

        if ($subscription->status === 'active' || $existingPaidPayment) {
            throw ValidationException::withMessages([
                'subscription_id' => 'This subscription is already active.',
            ]);
        }

        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');

        if (!$keyId || !$keySecret) {
            throw ValidationException::withMessages([
                'razorpay' => 'Razorpay credentials are not configured.',
            ]);
        }

        $amount = (float) ($subscription->plan?->price ?? 0);

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'This plan does not have a payable Razorpay amount.',
            ]);
        }

        $existingPendingPayment = Payment::query()
            ->where('payable_type', Subscription::class)
            ->where('payable_id', $subscription->id)
            ->where('gateway', 'razorpay')
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($existingPendingPayment && isset($existingPendingPayment->payload['razorpay_order'])) {
            return response()->json([
                'order' => $existingPendingPayment->payload['razorpay_order'],
                'key_id' => $keyId,
                'subscription_id' => $subscription->id,
            ]);
        }

        $orderPayload = [
            'amount' => (int) round($amount * 100),
            'currency' => 'INR',
            'receipt' => 'sub_' . $subscription->id . '_' . Str::lower(Str::random(8)),
            'notes' => [
                'subscription_id' => (string) $subscription->id,
                'user_id' => (string) $request->user()->id,
            ],
        ];

        try {
            $order = Http::withBasicAuth($keyId, $keySecret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', $orderPayload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            return response()->json([
                'message' => 'Unable to create Razorpay order.',
                'details' => $exception->response?->json(),
            ], 502);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to create Razorpay order.',
                'details' => config('app.debug') ? $exception->getMessage() : null,
            ], 502);
        }

        $subscription->payment()->create([
            'gateway' => 'razorpay',
            'reference' => $order['id'],
            'amount' => $amount,
            'currency' => $order['currency'] ?? 'INR',
            'status' => 'pending',
            'payload' => [
                'razorpay_order' => $order,
                'request' => $orderPayload,
            ],
        ]);

        return response()->json([
            'order' => $order,
            'key_id' => $keyId,
            'subscription_id' => $subscription->id,
        ]);
    }

    public function verifyPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_id' => 'required|exists:subscriptions,id',
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $subscription = Subscription::where('user_id', $request->user()->id)
            ->findOrFail($validated['subscription_id']);

        $keySecret = config('services.razorpay.key_secret');

        if (!$keySecret) {
            throw ValidationException::withMessages([
                'razorpay' => 'Razorpay credentials are not configured.',
            ]);
        }

        $payment = Payment::query()
            ->where('payable_type', Subscription::class)
            ->where('payable_id', $subscription->id)
            ->where('gateway', 'razorpay')
            ->where('reference', $validated['razorpay_order_id'])
            ->firstOrFail();

        $expectedSignature = hash_hmac(
            'sha256',
            $validated['razorpay_order_id'] . '|' . $validated['razorpay_payment_id'],
            $keySecret
        );

        if (!hash_equals($expectedSignature, $validated['razorpay_signature'])) {
            $payment->update([
                'status' => 'failed',
                'payload' => array_merge($payment->payload ?? [], [
                    'verification' => $validated,
                    'failure_reason' => 'Invalid Razorpay payment signature.',
                ]),
            ]);

            throw ValidationException::withMessages([
                'razorpay_signature' => 'Invalid Razorpay payment signature.',
            ]);
        }

        $payment->update([
            'status' => 'paid',
            'payload' => array_merge($payment->payload ?? [], [
                'verification' => $validated,
            ]),
            'paid_at' => now(),
        ]);

        if ($subscription->status !== 'active') {
            $subscription = $this->subscriptionService->activateSubscription($subscription);
        }

        return response()->json([
            'message' => 'Payment verified successfully.',
            'payment' => $payment->fresh(),
            'subscription' => $subscription->fresh(),
        ]);
    }

    public function createConsultationOrder(Request $request, int $id): JsonResponse
    {
        $consultation = Consultation::with('fee')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $existingPaidPayment = Payment::query()
            ->where('payable_type', Consultation::class)
            ->where('payable_id', $consultation->id)
            ->where('gateway', 'razorpay')
            ->where('status', 'paid')
            ->exists();

        if ($consultation->payment_status === 'paid' || $existingPaidPayment) {
            throw ValidationException::withMessages([
                'consultation_id' => 'This consultation is already paid.',
            ]);
        }

        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');

        if (!$keyId || !$keySecret) {
            throw ValidationException::withMessages([
                'razorpay' => 'Razorpay credentials are not configured.',
            ]);
        }

        $amount = (float) ($consultation->fee?->amount ?? 0);

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => 'This consultation does not have a payable Razorpay amount.',
            ]);
        }

        $existingPendingPayment = Payment::query()
            ->where('payable_type', Consultation::class)
            ->where('payable_id', $consultation->id)
            ->where('gateway', 'razorpay')
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($existingPendingPayment && isset($existingPendingPayment->payload['razorpay_order'])) {
            return response()->json([
                'order' => $existingPendingPayment->payload['razorpay_order'],
                'key_id' => $keyId,
                'consultation_id' => $consultation->id,
            ]);
        }

        $orderPayload = [
            'amount' => (int) round($amount * 100),
            'currency' => $consultation->fee?->currency ?? 'INR',
            'receipt' => 'consult_' . $consultation->id . '_' . Str::lower(Str::random(8)),
            'notes' => [
                'consultation_id' => (string) $consultation->id,
                'user_id' => (string) $request->user()->id,
            ],
        ];

        try {
            $order = Http::withBasicAuth($keyId, $keySecret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', $orderPayload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            return response()->json([
                'message' => 'Unable to create Razorpay order.',
                'details' => $exception->response?->json(),
            ], 502);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Unable to create Razorpay order.',
                'details' => config('app.debug') ? $exception->getMessage() : null,
            ], 502);
        }

        $consultation->payment()->create([
            'gateway' => 'razorpay',
            'reference' => $order['id'],
            'amount' => $amount,
            'currency' => $order['currency'] ?? $orderPayload['currency'],
            'status' => 'pending',
            'payload' => [
                'razorpay_order' => $order,
                'request' => $orderPayload,
            ],
        ]);

        return response()->json([
            'order' => $order,
            'key_id' => $keyId,
            'consultation_id' => $consultation->id,
        ]);
    }

    public function verifyConsultationPayment(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $consultation = Consultation::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $keySecret = config('services.razorpay.key_secret');

        if (!$keySecret) {
            throw ValidationException::withMessages([
                'razorpay' => 'Razorpay credentials are not configured.',
            ]);
        }

        $payment = Payment::query()
            ->where('payable_type', Consultation::class)
            ->where('payable_id', $consultation->id)
            ->where('gateway', 'razorpay')
            ->where('reference', $validated['razorpay_order_id'])
            ->firstOrFail();

        $expectedSignature = hash_hmac(
            'sha256',
            $validated['razorpay_order_id'] . '|' . $validated['razorpay_payment_id'],
            $keySecret
        );

        if (!hash_equals($expectedSignature, $validated['razorpay_signature'])) {
            $payment->update([
                'status' => 'failed',
                'payload' => array_merge($payment->payload ?? [], [
                    'verification' => $validated,
                    'failure_reason' => 'Invalid Razorpay payment signature.',
                ]),
            ]);
            $consultation->update(['payment_status' => 'failed']);

            throw ValidationException::withMessages([
                'razorpay_signature' => 'Invalid Razorpay payment signature.',
            ]);
        }

        $payment->update([
            'status' => 'paid',
            'payload' => array_merge($payment->payload ?? [], [
                'verification' => $validated,
            ]),
            'paid_at' => now(),
        ]);

        $consultation->update([
            'payment_status' => 'paid',
            'status' => $consultation->status === 'pending' ? 'requested' : $consultation->status,
        ]);

        return response()->json([
            'message' => 'Consultation payment verified successfully.',
            'payment' => $payment->fresh(),
            'consultation' => $consultation->fresh(['fee', 'doctor']),
        ]);
    }
}
