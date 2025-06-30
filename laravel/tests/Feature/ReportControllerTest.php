<?php

namespace Tests\Feature;

use Illuminate\Foundation->Testing->RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Exception;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $viewerB;
    protected $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Spatie permissions
        Permission::findOrCreate('view reports');

        // Create tenant
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B', 'domain' => 'tenant-b.localhost']);

        // Create role and assign permission
        $viewerRole = Role::findOrCreate('viewer', 'web', $this->tenantB->id);
        $viewerRole->givePermissionTo('view reports');

        // Create user and assign role
        $this->viewerB = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->viewerB->assignRole($viewerRole);

        // Create some events for Tenant B
        Event::create([
            'tenant_id' => $this->tenantB->id,
            'ad_id' => 1,
            'user_id' => $this->viewerB->id,
            'event_type' => 'impression',
            'occurred_at' => now()->subDays(2),
        ]);
        Event::create([
            'tenant_id' => $this->tenantB->id,
            'ad_id' => 1,
            'user_id' => $this->viewerB->id,
            'event_type' => 'click',
            'occurred_at' => now()->subDays(2),
        ]);
        Event::create([
            'tenant_id' => $this->tenantB->id,
            'ad_id' => 2,
            'user_id' => $this->viewerB->id,
            'event_type' => 'impression',
            'occurred_at' => now()->subDay(),
        ]);
        Event::create([
            'tenant_id' => $this->tenantB->id,
            'ad_id' => 2,
            'user_id' => $this->viewerB->id,
            'event_type' => 'click',
            'occurred_at' => now()->subDay(),
        ]);
        Event::create([
            'tenant_id' => $this->tenantB->id,
            'ad_id' => 2,
            'user_id' => $this->viewerB->id,
            'event_type' => 'impression',
            'occurred_at' => now(),
        ]);

        // Create events for another tenant (should not be visible)
        $anotherTenant = Tenant::factory()->create();
        Event::create([
            'tenant_id' => $anotherTenant->id,
            'ad_id' => 3,
            'user_id' => null,
            'event_type' => 'impression',
            'occurred_at' => now(),
        ]);
    }

    /** @test */
    public function an_authorized_user_can_get_conversion_rate_for_their_tenant()
    {
        $this->actingAs($this->viewerB, 'sanctum');

        $response = $this->getJson('/api/reports/conversions');

        $response->assertStatus(200)
                 ->assertJsonStructure(['impressions', 'clicks', 'conversion_rate']);
        
        // Assert based on seeded data for Tenant B
        // 3 impressions, 2 clicks
        $response->assertJsonFragment(['impressions' => 3]);
        $response->assertJsonFragment(['clicks' => 2]);
        $response->assertJsonFragment(['conversion_rate' => '66.67%']); // (2/3)*100
    }

    /** @test */
    public function an_authorized_user_can_get_events_report_for_their_tenant()
    {
        $this->actingAs($this->viewerB, 'sanctum');

        $response = $this->getJson('/api/reports/events');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     now()->subDays(2)->toDateString() => ['impression', 'click'],
                     now()->subDay()->toDateString() => ['impression', 'click'],
                     now()->toDateString() => ['impression', 'click'],
                 ]);
        
        $response->assertJsonFragment([
            now()->subDays(2)->toDateString() => ['impression' => 1, 'click' => 1],
            now()->subDay()->toDateString() => ['impression' => 1, 'click' => 1],
            now()->toDateString() => ['impression' => 1, 'click' => 0],
        ]);
    }

    /** @test */
    public function unauthorized_user_cannot_get_reports()
    {
        // User without 'view reports' permission
        $unauthorizedUser = User::factory()->create(['tenant_id' => $this->tenantB->id]);
        $this->actingAs($unauthorizedUser, 'sanctum');

        $response = $this->getJson('/api/reports/conversions');
        $response->assertStatus(403); // Forbidden

        $response = $this->getJson('/api/reports/events');
        $response->assertStatus(403); // Forbidden
    }

    /** @test */
    public function database_connection_failure_during_report_generation_returns_error()
    {
        $this->actingAs($this->viewerB, 'sanctum');

        // Simulate database connection failure
        \Illuminate\Support\Facades\DB::shouldReceive('raw')
            ->andThrow(new Exception('Simulated database connection failure during report.'));

        $response = $this->getJson('/api/reports/conversions');

        $response->assertStatus(500)
                 ->assertJsonFragment(['message' => 'Server Error']);
    }
}
