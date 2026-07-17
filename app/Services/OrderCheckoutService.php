<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderCheckoutService
{
    public function __construct(private CartService $carts, private PincodeService $pincodes) {}

    public function checkout(User $user, array $address, int $version): array
    {
        $order = DB::transaction(function () use ($user, $address, $version) {
            $cart = Cart::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($cart->version !== $version) throw ValidationException::withMessages(['version' => 'Cart changed. Refresh and try again.']);
            if ($cart->status !== 'active') throw ValidationException::withMessages(['cart' => 'Cart is already in checkout.']);
            $cart->load(['items.mealOption']);
            if ($cart->items->isEmpty()) throw ValidationException::withMessages(['cart' => 'Cart is empty.']);
            if (! $this->pincodes->isServiceable($address['delivery_pincode'])) throw ValidationException::withMessages(['delivery_pincode' => 'Delivery is not available for this pincode.']);
            $subtotalPaise = 0;
            foreach ($cart->items as $item) {
                $meal = $item->mealOption;
                if (! $meal || ! $meal->is_active || (float) $meal->price <= 0) throw ValidationException::withMessages(['cart' => 'One or more meals are no longer available.']);
                if (! $item->delivery_date) throw ValidationException::withMessages(['cart' => 'Every meal needs a delivery date.']);
                $this->carts->validateDeliveryDate($item->delivery_date->toDateString());
                $subtotalPaise += (int) round(((float) $meal->price) * 100) * $item->quantity;
            }
            $order = Order::create([
                'order_number' => 'OJAS-'.Str::upper((string) Str::ulid()), 'user_id' => $user->id,
                'cart_id' => $cart->id, 'cart_version' => $cart->version, 'status' => 'awaiting_payment',
                'payment_status' => 'pending', 'currency' => 'INR', 'subtotal' => $subtotalPaise / 100,
                'discount_total' => 0, 'tax_total' => 0, 'delivery_fee' => 0, 'grand_total' => $subtotalPaise / 100,
                'customer_name' => $user->name, 'customer_phone' => $user->phone, ...$address,
            ]);
            foreach ($cart->items as $item) {
                $meal = $item->mealOption;
                $unitPaise = (int) round(((float) $meal->price) * 100);
                $order->items()->create([
                    'meal_option_id' => $meal->id, 'meal_name' => $meal->title, 'meal_slug' => $meal->slug,
                    'meal_type' => $meal->meal_type, 'category_slug' => $meal->category_slug,
                    'unit_price' => $unitPaise / 100, 'quantity' => $item->quantity,
                    'line_total' => ($unitPaise * $item->quantity) / 100, 'delivery_date' => $item->delivery_date,
                    'fulfillment_status' => 'pending_payment',
                ]);
            }
            $cart->update(['status' => 'checkout_locked']);
            return $order;
        });
        try {
            $gatewayOrder = $this->createGatewayOrder($order);
        } catch (Throwable $e) {
            $this->abandonInternal($order, 'gateway_order_failed', $e->getMessage());
            if ($e instanceof ValidationException) throw $e;
            throw ValidationException::withMessages(['payment' => 'Unable to create Razorpay order. Please try again.']);
        }
        $payment = $order->payments()->create([
            'gateway' => 'razorpay', 'reference' => $gatewayOrder['id'], 'gateway_order_id' => $gatewayOrder['id'],
            'attempt_number' => 1, 'idempotency_key' => hash('sha256', 'order:'.$order->id.':attempt:1'),
            'amount' => $order->grand_total, 'currency' => 'INR', 'status' => 'pending',
            'payload' => ['razorpay_order' => $gatewayOrder],
        ]);
        return ['order' => $this->present($order->fresh()), 'payment' => $payment,
            'razorpay' => ['key_id' => config('services.razorpay.key_id'), 'order' => $gatewayOrder]];
    }

    public function verify(User $user, Order $order, array $values): Order
    {
        if ($order->user_id !== $user->id) abort(404);

        $payment = $order->payments()->where('gateway_order_id', $values['razorpay_order_id'])->firstOrFail();
        $expected = hash_hmac(
            'sha256',
            $payment->gateway_order_id.'|'.$values['razorpay_payment_id'],
            (string) config('services.razorpay.key_secret'),
        );
        if (! hash_equals($expected, $values['razorpay_signature'])) {
            $payment->update(['status' => 'failed', 'failure_code' => 'invalid_signature']);
            throw ValidationException::withMessages(['razorpay_signature' => 'Invalid Razorpay payment signature.']);
        }

        return DB::transaction(function () use ($order, $values) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_status === 'paid') return $order;
            if ($order->status === 'abandoned') throw ValidationException::withMessages(['order' => 'This checkout was abandoned. Any late capture will be refunded.']);
            $payment = $order->payments()->where('gateway_order_id', $values['razorpay_order_id'])->lockForUpdate()->firstOrFail();
            $payment->update(['gateway_payment_id' => $values['razorpay_payment_id'], 'status' => 'paid', 'paid_at' => now(),
                'payload' => array_merge($payment->payload ?? [], ['verification' => $values])]);
            $this->markPaid($order);
            return $order->fresh();
        });
    }

    public function abandon(User $user, Order $order): Order
    {
        if ($order->user_id !== $user->id) abort(404);
        return $this->abandonInternal($order, 'checkout_abandoned', 'Customer dismissed or failed checkout.');
    }

    public function reconcileFailure(Order $order, string $code, ?string $description = null): Order
    {
        return $this->abandonInternal($order, $code, $description ?? 'Razorpay reported a failed payment.');
    }

    public function markPaid(Order $order): void
    {
        if ($order->payment_status === 'paid') return;
        $order->update(['status' => 'confirmed', 'payment_status' => 'paid', 'paid_at' => now(), 'placed_at' => now()]);
        $order->items()->where('fulfillment_status', 'pending_payment')->update(['fulfillment_status' => 'confirmed']);
        $cart = Cart::query()->whereKey($order->cart_id)->lockForUpdate()->first();
        if ($cart && $cart->version === $order->cart_version) {
            $cart->items()->delete();
            $cart->update(['status' => 'active', 'version' => $cart->version + 1]);
        }
    }

    public function present(Order $order): array
    {
        return $order->load(['items.refunds', 'payments.refunds'])->toArray();
    }

    private function createGatewayOrder(Order $order): array
    {
        $key = config('services.razorpay.key_id');
        $secret = config('services.razorpay.key_secret');
        if (! $key || ! $secret) throw ValidationException::withMessages(['razorpay' => 'Razorpay credentials are not configured.']);
        try {
            return Http::withBasicAuth($key, $secret)->acceptJson()->asJson()->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round(((float) $order->grand_total) * 100), 'currency' => 'INR',
                'receipt' => $order->order_number, 'notes' => ['order_id' => (string) $order->id, 'order_number' => $order->order_number],
            ])->throw()->json();
        } catch (RequestException $e) {
            throw new \RuntimeException('Razorpay rejected order creation.', previous: $e);
        }
    }

    private function abandonInternal(Order $order, string $code, string $description): Order
    {
        return DB::transaction(function () use ($order, $code, $description) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_status === 'paid') return $order;
            $order->update(['status' => 'abandoned', 'payment_status' => 'failed', 'abandoned_at' => now()]);
            $order->items()->where('fulfillment_status', 'pending_payment')->update(['fulfillment_status' => 'cancelled', 'cancelled_at' => now()]);
            $order->payments()->where('status', 'pending')->update(['status' => 'failed', 'failure_code' => $code, 'failure_description' => $description]);
            Cart::query()->whereKey($order->cart_id)->where('version', $order->cart_version)->update(['status' => 'active']);
            return $order->fresh();
        });
    }
}
