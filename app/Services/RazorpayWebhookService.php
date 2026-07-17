<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

class RazorpayWebhookService
{
    public function __construct(private OrderCheckoutService $checkout, private OrderRefundService $refunds) {}

    public function process(PaymentWebhookEvent $event): void
    {
        if ($event->status === 'processed') return;
        try {
            match ($event->event_type) {
                'order.paid', 'payment.captured' => $this->captured($event->payload),
                'payment.failed' => $this->failed($event->payload),
                'refund.processed', 'refund.failed', 'refund.reversed' => $this->refundUpdated($event->payload),
                default => null,
            };
            $event->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $event->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function captured(array $payload): void
    {
        $entity = data_get($payload, 'payload.payment.entity', []);
        $gatewayOrderId = $entity['order_id'] ?? data_get($payload, 'payload.order.entity.id');
        if (! $gatewayOrderId) return;
        $payment = Payment::where('gateway_order_id', $gatewayOrderId)->first();
        if (! $payment || ! $payment->payable instanceof Order) return;
        $order = $payment->payable;
        $amount = (int) ($entity['amount'] ?? data_get($payload, 'payload.order.entity.amount_paid', 0));
        if ($amount && $amount !== (int) round(((float) $order->grand_total) * 100)) throw new \RuntimeException('Captured amount does not match order total.');
        $currency = strtoupper((string) ($entity['currency'] ?? data_get($payload, 'payload.order.entity.currency', '')));
        if ($currency && $currency !== strtoupper($order->currency)) throw new \RuntimeException('Captured currency does not match order currency.');
        DB::transaction(function () use ($payment, $order, $entity) {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment->refresh()->update(['gateway_payment_id' => $entity['id'] ?? $payment->gateway_payment_id,
                'status' => 'paid', 'paid_at' => now(), 'payload' => array_merge($payment->payload ?? [], ['webhook_payment' => $entity])]);
            if ($lockedOrder->status !== 'abandoned') $this->checkout->markPaid($lockedOrder);
        });
        $order->refresh();
        if ($order->status === 'abandoned') $this->refunds->refund($order, reason: 'Automatic refund for late capture after checkout abandonment');
    }

    private function failed(array $payload): void
    {
        $entity = data_get($payload, 'payload.payment.entity', []);
        $payment = Payment::where('gateway_order_id', $entity['order_id'] ?? null)->first();
        if (! $payment || $payment->status === 'paid') return;
        $payment->update(['status' => 'failed', 'failure_code' => $entity['error_code'] ?? 'payment_failed',
            'failure_description' => $entity['error_description'] ?? null,
            'payload' => array_merge($payment->payload ?? [], ['webhook_payment' => $entity])]);
        if ($payment->payable instanceof Order) {
            $this->checkout->reconcileFailure(
                $payment->payable,
                $entity['error_code'] ?? 'payment_failed',
                $entity['error_description'] ?? null,
            );
        }
    }

    private function refundUpdated(array $payload): void
    {
        $entity = data_get($payload, 'payload.refund.entity', []);
        $refund = PaymentRefund::where('gateway_refund_id', $entity['id'] ?? null)->first();
        if (! $refund) return;
        $status = $entity['status'] ?? 'pending';
        $refund->update(['status' => $status, 'payload' => array_merge($refund->payload ?? [], ['webhook_refund' => $entity]),
            'processed_at' => $status === 'processed' ? now() : null]);
        if ($refund->payment->payable instanceof Order) $this->refunds->syncOrderRefundStatus($refund->payment->payable);
    }
}
