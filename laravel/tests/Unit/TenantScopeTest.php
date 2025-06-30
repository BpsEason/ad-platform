<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Ad;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    protected $tenantA;
    protected $tenantB;
    protected $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);

        // Create ads for Tenant A
        Ad::create([
            'name' => 'Ad A1',
            'content' => 'Content A1',
            'start_time' => now(),
            'end_time' => now()->addDays(7),
            'tenant_id' => $this->tenantA->id,
        ]);
        Ad::create([
            'name' => 'Ad A2',
            'content' => 'Content A2',
            'start_time' => now(),
            'end_time' => now()->addDays(7),
            'tenant_id' => $this->tenantA->id,
        ]);

        // Create ads for Tenant B
        Ad::create([
            'name' => 'Ad B1',
            'content' => 'Content B1',
            'start_time' => now(),
            'end_time' => now()->addDays(7),
            'tenant_id' => $this->tenantB->id,
        ]);
    }

    /** @test */
    public function it_filters_models_by_tenant_id_when_user_is_authenticated()
    {
        // Authenticate as User A (from Tenant A)
        Auth::login($this->userA);

        $ads = Ad::all();

        // Should only see ads from Tenant A
        $this->assertCount(2, $ads);
        $this->assertTrue($ads->every(fn ($ad) => $ad->tenant_id === $this->tenantA->id));

        Auth::logout();
    }

    /** @test */
    public function it_filters_models_by_tenant_id_when_global_config_is_set()
    {
        // Clear authenticated user
        Auth::logout();

        // Set tenant ID via global config (e.g., from middleware for unauthenticated access by tenant header)
        config(['current_tenant_id' => $this->tenantB->id]);

        $ads = Ad::all();

        // Should only see ads from Tenant B
        $this->assertCount(1, $ads);
        $this->assertTrue($ads->every(fn ($ad) => $ad->tenant_id === $this->tenantB->id));

        config(['current_tenant_id' => null]); // Clear config for other tests
    }

    /** @test */
    public function it_does_not_filter_models_when_no_tenant_context_is_set()
    {
        // Clear all tenant contexts
        Auth::logout();
        config(['current_tenant_id' => null]);

        $ads = Ad::all();

        // Should see all ads if no tenant context is applied
        $this->assertCount(3, $ads);
    }
}
