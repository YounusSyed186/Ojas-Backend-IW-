<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_panel(): void
    {
        $this->seed();

        $admin = User::query()->where('role', 'admin')->firstOrFail();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk();
    }

    public function test_customer_cannot_access_admin_or_doctor_panels(): void
    {
        $this->seed();

        $customer = User::query()->where('role', 'customer')->firstOrFail();

        $this->actingAs($customer)->get('/admin')->assertForbidden();
        $this->actingAs($customer)->get('/doctor')->assertForbidden();
    }
}
