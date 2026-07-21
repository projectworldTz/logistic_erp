<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'jane@acme.test'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => 'Acme Logistics',
                'country' => 'Kenya',
                'city' => 'Nairobi',
                'address' => '123 Port Rd',
                'currency' => 'USD',
                'timezone' => 'Africa/Nairobi',
                'industry' => 'Freight Forwarding',
            ],
        ]);

        return $response->json();
    }

    public function test_owner_can_invite_a_user_and_the_new_user_can_log_in(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Rashid Kombo',
                'email' => 'rashid@acme.test',
                'phone' => '+254700000000',
                'roles' => ['Clearing Officer'],
                'password' => 'StaffPass123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'rashid@acme.test')
            ->assertJsonPath('data.roles.0', 'Clearing Officer')
            ->assertJsonPath('data.status', 'active');

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'rashid@acme.test',
            'password' => 'StaffPass123',
        ])->assertOk();
    }

    public function test_owner_can_change_a_users_role(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $invite = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Rashid Kombo',
                'email' => 'rashid@acme.test',
                'roles' => ['Clearing Officer'],
                'password' => 'StaffPass123',
            ]);
        $userId = $invite->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/users/{$userId}", ['roles' => ['Warehouse Manager']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['Warehouse Manager']);
    }

    public function test_owner_can_assign_multiple_roles_to_a_user(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $invite = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Rashid Kombo',
                'email' => 'rashid@acme.test',
                'roles' => ['Clearing Officer'],
                'password' => 'StaffPass123',
            ]);
        $userId = $invite->json('data.id');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/users/{$userId}", ['roles' => ['Clearing Officer', 'Warehouse Manager']])
            ->assertOk();

        $this->assertEqualsCanonicalizing(['Clearing Officer', 'Warehouse Manager'], $response->json('data.roles'));

        // Permissions should be the union of both roles' permissions.
        $permissions = $response->json('data.permissions');
        $this->assertContains('clearing.files.view', $permissions);
        $this->assertContains('warehouse.items.view', $permissions);
    }

    public function test_owner_can_suspend_and_activate_a_user(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $invite = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Rashid Kombo',
                'email' => 'rashid@acme.test',
                'roles' => ['Clearing Officer'],
                'password' => 'StaffPass123',
            ]);
        $userId = $invite->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/users/{$userId}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'rashid@acme.test',
            'password' => 'StaffPass123',
        ])->assertStatus(422);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/users/{$userId}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/auth/login', [
            'email' => 'rashid@acme.test',
            'password' => 'StaffPass123',
        ])->assertOk();
    }

    public function test_owner_cannot_change_their_own_role(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $ownerId = $registration['user']['id'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/users/{$ownerId}", ['roles' => ['Sales Manager']])
            ->assertStatus(422);
    }

    public function test_owner_cannot_suspend_their_own_account(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];
        $ownerId = $registration['user']['id'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/users/{$ownerId}/suspend")
            ->assertStatus(422);
    }

    public function test_user_without_manage_permission_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->postJson('/api/v1/users', [
            'name' => 'New Guy',
            'email' => 'newguy@acme.test',
            'roles' => ['Clearing Officer'],
            'password' => 'StaffPass123',
        ])->assertForbidden();

        $this->postJson("/api/v1/users/{$driver->id}/suspend")->assertForbidden();
    }

    public function test_user_without_view_permission_is_forbidden_from_listing(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        app(TenantContext::class)->clear();
        Sanctum::actingAs($driver, ['*']);
        app(TenantContext::class)->set($tenantId);

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->getJson('/api/v1/roles')->assertForbidden();
    }
}
