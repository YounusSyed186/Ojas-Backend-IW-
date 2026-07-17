<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentRefund;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OrderRefundService
{
    public function __construct(private CartService $carts) {}

    public function refund(Order $order, ?OrderItem $item = null, ?User $admin = null, string $reason = 'Order cancelled'): PaymentRefund
    {
        if ($item && $item->order_id !== $order->id) abort(404);
        [$minimum] = $this->carts->deliveryWindow();
        $cancellableItems = $item ? collect([$item]) : $order->items()->whereNotIn('fulfillment_status', ['delivered', 'cancelled'])->get();
        if ($cancellableItems->isEmpty()) throw ValidationException::withMessages(['order_item' => 'There are no cancellable deliveries on this order.']);
        if ($cancellableItems->contains(fn (OrderItem $line) => $line->delivery_date->toDateString() < $minimum)) {
            throw ValidationException::withMessages(['order_item' => 'This delivery is past its cancellation cutoff.']);
        }
        $payment = $order->payments()->where('status', 'paid')->latest()->first();
        if (! $payment?->gateway_payment_id) throw ValidationException::withMessages(['payment' => 'A captured Razorpay payment is required for refund.']);
        $key = hash('sha256', 'refund:'.($item ? 'item:'.$item->id : 'order:'.$order->id));
        if ($existing = PaymentRefund::where('idempotency_key', $key)->first()) return $existing;
        $alreadyAllocatedPaise = (int) round(((float) $order->payments()->with('refunds')->get()
            ->flatMap->refunds->whereIn('status', ['pending', 'processed'])->sum('amount')) * 100);
        $requestedPaise = (int) round(((float) ($item?->line_total ?? $order->grand_total)) * 100);
        $amountPaise = $item ? $requestedPaise : max(0, $requestedPaise - $alreadyAllocatedPaise);
        if ($amountPaise <= 0) throw ValidationException::withMessages(['refund' => 'This order has already been fully allocated for refund.']);
        if ($item && $alreadyAllocatedPaise + $amountPaise > (int) round(((float) $order->grand_total) * 100)) {
            throw ValidationException::withMessages(['refund' => 'This refund would exceed the captured payment.']);
        }
        $amount = $amountPaise / 100;
        $refund = PaymentRefund::create([
            'payment_id' => $payment->id, 'order_item_id' => $item?->id, 'requested_by' => $admin?->id,
            'idempotency_key' => $key, 'amount' => $amount, 'status' => 'pending', 'reason' => $reason,
        ]);
        try {
            $response = Http::withBasicAuth(config('services.razorpay.key_id'), config('services.razorpay.key_secret'))
                ->acceptJson()->asJson()->post("https://api.razorpay.com/v1/payments/{$payment->gateway_payment_id}/refund", [
                    'amount' => $amountPaise, 'receipt' => substr($key, 0, 40),
                    'notes' => ['order_number' => $order->order_number, 'reason' => $reason],
                ])->throw()->json();
            $refund->update(['gateway_refund_id' => $response['id'] ?? null, 'status' => $response['status'] ?? 'pending',
                'payload' => $response, 'processed_at' => ($response['status'] ?? null) === 'processed' ? now() : null]);
        } catch (\Throwable $e) {
            $refund->update(['status' => 'failed', 'payload' => ['error' => $e->getMessage()]]);
            throw ValidationException::withMessages(['refund' => 'Razorpay could not initiate the refund.']);
        }
        if ($item) {
            $item->update(['fulfillment_status' => 'cancelled', 'cancelled_at' => now()]);
        } else {
            $order->items()->whereNotIn('fulfillment_status', ['delivered', 'cancelled'])->update(['fulfillment_status' => 'cancelled', 'cancelled_at' => now()]);
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }
        $this->syncOrderRefundStatus($order);
        return $refund->fresh();
    }

    public function syncOrderRefundStatus(Order $order): void
    {
        $refunds = $order->load('payments.refunds')->payments->flatMap->refunds;
        $refunded = $refunds->where('status', 'processed')->sum(fn ($refund) => (float) $refund->amount);
        if ($refunded >= (float) $order->grand_total) $order->update(['payment_status' => 'refunded']);
        elseif ($refunds->where('status', 'pending')->isNotEmpty()) $order->update(['payment_status' => 'refund_pending']);
        elseif ($refunded > 0) $order->update(['payment_status' => 'partially_refunded']);
    }
}
