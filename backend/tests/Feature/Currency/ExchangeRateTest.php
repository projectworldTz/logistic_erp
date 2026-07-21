<?php

namespace Tests\Feature\Currency;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(PlanSeeder::class);
    }

    private function registerTenant(string $email = 'jane@acme.test', string $companyName = 'Acme Logistics'): array
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

    public function test_owner_can_record_and_list_exchange_rates(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $create = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/exchange-rates', [
                'base_currency' => 'usd',
                'quote_currency' => 'tzs',
                'rate' => 2600.5,
                'rate_date' => now()->toDateString(),
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.base_currency', 'USD');
        $create->assertJsonPath('data.quote_currency', 'TZS');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/finance/exchange-rates')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Re-recording the same pair/date corrects it in place rather than duplicating.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/exchange-rates', [
                'base_currency' => 'USD',
                'quote_currency' => 'TZS',
                'rate' => 2610,
                'rate_date' => now()->toDateString(),
            ])->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/finance/exchange-rates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rate', '2610.000000');
    }

    public function test_convert_uses_direct_rate_and_falls_back_to_inverse(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/exchange-rates', [
                'base_currency' => 'USD',
                'quote_currency' => 'TZS',
                'rate' => 2500,
                'rate_date' => now()->toDateString(),
            ])->assertCreated();

        // Direct: 100 USD -> TZS at rate 2500.
        $direct = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/exchange-rates/convert', [
                'amount' => 100,
                'from' => 'USD',
                'to' => 'TZS',
            ]);
        $direct->assertOk();
        $direct->assertJsonPath('converted_amount', 250000);

        // Inverse: converting TZS -> USD with only a USD->TZS rate on file.
        $inverse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/exchange-rates/convert', [
                'amount' => 2500,
                'from' => 'TZS',
                'to' => 'USD',
            ]);
        $inverse->assertOk();
        $inverse->assertJsonPath('converted_amount', 1);

        // Same currency is always a 1:1 passthrough, no rate needed.
        $same = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/exchange-rates/convert', [
                'amount' => 42,
                'from' => 'usd',
                'to' => 'usd',
            ]);
        $same->assertOk();
        $same->assertJsonPath('converted_amount', 42);
    }

    public function test_convert_returns_422_when_no_rate_is_available(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/finance/exchange-rates/convert', [
                'amount' => 100,
                'from' => 'EUR',
                'to' => 'JPY',
            ])->assertStatus(422);
    }

    public function test_exchange_rates_are_isolated_per_tenant(): void
    {
        $registrationA = $this->registerTenant('jane@acme.test', 'Acme Logistics');
        $this->withHeader('Authorization', "Bearer {$registrationA['token']}")
            ->postJson('/api/v1/finance/exchange-rates', [
                'base_currency' => 'USD',
                'quote_currency' => 'TZS',
                'rate' => 2500,
                'rate_date' => now()->toDateString(),
            ])->assertCreated();

        $this->registerTenant('bob@globex.test', 'Globex Freight');
        app(TenantContext::class)->clear();
        $userB = User::where('email', 'bob@globex.test')->firstOrFail();
        Sanctum::actingAs($userB, ['*']);

        $this->getJson('/api/v1/finance/exchange-rates')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/finance/exchange-rates/convert', [
            'amount' => 100,
            'from' => 'USD',
            'to' => 'TZS',
        ])->assertStatus(422);
    }

    public function test_user_without_manage_permission_cannot_record_a_rate(): void
    {
        $registration = $this->registerTenant();
        $token = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', [
                'name' => 'Driver Dan',
                'email' => 'driver@acme.test',
                'roles' => ['Driver'],
                'password' => 'DriverPass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $driverToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@acme.test',
            'password' => 'DriverPass123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$driverToken}")
            ->postJson('/api/v1/finance/exchange-rates', [
                'base_currency' => 'USD',
                'quote_currency' => 'TZS',
                'rate' => 2500,
                'rate_date' => now()->toDateString(),
            ])->assertForbidden();
    }
}
