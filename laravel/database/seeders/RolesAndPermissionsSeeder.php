<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use App\Models\Tenant;
class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        // Create permissions
        $permissions = [
            'create ads', 'read ads', 'update ads', 'delete ads', 'view reports'
        ];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }
        // Create tenants
        $tenantA = Tenant::firstOrCreate(['name' => 'Tenant A', 'domain' => 'tenant-a.localhost']);
        $tenantB = Tenant::firstOrCreate(['name' => 'Tenant B', 'domain' => 'tenant-b.localhost']);
        // Create roles per tenant
        $advertiserA = Role::findOrCreate('advertiser', 'web', $tenantA->id);
        $advertiserA->givePermissionTo(['create ads', 'read ads', 'update ads']);
        $viewerB = Role::findOrCreate('viewer', 'web', $tenantB->id);
        $viewerB->givePermissionTo(['read ads', 'view reports']);
        $admin = Role::findOrCreate('admin'); // Global admin
        $admin->givePermissionTo(Permission::all());
        // Create users and assign roles
        $userA = User::firstOrCreate(['email' => 'advertiserA@example.com'], ['name' => 'Advertiser A', 'password' => bcrypt('password'), 'tenant_id' => $tenantA->id]);
        $userA->assignRole($advertiserA);
        $userB = User::firstOrCreate(['email' => 'viewerB@example.com'], ['name' => 'Viewer B', 'password' => bcrypt('password'), 'tenant_id' => $tenantB->id]);
        $userB->assignRole($viewerB);
        $superAdmin = User::firstOrCreate(['email' => 'admin@example.com'], ['name' => 'Super Admin', 'password' => bcrypt('password')]);
        $superAdmin->assignRole($admin);
    }
}
