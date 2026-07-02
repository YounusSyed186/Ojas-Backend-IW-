<?php

namespace Tests\Feature\Api;

use App\Models\Consultation;
use App\Models\ConsultationFee;
use App\Models\MealPlanTemplate;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDoctorDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'doctor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    }

    public function test_admin_dashboard_requires_admin_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        $this->getJson('/api/admin/dashboard')->assertForbidden();
    }

    public function test_admin_can_load_dashboard_stats(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'revenue',
                    'active_subscriptions',
                    'today_deliveries',
                    'pending_consultations',
                    'failed_payments',
                    'customer_growth',
                ],
                'top_meal_plans',
                'doctor_performance',
            ]);
    }

    public function test_admin_can_assign_doctor_to_consultation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $doctor = User::factory()->create(['role' => 'doctor', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer']);
        $consultation = Consultation::create([
            'user_id' => $customer->id,
            'consultation_fee_id' => ConsultationFee::create(['amount' => 1499, 'currency' => 'INR', 'is_active' => true])->id,
            'status' => 'requested',
            'payment_status' => 'paid',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/consultations/{$consultation->id}/assign-doctor", [
            'doctor_id' => $doctor->id,
        ])->assertOk()
            ->assertJsonPath('consultation.doctor_id', $doctor->id);
    }

    public function test_doctor_dashboard_requires_doctor_role(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Sanctum::actingAs($customer);

        $this->getJson('/api/doctor/dashboard')->assertForbidden();
    }

    public function test_doctor_can_accept_schedule_and_assign_plan(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor', 'status' => 'active']);
        $customer = User::factory()->create(['role' => 'customer']);
        $template = MealPlanTemplate::create([
            'name' => 'Test Plan',
            'description' => 'Test',
            'created_by' => $doctor->id,
            'is_active' => true,
        ]);
        $consultation = Consultation::create([
            'user_id' => $customer->id,
            'consultation_fee_id' => ConsultationFee::create(['amount' => 1499, 'currency' => 'INR', 'is_active' => true])->id,
            'status' => 'requested',
            'payment_status' => 'paid',
        ]);

        Sanctum::actingAs($doctor);

        $this->postJson("/api/doctor/consultations/{$consultation->id}/accept")
            ->assertOk()
            ->assertJsonPath('consultation.doctor_id', $doctor->id);

        $this->postJson("/api/doctor/consultations/{$consultation->id}/schedule", [
            'scheduled_for' => now()->addDay()->toISOString(),
        ])->assertOk()
            ->assertJsonPath('consultation.status', 'scheduled');

        $this->postJson("/api/doctor/consultations/{$consultation->id}/notes", [
            'doctor_notes' => 'Follow a low-spice routine for two weeks.',
            'mark_completed' => true,
        ])->assertOk()
            ->assertJsonPath('consultation.status', 'completed');

        $this->postJson("/api/doctor/consultations/{$consultation->id}/assign", [
            'meal_plan_template_id' => $template->id,
        ])->assertOk()
            ->assertJsonPath('consultation.status', 'plan_assigned');
    }

    public function test_admin_list_endpoints_support_search_and_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'customer', 'name' => 'Searchable Customer']);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?search=Searchable&per_page=2')
            ->assertOk()
            ->assertJsonPath('users.per_page', 2)
            ->assertJsonCount(2, 'users.data');
    }

    public function test_public_doctors_endpoint_returns_database_profiles(): void
    {
        User::factory()->create([
            'role' => 'doctor',
            'status' => 'active',
            'slug' => 'demo-doctor',
            'name' => 'Dr. Demo Doctor',
            'specialization' => 'Nutritionist',
            'experience' => '8 yrs',
            'rating' => 4.7,
            'bio' => 'Demo bio',
            'focus_areas' => ['Weight loss'],
        ]);

        $this->getJson('/api/doctors')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'demo-doctor', 'name' => 'Dr. Demo Doctor']);
    }

    public function test_customer_dashboard_requires_customer_role(): void
    {
        $doctor = User::factory()->create(['role' => 'doctor']);
        Sanctum::actingAs($doctor);

        $this->getJson('/api/customer/dashboard')->assertForbidden();
    }
}
