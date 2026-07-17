<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessRazorpayWebhook;
use App\Models\MealOption;
use App\Models\MealPlanTemplate;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use App\Models\ServiceablePincode;
use App\Models\User;
use App\Services\CartService;
use App\Services\RazorpayWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MealCommerceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_customer_can_schedule_cart_and_complete_order(): void
    {
        config(['services.razorpay.key_id' => 'rzp_test_key', 'services.razorpay.key_secret' => 'test_secret']);
        Http::fake(['https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_meals_123', 'amount' => 49800, 'currency' => 'INR',
        ])]);
        [$user, $meal] = $this->commerceFixtures();
        Sanctum::actingAs($user);

        $cart = $this->getJson('/api/cart')->assertOk()->json('cart');
        $cart = $this->postJson('/api/cart/items', [
            'meal_option_id' => $meal->id, 'quantity' => 2, 'version' => $cart['version'],
        ])->assertCreated()->assertJsonPath('cart.subtotal', 498)->json('cart');
        $deliveryDate = app(CartService::class)->deliveryWindow()[0];
        $cart = $this->patchJson('/api/cart/items/'.$cart['items'][0]['id'], [
            'delivery_date' => $deliveryDate, 'version' => $cart['version'],
        ])->assertOk()->assertJsonPath('cart.checkout_ready', true)->json('cart');

        $checkout = $this->postJson('/api/cart/checkout', [
            'version' => $cart['version'], 'delivery_address_line_1' => '15 Wellness Street',
            'delivery_city' => 'Mumbai', 'delivery_state' => 'Maharashtra', 'delivery_pincode' => '400001',
        ])->assertCreated()->assertJsonPath('razorpay.order.id', 'order_meals_123');

        $number = $checkout->json('order.order_number');
        $signature = hash_hmac('sha256', 'order_meals_123|pay_meals_123', 'test_secret');
        $this->postJson('/api/orders/'.$number.'/payments/razorpay/verify', [
            'razorpay_order_id' => 'order_meals_123', 'razorpay_payment_id' => 'pay_meals_123',
            'razorpay_signature' => $signature,
        ])->assertOk()->assertJsonPath('order.payment_status', 'paid')->assertJsonPath('order.status', 'confirmed');

        $this->assertDatabaseHas('orders', ['order_number' => $number, 'grand_total' => 498, 'payment_status' => 'paid']);
        $this->getJson('/api/cart')->assertJsonCount(0, 'cart.items');
    }

    public function test_cart_rejects_stale_version_and_unverified_customer(): void
    {
        [$user, $meal] = $this->commerceFixtures();
        Sanctum::actingAs($user);
        $version = $this->getJson('/api/cart')->json('cart.version');
        $this->postJson('/api/cart/items', ['meal_option_id' => $meal->id, 'quantity' => 1, 'version' => $version])->assertCreated();
        $this->postJson('/api/cart/items', ['meal_option_id' => $meal->id, 'quantity' => 1, 'version' => $version])
            ->assertConflict()->assertJsonStructure(['cart']);

        $user->update(['phone_verified_at' => null]);
        Sanctum::actingAs($user->fresh());
        $this->getJson('/api/cart')->assertForbidden();
    }

    public function test_signed_webhook_is_persisted_and_deduplicated(): void
    {
        Queue::fake();
        config(['services.razorpay.webhook_secret' => 'hook_secret']);
        $payload = json_encode(['event' => 'payment.failed', 'payload' => ['payment' => ['entity' => ['id' => 'pay_1']]]]);
        $headers = [
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, 'hook_secret'),
            'HTTP_X_RAZORPAY_EVENT_ID' => 'evt_unique_1',
            'CONTENT_TYPE' => 'application/json',
        ];
        $this->call('POST', '/api/webhooks/razorpay', server: $headers, content: $payload)->assertOk();
        $this->call('POST', '/api/webhooks/razorpay', server: $headers, content: $payload)->assertOk();
        $this->assertSame(1, PaymentWebhookEvent::count());
        Queue::assertPushed(ProcessRazorpayWebhook::class, 1);
    }

    public function test_invalid_signature_is_recorded_and_failure_webhook_unlocks_cart(): void
    {
        config(['services.razorpay.key_id' => 'rzp_test_key', 'services.razorpay.key_secret' => 'test_secret']);
        Http::fake(['https://api.razorpay.com/v1/orders' => Http::response([
            'id' => 'order_failed_123', 'amount' => 24900, 'currency' => 'INR',
        ])]);
        [$user, $meal] = $this->commerceFixtures();
        Sanctum::actingAs($user);
        $cart = $this->getJson('/api/cart')->json('cart');
        $cart = $this->postJson('/api/cart/items', [
            'meal_option_id' => $meal->id, 'quantity' => 1, 'version' => $cart['version'],
        ])->json('cart');
        $cart = $this->patchJson('/api/cart/items/'.$cart['items'][0]['id'], [
            'delivery_date' => app(CartService::class)->deliveryWindow()[0], 'version' => $cart['version'],
        ])->json('cart');
        $checkout = $this->postJson('/api/cart/checkout', [
            'version' => $cart['version'], 'delivery_address_line_1' => '15 Wellness Street',
            'delivery_city' => 'Mumbai', 'delivery_state' => 'Maharashtra', 'delivery_pincode' => '400001',
        ])->assertCreated();

        $this->postJson('/api/orders/'.$checkout->json('order.order_number').'/payments/razorpay/verify', [
            'razorpay_order_id' => 'order_failed_123', 'razorpay_payment_id' => 'pay_failed_123',
            'razorpay_signature' => 'invalid',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('payments', ['gateway_order_id' => 'order_failed_123', 'failure_code' => 'invalid_signature']);

        $event = PaymentWebhookEvent::create([
            'gateway' => 'razorpay', 'event_id' => 'evt_failed_123', 'event_type' => 'payment.failed', 'status' => 'pending',
            'payload' => ['payload' => ['payment' => ['entity' => [
                'order_id' => 'order_failed_123', 'error_code' => 'BAD_REQUEST_ERROR', 'error_description' => 'Payment failed',
            ]]]],
        ]);
        app(RazorpayWebhookService::class)->process($event);

        $this->assertDatabaseHas('orders', ['order_number' => $checkout->json('order.order_number'), 'status' => 'abandoned']);
        $this->assertDatabaseHas('carts', ['user_id' => $user->id, 'status' => 'active']);
    }

    private function commerceFixtures(): array
    {
        $user = User::factory()->create(['role' => 'customer', 'status' => 'active', 'phone' => '9991112222', 'phone_verified_at' => now()]);
        ServiceablePincode::create(['pincode' => '400001', 'label' => 'Mumbai', 'is_active' => true]);
        $template = MealPlanTemplate::create(['name' => 'Commerce', 'description' => 'Commerce meals', 'period' => 'day', 'is_active' => true]);
        $meal = MealOption::create(['meal_plan_template_id' => $template->id, 'meal_type' => 'lunch', 'category_slug' => 'lunch',
            'title' => 'Balanced Bowl', 'slug' => 'balanced-bowl', 'price' => 249, 'is_active' => true]);
        return [$user, $meal];
    }
}
