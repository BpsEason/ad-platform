<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Tenant;

class TraefikConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create some tenants for testing
        Tenant::factory()->create(['name' => 'Tenant One', 'domain' => 'tenant1.localhost']);
        Tenant::factory()->create(['name' => 'Tenant Two', 'domain' => 'tenant2.localhost']);
    }

    /** @test */
    public function it_generates_correct_traefik_http_config_with_api_key()
    {
        // Set a dummy API key for testing
        \Config::set('app.traefik_api_key', 'test_api_key_123');

        $response = $this->getJson('/api/traefik/config', ['X-API-Key' => 'test_api_key_123']);

        $response->assertStatus(200)
                 ->assertHeader('Content-Type', 'application/json');

        $data = $response->json();

        $this->assertArrayHasKey('http', $data);
        $this->assertArrayHasKey('routers', $data['http']);
        $this->assertArrayHasKey('services', $data['http']);

        $routers = $data['http']['routers'];
        $services = $data['http']['services'];

        // Check for tenant1.localhost router
        $this->assertArrayHasKey('tenant-1-router', $routers);
        $this->assertEquals("Host(`tenant1.localhost`)", $routers['tenant-1-router']['rule']);
        $this->assertEquals("laravel-1-service", $routers['tenant-1-router']['service']);

        // Check for laravel-1-service
        $this->assertArrayHasKey('laravel-1-service', $services);
        $this->assertCount(1, $services['laravel-1-service']['loadBalancer']['servers']);
        $this->assertEquals('http://laravel/', $services['laravel-1-service']['loadBalancer']['servers'][0]['url']);
    }

    /** @test */
    public function it_returns_unauthorized_without_api_key()
    {
        // Set a dummy API key for testing
        \Config::set('app.traefik_api_key', 'test_api_key_123');

        $response = $this->getJson('/api/traefik/config'); // No X-API-Key header

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthorized: Invalid API Key']);
    }

    /** @test */
    public function it_returns_unauthorized_with_invalid_api_key()
    {
        // Set a dummy API key for testing
        \Config::set('app.traefik_api_key', 'test_api_key_123');

        $response = $this->getJson('/api/traefik/config', ['X-API-Key' => 'wrong_key']);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthorized: Invalid API Key']);
    }

    /** @test */
    public function it_returns_empty_config_if_no_tenants_exist_with_api_key()
    {
        // Set a dummy API key for testing
        \Config::set('app.traefik_api_key', 'test_api_key_123');
        
        // Delete all existing tenants
        Tenant::truncate();

        $response = $this->getJson('/api/traefik/config', ['X-API-Key' => 'test_api_key_123']);

        $response->assertStatus(200)
                 ->assertHeader('Content-Type', 'application/json');

        $data = $response->json();

        $this->assertEmpty($data['http']['routers']);
        $this->assertEmpty($data['http']['services']);
    }
}
