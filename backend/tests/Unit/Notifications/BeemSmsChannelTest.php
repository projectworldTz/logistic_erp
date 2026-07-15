<?php

namespace Tests\Unit\Notifications;

use App\Services\Notifications\Channels\BeemSmsChannel;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BeemSmsChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.beem.api_key' => 'test-api-key',
            'services.beem.secret_key' => 'test-secret-key',
            'services.beem.sender_id' => 'INFO',
        ]);
    }

    public function test_send_posts_the_expected_payload_and_normalizes_a_local_number(): void
    {
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true], 200)]);

        (new BeemSmsChannel())->send('0712345678', 'Your shipment has been delivered.');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://apisms.beem.africa/v1/send'
                && $request['source_addr'] === 'INFO'
                && $request['message'] === 'Your shipment has been delivered.'
                && $request['recipients'][0]['dest_addr'] === '255712345678'
                && $request->hasHeader('Authorization');
        });
    }

    public function test_send_leaves_an_already_international_number_untouched(): void
    {
        Http::fake(['apisms.beem.africa/*' => Http::response(['successful' => true], 200)]);

        (new BeemSmsChannel())->send('+255712345678', 'Hello');

        Http::assertSent(fn ($request) => $request['recipients'][0]['dest_addr'] === '255712345678');
    }

    public function test_a_failed_response_does_not_throw(): void
    {
        Http::fake(['apisms.beem.africa/*' => Http::response(['message' => 'invalid sender'], 422)]);

        (new BeemSmsChannel())->send('255712345678', 'Hello');

        $this->assertTrue(true);
    }
}
