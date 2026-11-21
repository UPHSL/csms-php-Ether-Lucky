<?php

namespace Tests\Feature;

use Tests\TestCase;

class CsmsApplicationTest extends TestCase
{
    public function test_home_page_returns_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_home_page_displays_application_name(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Community Services Management System');
        $response->assertSee('Sprint 0 - Developer Onboarding');
        $response->assertSee('PHP with Laravel');
        $response->assertSee('1.0.0');
    }

    public function test_health_endpoint_returns_expected_payload(): void
    {
        $response = $this->get('/health');

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'application' => 'Community Services Management System',
                'version' => '1.0.0',
            ]);
    }

    public function test_unknown_page_returns_not_found(): void
    {
        $response = $this->get('/does-not-exist');

        $response->assertStatus(404);
    }
}
