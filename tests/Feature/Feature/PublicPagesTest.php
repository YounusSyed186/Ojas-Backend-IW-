<?php

namespace Tests\Feature\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_seeded_content(): void
    {
        $this->seed();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Ojas Cuisine');
        $response->assertSee('Metabolic Reset');
    }

    public function test_phone_login_page_renders(): void
    {
        $response = $this->get('/phone-login');

        $response->assertOk();
        $response->assertSee('Send OTP');
    }
}
