<?php

namespace Tests\Feature\Feature;

use App\Models\Consultation;
use App\Models\OtpCode;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\MealTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_with_phone_and_address(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test Customer',
            'email' => 'new@example.com',
            'phone' => '9876543210',
            'address_line_1' => '123 Main Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pincode' => '400001',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/customer/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'phone' => '9876543210',
            'pincode' => '400001',
        ]);
    }

    public function test_phone_otp_login_creates_or_authenticates_customer(): void
    {
        config([
            'services.msg91.auth_key' => 'test-auth-key',
            'services.msg91.otp_template_id' => 'test-template',
        ]);
        Http::fake([
            'https://control.msg91.com/api/v5/otp*' => Http::response(['type' => 'success']),
        ]);

        $sendResponse = $this->post('/phone-login/send', ['phone' => '9991112222']);
        $sendResponse->assertSessionHas('status');

        $otpCode = OtpCode::where('phone', '9991112222')->firstOrFail();

        $verifyResponse = $this->post('/phone-login/verify', [
            'phone' => '9991112222',
            'name' => 'OTP User',
            'code' => $otpCode->code,
        ]);

        $verifyResponse->assertRedirect('/customer/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('otp_codes', [
            'phone' => '9991112222',
        ]);
        $this->assertNotNull(OtpCode::first()?->verified_at);
    }

    public function test_customer_can_book_consultation_with_dummy_payment(): void
    {
        $this->seed();
        $customer = User::query()->where('role', 'customer')->firstOrFail();

        $response = $this->actingAs($customer)->post('/customer/consultations', [
            'preferred_slot_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'request_notes' => 'Need help with a high-protein plan.',
        ]);

        $response->assertRedirect('/customer/dashboard');
        $this->assertDatabaseCount('consultations', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertEquals('paid', Consultation::firstOrFail()->payment_status);
    }

    public function test_subscription_requires_serviceable_pincode_and_creates_daily_meals(): void
    {
        $this->seed();
        $customer = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        $plan = SubscriptionPlan::query()->firstOrFail();

        $badResponse = $this->actingAs($customer)->post('/customer/subscriptions', [
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->addDay()->toDateString(),
            'delivery_pincode' => '999999',
        ]);
        $badResponse->assertSessionHasErrors('delivery_pincode');

        $goodResponse = $this->actingAs($customer)->post('/customer/subscriptions', [
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->addDay()->toDateString(),
            'delivery_pincode' => '400001',
        ]);

        $goodResponse->assertRedirect('/customer/dashboard');
        $subscription = Subscription::query()
            ->where('user_id', $customer->id)
            ->whereDate('start_date', now()->addDay()->toDateString())
            ->latest()
            ->firstOrFail();

        $this->assertSame('active', $subscription->status);
        $this->assertSame(count(MealTypes::ALL), $subscription->preferences()->count());
        $this->assertGreaterThanOrEqual(21, $subscription->dailySelections()->count());
        $this->assertNotNull($subscription->payment);
    }
}
