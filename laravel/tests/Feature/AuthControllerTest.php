<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;
use Exception;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $tenantA;
    protected $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant
        $this->tenantA = Tenant::factory()->create(['name' => 'Test Tenant', 'domain' => 'test-tenant.localhost']);

        // Create a test user associated with the tenant
        $this->testUser = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenantA->id,
        ]);

        // Ensure default permissions are set up if not already
        Permission::findOrCreate('view reports');
        $viewerRole = Role::findOrCreate('viewer', 'web', $this->tenantA->id);
        $viewerRole->givePermissionTo('view reports');
        $this->testUser->assignRole($viewerRole);
    }

    /** @test */
    public function a_user_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'tenant_id']]);
        
        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals('test@example.com', $response->json('user.email'));
    }

    /** @test */
    public function a_user_cannot_login_with_invalid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthorized']);
    }

    /** @test */
    public function login_with_missing_email_returns_validation_error()
    {
        $response = $this->postJson('/api/login', [
            'password' => 'password',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function login_with_missing_password_returns_validation_error()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function an_authenticated_user_can_logout()
    {
        $token = $this->testUser->createToken('test_token')->plainTextToken;

        $response = $this->actingAs($this->testUser, 'sanctum')
                         ->postJson('/api/logout');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out successfully']);
        
        // Assert that the token is revoked (e.g., by trying to use it)
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
                         ->getJson('/api/user'); // A protected route, e.g., default /api/user

        $response->assertStatus(401); // Unauthorized
    }

    /** @test */
    public function a_guest_cannot_logout()
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401); // Unauthorized
    }

    /** @test */
    public function database_connection_failure_during_login_returns_error()
    {
        // Simulate database connection failure by mocking the DB facade
        \Illuminate\Support\Facades\DB::shouldReceive('connection')
            ->once()
            ->andThrow(new Exception('Simulated database connection failed.'));

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // Depending on Laravel's error handling, this might be a 500 or caught by custom handlers
        $response->assertStatus(500)
                 ->assertJsonFragment(['message' => 'Server Error']);
    }

    /** @test */
    public function invalid_tenant_id_in_header_returns_error()
    {
        $token = $this->testUser->createToken('test_token')->plainTextToken;
        $invalidTenantId = $this->tenantA->id + 999; // An ID that doesn't exist

        $response = $this->actingAs($this->testUser, 'sanctum')
                         ->withHeaders(['X-Tenant-Id' => $invalidTenantId])
                         ->getJson('/api/ads'); // A route protected by SetTenant middleware

        $response->assertStatus(403)
                 ->assertJson(['error' => 'Invalid tenant ID.']);
    }
}
