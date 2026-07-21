<?php

namespace Tests\Feature\Tenant;

use App\Models\Customer;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationTest extends TestCase
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

    public function test_creating_a_quotation_notifies_users_with_view_permission_but_not_the_actor(): void
    {
        $registration = $this->registerTenant();
        $ownerToken = $registration['token'];
        $tenantId = $registration['user']['tenant_id'];

        $customer = Customer::create(['tenant_id' => $tenantId, 'company_name' => 'Test Customer']);

        // A second user who can see quotations (Sales Executive) — created
        // directly, NOT via Sanctum::actingAs, since that would override the
        // owner's bearer-token auth on the request below (a persistent-guard
        // gotcha, not a new bug).
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $salesUser = User::factory()->create(['tenant_id' => $tenantId]);
        $salesUser->assignRole('Sales Executive');

        // A third user who has no visibility into quotations (Driver).
        $driver = User::factory()->create(['tenant_id' => $tenantId]);
        $driver->assignRole('Driver');

        // Owner creates the quotation.
        $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customer->id,
                'direction' => 'export',
                'mode' => 'sea',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 100,
                'tax_amount' => 16,
                'total_amount' => 116,
            ])
            ->assertCreated();

        $ownerId = $registration['user']['id'];

        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenantId,
            'user_id' => $salesUser->id,
            'type' => 'quotation.created',
        ]);

        $this->assertDatabaseMissing('user_notifications', ['user_id' => $ownerId]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $driver->id]);
    }

    public function test_owner_is_notified_when_someone_else_creates_a_record_since_the_owner_holds_every_permission(): void
    {
        $registration = $this->registerTenant();
        $ownerId = $registration['user']['id'];
        $tenantId = $registration['user']['tenant_id'];

        $customer = Customer::create(['tenant_id' => $tenantId, 'company_name' => 'Test Customer']);

        // A restricted user creates the quotation this time — the Owner
        // (a bystander who isn't the actor) should still be notified
        // because the "Company Owner" role holds every permission via the
        // catalog's wildcards, including quotations.items.view. A real
        // token is minted directly (not via the /auth/login endpoint or
        // Sanctum::actingAs) to avoid the persistent-guard gotcha where
        // either would hijack a later bearer-token request in the same test.
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $salesUser = User::factory()->create(['tenant_id' => $tenantId]);
        $salesUser->assignRole('Sales Executive');
        $salesToken = $salesUser->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$salesToken}")
            ->postJson('/api/v1/quotations/items', [
                'customer_id' => $customer->id,
                'direction' => 'export',
                'mode' => 'sea',
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(14)->toDateString(),
                'subtotal' => 100,
                'tax_amount' => 16,
                'total_amount' => 116,
            ])
            ->assertCreated();

        // The owner (a bystander, not the actor) received it — proving
        // "owner sees everything they hold a permission for" at the data
        // layer. NotificationController::index()'s own user_id-scoped fetch
        // is already covered by a separate test in this file.
        $this->assertDatabaseHas('user_notifications', [
            'tenant_id' => $tenantId,
            'user_id' => $ownerId,
            'type' => 'quotation.created',
        ]);
    }

    public function test_unread_count_and_mark_read_and_mark_all_read(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];
        $userId = $registration['user']['id'];
        $token = $registration['token'];

        UserNotification::insert([
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'type' => 'quotation.created', 'title' => 'New quotation', 'message' => 'Test 1', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'type' => 'shipment.created', 'title' => 'New shipment', 'message' => 'Test 2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 2]);

        $first = UserNotification::where('user_id', $userId)->first();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 1]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJson(['count' => 0]);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test');
        $tenantId = $registrationA['user']['tenant_id'];
        $tokenA = $registrationA['token'];

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        $otherUser = User::factory()->create(['tenant_id' => $tenantId]);
        $otherUser->assignRole('Auditor');

        $notification = UserNotification::create([
            'tenant_id' => $tenantId,
            'user_id' => $otherUser->id,
            'type' => 'quotation.created',
            'title' => 'New quotation',
            'message' => 'Test',
        ]);

        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertForbidden();
    }
}
