<?php

namespace Tests\Feature\Ai;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantTest extends TestCase
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

    public function test_assistant_chat_returns_503_when_not_configured(): void
    {
        config(['services.anthropic.api_key' => null]);

        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/v1/ai/assistant/chat', ['message' => 'How many shipments do we have?'])
            ->assertStatus(503);
    }

    public function test_email_parser_returns_503_when_not_configured(): void
    {
        config(['services.anthropic.api_key' => null]);

        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->postJson('/api/v1/ai/email-parser/parse', ['email_text' => 'Please book a shipment for us.'])
            ->assertStatus(503);
    }

    public function test_assistant_chat_requires_the_message_field(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->withHeader('Accept', 'application/json')
            ->postJson('/api/v1/ai/assistant/chat', [])
            ->assertJsonValidationErrors(['message']);
    }

    public function test_email_parser_requires_the_email_text_field(): void
    {
        $registration = $this->registerTenant();

        $this->withHeader('Authorization', "Bearer {$registration['token']}")
            ->withHeader('Accept', 'application/json')
            ->postJson('/api/v1/ai/email-parser/parse', [])
            ->assertJsonValidationErrors(['email_text']);
    }

    public function test_user_without_ai_permissions_is_forbidden(): void
    {
        $registration = $this->registerTenant();
        $ownerToken = $registration['token'];

        $this->withHeader('Authorization', "Bearer {$ownerToken}")
            ->postJson('/api/v1/users', [
                'name' => 'Warehouse Staffer',
                'email' => 'warehouse@acme.test',
                'role' => 'Warehouse Staff',
                'password' => 'RolePass123',
            ])->assertCreated();

        config(['auth.defaults.guard' => 'web']);
        $this->app['auth']->forgetGuards();

        $restrictedToken = $this->postJson('/api/v1/auth/login', [
            'email' => 'warehouse@acme.test',
            'password' => 'RolePass123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$restrictedToken}")
            ->postJson('/api/v1/ai/assistant/chat', ['message' => 'Hi'])
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$restrictedToken}")
            ->postJson('/api/v1/ai/email-parser/parse', ['email_text' => 'Hi'])
            ->assertForbidden();
    }
}
