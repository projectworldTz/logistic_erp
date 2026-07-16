<?php

namespace App\Services\Ai;

use Anthropic\Client;

/**
 * Extracts structured lead/shipment-booking fields from a raw pasted
 * email using a single forced tool call — a "paste-to-parse" review
 * step, not an inbound-mailbox integration. Nothing is created here;
 * the caller reviews/edits the extracted fields before saving them.
 */
class AiEmailParserService
{
    public function __construct(private readonly Client $client) {}

    public function configured(): bool
    {
        return (bool) config('services.anthropic.api_key');
    }

    public function parse(string $emailText): array
    {
        $tool = [
            'name' => 'extract_shipment_lead',
            'description' => 'Extract shipment/lead booking details from a customer email.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'customer_name' => ['type' => 'string', 'description' => 'The company or contact name requesting the booking'],
                    'customer_email' => ['type' => 'string', 'description' => 'The sender\'s email address, if present'],
                    'cargo_description' => ['type' => 'string', 'description' => 'What the cargo is'],
                    'origin_port' => ['type' => 'string'],
                    'destination_port' => ['type' => 'string'],
                    'mode' => ['type' => 'string', 'enum' => ['sea', 'air', 'land'], 'description' => 'Transport mode, best guess if not explicit'],
                    'direction' => ['type' => 'string', 'enum' => ['import', 'export'], 'description' => 'Best guess if not explicit'],
                    'notes' => ['type' => 'string', 'description' => 'Any other relevant detail from the email'],
                ],
                'required' => ['customer_name'],
            ],
        ];

        $response = $this->client->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 1024,
            toolChoice: ['type' => 'tool', 'name' => 'extract_shipment_lead'],
            tools: [$tool],
            messages: [[
                'role' => 'user',
                'content' => "Extract the booking/lead details from this email. If a field isn't mentioned, omit it rather than guessing a specific value.\n\n{$emailText}",
            ]],
        );

        foreach ($response->content as $block) {
            if ($block->type === 'tool_use') {
                return (array) $block->input;
            }
        }

        return [];
    }
}
