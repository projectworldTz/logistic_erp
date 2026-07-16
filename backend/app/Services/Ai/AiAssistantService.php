<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Shipment;

/**
 * A tool-using chat assistant scoped to this tenant's own operational
 * data. Every tool queries through the normal Eloquent models, so
 * TenantScope isolates it exactly like every other request — the
 * assistant can never see another tenant's records.
 */
class AiAssistantService
{
    private const MAX_TOOL_ITERATIONS = 5;

    public function __construct(private readonly Client $client) {}

    public function configured(): bool
    {
        return (bool) config('services.anthropic.api_key');
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function chat(array $history, string $message): string
    {
        $model = config('services.anthropic.model');
        $tools = $this->tools();

        $messages = $history;
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = $this->client->messages->create(
            model: $model,
            maxTokens: 2048,
            system: $this->systemPrompt(),
            tools: $tools,
            messages: $messages,
        );

        $iterations = 0;

        while ($response->stopReason === 'tool_use' && $iterations < self::MAX_TOOL_ITERATIONS) {
            $iterations++;
            $toolResults = [];

            foreach ($response->content as $block) {
                if ($block->type === 'tool_use') {
                    $toolResults[] = [
                        'type' => 'tool_result',
                        'toolUseID' => $block->id,
                        'content' => json_encode($this->executeTool($block->name, (array) $block->input)),
                    ];
                }
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->content];
            $messages[] = ['role' => 'user', 'content' => $toolResults];

            $response = $this->client->messages->create(
                model: $model,
                maxTokens: 2048,
                system: $this->systemPrompt(),
                tools: $tools,
                messages: $messages,
            );
        }

        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                return $block->text;
            }
        }

        return '';
    }

    private function systemPrompt(): string
    {
        return 'You are an internal assistant for a Clearing & Forwarding logistics ERP. '
            .'Answer questions about shipments, customers, and finances using the provided tools. '
            .'Be concise and only state figures that came from a tool result — never guess numbers.';
    }

    private function tools(): array
    {
        return [
            [
                'name' => 'get_dashboard_summary',
                'description' => 'Get a snapshot of current activity: shipment counts by status, total customers, and outstanding invoice total.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'list_shipments',
                'description' => 'List recent shipments, optionally filtered by status (booked, in_transit, arrived, cleared, delivered, cancelled).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Optional status filter'],
                    ],
                ],
            ],
            [
                'name' => 'get_overdue_invoices',
                'description' => 'List sent/overdue invoices with customer and amount, most recent first.',
                'inputSchema' => ['type' => 'object', 'properties' => (object) []],
            ],
            [
                'name' => 'find_customer',
                'description' => 'Search customers by company name.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Company name search term'],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    private function executeTool(string $name, array $input): array
    {
        return match ($name) {
            'get_dashboard_summary' => $this->dashboardSummary(),
            'list_shipments' => $this->listShipments($input['status'] ?? null),
            'get_overdue_invoices' => $this->overdueInvoices(),
            'find_customer' => $this->findCustomer($input['query'] ?? ''),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }

    private function dashboardSummary(): array
    {
        return [
            'shipments_by_status' => Shipment::query()
                ->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'customers_total' => Customer::query()->count(),
            'invoices_outstanding_total' => (float) Invoice::query()
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
                ->sum('total_amount'),
        ];
    }

    private function listShipments(?string $status): array
    {
        return Shipment::query()
            ->with('customer')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Shipment $s) => [
                'shipment_number' => $s->shipment_number,
                'customer' => $s->customer?->company_name,
                'status' => $s->status->value,
                'eta' => $s->eta?->toDateString(),
            ])
            ->all();
    }

    private function overdueInvoices(): array
    {
        return Invoice::query()
            ->with('customer')
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Invoice $i) => [
                'invoice_number' => $i->invoice_number,
                'customer' => $i->customer?->company_name,
                'status' => $i->status->value,
                'total_amount' => (float) $i->total_amount,
                'due_date' => $i->due_date?->toDateString(),
            ])
            ->all();
    }

    private function findCustomer(string $query): array
    {
        return Customer::query()
            ->where('company_name', 'like', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(fn (Customer $c) => [
                'company_name' => $c->company_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'status' => $c->status->value,
            ])
            ->all();
    }
}
