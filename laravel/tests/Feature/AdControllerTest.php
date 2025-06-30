<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Ad;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\ValidationException;

class AdControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $advertiserA;
    protected $tenantA;
    protected $viewerB;
    protected $tenantB;
    protected $adminUser; // New admin user for global tests

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Spatie permissions
        Permission::findOrCreate('create ads');
        Permission::findOrCreate('read ads');
        Permission::findOrCreate('update ads');
        Permission::findOrCreate('delete ads');
        Permission::findOrCreate('manage tenants'); // For admin tests

        // Create tenants
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A', 'domain' => 'tenant-a.localhost']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B', 'domain' => 'tenant-b.localhost']);

        // Create roles and assign permissions for Tenant A
        $advertiserRoleA = Role::findOrCreate('advertiser', 'web', $this->tenantA->id);
        $advertiserRoleA->givePermissionTo(['create ads', 'read ads', 'update ads', 'delete ads']);

        // Create user for Tenant A
        $this->advertiserA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->advertiserA->assignRole($advertiserRoleA);

        // Create user for Tenant B (with read-only for their tenant)
        $viewerRoleB = Role::findOrCreate('viewer', 'web', $this->tenantB->id);
        $viewerRoleB->givePermissionTo(['read ads']);
        $this->viewerB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->viewerB->assignRole($viewerRoleB);

        // Create a global admin user
        $adminRole = Role::findOrCreate('admin');
        $adminRole->givePermissionTo('manage tenants');
        $this->adminUser = User::factory()->create(['email' => 'admin@admin.com', 'tenant_id' => null]);
        $this->adminUser->assignRole($adminRole);
    }

    /** @test */
    public function an_authorized_user_can_create_an_ad_for_their_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');

        $response = $this->postJson('/api/ads', [
            'name' => 'Test Ad',
            'content' => 'This is a test ad content.',
            'start_time' => now()->toDateString(),
            'end_time' => now()->addDays(7)->toDateString(),
            'target_audience' => json_encode(['gender' => 'male', 'age_min' => 25]),
        ]);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Test Ad', 'tenant_id' => $this->tenantA->id]);

        $this->assertDatabaseHas('ads', [
            'name' => 'Test Ad',
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    /** @test */
    public function creating_an_ad_with_invalid_data_returns_validation_errors()
    {
        $this->actingAs($this->advertiserA, 'sanctum');

        // Missing required 'name' field
        $response = $this->postJson('/api/ads', [
            'content' => 'Invalid Ad Content',
            'start_time' => now()->toDateString(),
            'end_time' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(422) // Unprocessable Entity
                 ->assertJsonValidationErrors(['name']);

        // Invalid date format for 'start_time'
        $response = $this->postJson('/api/ads', [
            'name' => 'Ad with Bad Date',
            'content' => 'Content',
            'start_time' => 'not-a-date',
            'end_time' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['start_time']);

        // End time before start time
        $response = $this->postJson('/api/ads', [
            'name' => 'Time Travel Ad',
            'content' => 'Content',
            'start_time' => now()->toDateString(),
            'end_time' => now()->subDay()->toDateString(),
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['end_time']);
    }

    /** @test */
    public function an_authorized_user_can_view_ads_for_their_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        Ad::factory()->count(2)->create(['tenant_id' => $this->tenantA->id]);
        Ad::factory()->count(1)->create(['tenant_id' => $this->tenantB->id]); // Ad from another tenant

        $response = $this->getJson('/api/ads');

        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data') // Should only see ads from Tenant A
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'name', 'content', 'tenant_id']
                     ]
                 ]);
        
        $this->assertFalse(collect($response->json('data'))->contains('tenant_id', $this->tenantB->id));
    }

    /** @test */
    public function an_authorized_user_can_view_a_specific_ad_from_their_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        $ad = Ad::factory()->create(['tenant_id' => $this->tenantA->id]);
        Ad::factory()->create(['tenant_id' => $this->tenantB->id]); // Ad from another tenant

        $response = $this->getJson('/api/ads/' . $ad->id);

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $ad->id, 'name' => $ad->name]);
    }

    /** @test */
    public function an_authorized_user_cannot_view_a_specific_ad_from_another_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        $adFromAnotherTenant = Ad::factory()->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->getJson('/api/ads/' . $adFromAnotherTenant->id);

        $response->assertStatus(404); // Not Found because TenantScope filters it out
    }

    /** @test */
    public function an_authorized_user_can_update_an_ad_from_their_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        $ad = Ad::factory()->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->putJson('/api/ads/' . $ad->id, [
            'name' => 'Updated Ad Name',
            'content' => 'Updated content here.',
        ]);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Updated Ad Name']);

        $this->assertDatabaseHas('ads', [
            'id' => $ad->id,
            'name' => 'Updated Ad Name',
        ]);
    }

    /** @test */
    public function updating_an_ad_with_invalid_data_returns_validation_errors()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        $ad = Ad::factory()->create(['tenant_id' => $this->tenantA->id]);

        // Invalid end_time
        $response = $this->putJson('/api/ads/' . $ad->id, [
            'end_time' => 'invalid-date',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['end_time']);
    }

    /** @test */
    public function an_authorized_user_cannot_update_an_ad_from_another_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        $adFromAnotherTenant = Ad::factory()->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->putJson('/api/ads/' . $adFromAnotherTenant->id, [
            'name' => 'Attempted Update',
        ]);

        $response->assertStatus(404); // Not Found
    }

    /** @test */
    public function an_authorized_user_can_delete_an_ad_from_their_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        $ad = Ad::factory()->create(['tenant_id' => $this->tenantA->id]);

        $response = $this->deleteJson('/api/ads/' . $ad->id);

        $response->assertStatus(204); // No Content

        $this->assertDatabaseMissing('ads', ['id' => $ad->id]);
    }

    /** @test */
    public function an_authorized_user_cannot_delete_an_ad_from_another_tenant()
    {
        $this->actingAs($this->advertiserA, 'sanctum');
        $adFromAnotherTenant = Ad::factory()->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->deleteJson('/api/ads/' . $adFromAnotherTenant->id);

        $response->assertStatus(404); // Not Found
    }

    /** @test */
    public function an_unauthorized_user_cannot_create_an_ad()
    {
        $userWithoutPermission = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->actingAs($userWithoutPermission, 'sanctum');

        $response = $this->postJson('/api/ads', [
            'name' => 'Unauthorized Ad',
            'content' => 'Content',
            'start_time' => now()->toDateString(),
            'end_time' => now()->addDays(7)->toDateString(),
        ]);

        $response->assertStatus(403); // Forbidden
    }
}
