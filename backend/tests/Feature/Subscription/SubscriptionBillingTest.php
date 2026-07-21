<?php

namespace Tests\Feature\Subscription;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'owner@acme.test', string $companyName = 'Acme Logistics'): array
    {
        $response = $this->postJson('/api/v1/tenants/register', [
            'plan_code' => 'starter',
            'owner' => ['name' => 'Jane Doe', 'email' => $email, 'password' => 'SecurePass123'],
            'company' => [
                'name' => $companyName,
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

    public function test_owner_can_view_their_subscription_and_billing_profile(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/subscription')
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'starter')
            ->assertJsonPath('data.status', 'trialing');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/billing-profile')
            ->assertOk()
            ->assertJsonPath('data.billing_name', 'Acme Logistics');
    }

    public function test_owner_can_switch_plans(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/subscription/plan', ['plan_code' => 'professional', 'billing_cycle' => 'yearly'])
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'professional')
            ->assertJsonPath('data.billing_cycle', 'yearly');
    }

    public function test_owner_can_update_billing_profile(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/billing-profile', [
                'billing_email' => 'billing@acme.test',
                'tax_id' => 'TIN-12345',
            ])
            ->assertOk()
            ->assertJsonPath('data.billing_email', 'billing@acme.test')
            ->assertJsonPath('data.tax_id', 'TIN-12345');
    }

    public function test_user_without_manage_permission_cannot_change_plan(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'No Access',
                'email' => 'noaccess@acme.test',
                'roles' => ['Driver'],
                'password' => 'SecurePass123',
            ])->assertCreated();

        app(TenantContext::class)->clear();
        $restrictedUser = User::where('email', 'noaccess@acme.test')->firstOrFail();
        Sanctum::actingAs($restrictedUser, ['*']);

        $this->putJson('/api/v1/subscription/plan', ['plan_code' => 'professional', 'billing_cycle' => 'monthly'])
            ->assertForbidden();
    }

    public function test_command_generates_an_invoice_for_an_active_subscription_and_flags_overdue_ones(): void
    {
        $registration = $this->registerTenant();
        $tenantId = $registration['user']['tenant_id'];

        app(TenantContext::class)->set($tenantId);
        $subscription = Subscription::query()->latest()->firstOrFail();
        $subscription->update(['status' => 'active', 'starts_at' => now()->subMonths(2)]);

        // An old pending invoice from a prior period, past its due date —
        // should flip to overdue by the same command run.
        SubscriptionInvoice::create([
            'tenant_id' => $tenantId,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'plan_name' => 'Starter',
            'amount' => 49,
            'currency' => 'USD',
            'period_start' => now()->subMonths(2),
            'period_end' => now()->subMonth(),
            'due_date' => now()->subMonths(2)->addDays(7),
            'status' => 'pending',
        ]);
        app(TenantContext::class)->clear();

        Artisan::call('subscriptions:generate-invoices');

        app(TenantContext::class)->set($tenantId);
        $this->assertDatabaseHas('subscription_invoices', [
            'subscription_id' => $subscription->id,
            'status' => 'overdue',
        ]);

        $this->assertGreaterThanOrEqual(1, SubscriptionInvoice::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->count());
    }
}
