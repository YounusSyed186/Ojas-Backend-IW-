<?php

namespace Tests\Feature\Api;

use App\Models\Consultation;
use App\Models\ConsultationFee;
use App\Models\MealOption;
use App\Models\MealPlanTemplate;
use App\Models\ServiceablePincode;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StabilizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_email_or_phone_and_read_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'phone' => '9991112222',
            'password' => 'password',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'customer@example.com',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $phoneLogin = $this->postJson('/api/auth/login', [
            'phone' => '9991112222',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $this->withHeader('Authorization', 'Bearer '.$phoneLogin->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_logout_revokes_current_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_pincode_validation_reports_serviceability(): void
    {
        ServiceablePincode::create([
            'pincode' => '400001',
            'label' => 'Mumbai',
            'is_active' => true,
        ]);

        $this->getJson('/api/pincode/validate?pincode=400001')
            ->assertOk()
            ->assertJsonPath('is_valid', true);

        $this->getJson('/api/pincode/validate?pincode=999999')
            ->assertOk()
            ->assertJsonPath('is_valid', false);
    }

    public function test_subscription_creation_validates_pincode_and_creates_pending_subscription(): void
    {
        [$user, $plan] = $this->createCustomerPlan();
        Sanctum::actingAs($user);

        $payload = [
            'subscription_plan_id' => $plan->id,
            'delivery_pincode' => '999999',
        ];

        $this->postJson('/api/subscriptions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['delivery_pincode']);

        ServiceablePincode::create([
            'pincode' => '400001',
            'label' => 'Mumbai',
            'is_active' => true,
        ]);

        $this->postJson('/api/subscriptions', [
            'subscription_plan_id' => $plan->id,
            'delivery_pincode' => '400001',
            'delivery_address_line_1' => '15 Wellness Street',
            'delivery_city' => 'Mumbai',
            'meal_preferences' => [
                ['meal_type' => 'breakfast', 'meal_option_id' => MealOption::where('meal_type', 'breakfast')->value('id')],
            ],
            'health_details' => [
                'age' => 30,
                'weight' => 70,
                'goal' => 'General wellness',
            ],
        ])->assertCreated()
            ->assertJsonPath('subscription.status', 'pending')
            ->assertJsonPath('subscription.delivery_pincode', '400001');
    }

    public function test_razorpay_signature_verification_marks_payment_paid_and_activates_subscription(): void
    {
        config(['services.razorpay.key_secret' => 'test_secret']);
        [$user, $plan] = $this->createCustomerPlan();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'meal_plan_template_id' => $plan->meal_plan_template_id,
            'period' => $plan->period,
            'status' => 'pending',
            'delivery_pincode' => '400001',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->subDay()->toDateString(),
        ]);
        $subscription->payment()->create([
            'gateway' => 'razorpay',
            'reference' => 'order_test_123',
            'amount' => 1000,
            'currency' => 'INR',
            'status' => 'pending',
            'payload' => [],
        ]);
        Sanctum::actingAs($user);

        $signature = hash_hmac('sha256', 'order_test_123|pay_test_123', 'test_secret');

        $this->postJson('/api/payments/razorpay/verify', [
            'subscription_id' => $subscription->id,
            'razorpay_order_id' => 'order_test_123',
            'razorpay_payment_id' => 'pay_test_123',
            'razorpay_signature' => $signature,
        ])->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('subscription.status', 'active');

        $this->assertGreaterThan(0, $subscription->dailySelections()->count());
    }

    public function test_consultation_creation_starts_pending_and_razorpay_verification_marks_paid(): void
    {
        config([
            'services.razorpay.key_id' => 'rzp_test_key',
            'services.razorpay.key_secret' => 'test_secret',
        ]);

        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_consult_123',
                'amount' => 149900,
                'currency' => 'INR',
            ]),
        ]);

        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        ConsultationFee::create([
            'amount' => 1499,
            'currency' => 'INR',
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/consultations', [
            'preferred_slot_at' => now()->addDay()->setHour(10)->setMinute(0)->toISOString(),
            'request_notes' => 'Need a nutrition consult.',
        ])->assertCreated()
            ->assertJsonPath('consultation.status', 'pending')
            ->assertJsonPath('consultation.payment_status', 'pending');

        $consultationId = $createResponse->json('consultation.id');

        $this->postJson("/api/consultations/{$consultationId}/payments/razorpay/order")
            ->assertOk()
            ->assertJsonPath('consultation_id', $consultationId)
            ->assertJsonPath('order.id', 'order_consult_123');

        $signature = hash_hmac('sha256', 'order_consult_123|pay_consult_123', 'test_secret');

        $this->postJson("/api/consultations/{$consultationId}/payments/razorpay/verify", [
            'razorpay_order_id' => 'order_consult_123',
            'razorpay_payment_id' => 'pay_consult_123',
            'razorpay_signature' => $signature,
        ])->assertOk()
            ->assertJsonPath('payment.status', 'paid')
            ->assertJsonPath('consultation.payment_status', 'paid')
            ->assertJsonPath('consultation.status', 'requested');
    }

    public function test_invalid_consultation_razorpay_signature_marks_payment_failed(): void
    {
        config(['services.razorpay.key_secret' => 'test_secret']);

        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);
        $fee = ConsultationFee::create([
            'amount' => 1499,
            'currency' => 'INR',
            'is_active' => true,
        ]);
        $consultation = Consultation::create([
            'user_id' => $user->id,
            'consultation_fee_id' => $fee->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
        $consultation->payment()->create([
            'gateway' => 'razorpay',
            'reference' => 'order_bad_123',
            'amount' => 1499,
            'currency' => 'INR',
            'status' => 'pending',
            'payload' => [],
        ]);
        Sanctum::actingAs($user);

        $this->postJson("/api/consultations/{$consultation->id}/payments/razorpay/verify", [
            'razorpay_order_id' => 'order_bad_123',
            'razorpay_payment_id' => 'pay_bad_123',
            'razorpay_signature' => 'invalid-signature',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['razorpay_signature']);

        $this->assertDatabaseHas('payments', [
            'payable_type' => Consultation::class,
            'payable_id' => $consultation->id,
            'reference' => 'order_bad_123',
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('consultations', [
            'id' => $consultation->id,
            'payment_status' => 'failed',
        ]);
    }

    public function test_customer_dashboard_returns_subscription_meals_consultations_and_payments(): void
    {
        [$user, $plan] = $this->createCustomerPlan();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'meal_plan_template_id' => $plan->meal_plan_template_id,
            'period' => $plan->period,
            'status' => 'active',
            'delivery_pincode' => '400001',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->subDay()->toDateString(),
        ]);
        $subscription->payment()->create([
            'gateway' => 'razorpay',
            'reference' => 'order_dashboard_123',
            'amount' => 1000,
            'currency' => 'INR',
            'status' => 'paid',
            'payload' => [],
            'paid_at' => now(),
        ]);
        $fee = ConsultationFee::create([
            'amount' => 1499,
            'currency' => 'INR',
            'is_active' => true,
        ]);
        Consultation::create([
            'user_id' => $user->id,
            'consultation_fee_id' => $fee->id,
            'status' => 'requested',
            'payment_status' => 'paid',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/customer/dashboard')
            ->assertOk()
            ->assertJsonPath('active_subscription.id', $subscription->id)
            ->assertJsonPath('recent_consultations.0.payment_status', 'paid')
            ->assertJsonStructure([
                'today_meals',
                'upcoming_meals',
                'recent_consultations',
                'recent_payments',
                'stats',
            ]);
    }

    public function test_public_meal_catalog_is_served_from_active_meal_options(): void
    {
        $template = MealPlanTemplate::create([
            'name' => 'Public Catalog',
            'description' => 'Public catalog template.',
            'period' => 'week',
            'is_active' => true,
        ]);

        MealOption::create([
            'meal_plan_template_id' => $template->id,
            'meal_type' => 'lunch',
            'category_slug' => 'lunch',
            'title' => 'Paneer Quinoa Bowl',
            'slug' => 'paneer-quinoa-bowl',
            'tag' => 'High Protein',
            'description' => 'Paneer, quinoa, greens, and yogurt dressing.',
            'calories' => 480,
            'price' => 389,
            'protein' => 30,
            'carbs' => 42,
            'fat' => 20,
            'ingredients' => ['Paneer', 'Quinoa', 'Greens'],
            'is_active' => true,
        ]);

        $this->getJson('/api/meals')
            ->assertOk()
            ->assertJsonPath('meals.0.slug', 'paneer-quinoa-bowl')
            ->assertJsonPath('meals.0.category', 'lunch')
            ->assertJsonPath('meals.0.protein', 30);

        $this->getJson('/api/categories/lunch')
            ->assertOk()
            ->assertJsonPath('category.slug', 'lunch')
            ->assertJsonPath('meals.0.name', 'Paneer Quinoa Bowl');
    }

    private function createCustomerPlan(): array
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        $template = MealPlanTemplate::create([
            'name' => 'Reset Plan',
            'description' => 'Balanced weekly plan.',
            'period' => 'week',
            'is_active' => true,
        ]);

        foreach (['breakfast', 'lunch', 'dinner'] as $mealType) {
            MealOption::create([
                'meal_plan_template_id' => $template->id,
                'meal_type' => $mealType,
                'title' => ucfirst($mealType),
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        $plan = SubscriptionPlan::create([
            'name' => 'Weekly Reset',
            'description' => 'One week plan.',
            'meal_plan_template_id' => $template->id,
            'period' => 'weekly',
            'price' => 1000,
            'is_active' => true,
        ]);

        return [$user, $plan];
    }
}
