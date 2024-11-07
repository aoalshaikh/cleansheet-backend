<?php

namespace Tests\Unit\Services\Communication;

use App\Services\Communication\SmsService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    private SmsService $smsService;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up mock config
        Config::set('services.sms.url', 'http://sms-provider.test');
        Config::set('services.sms.api_key', 'test-api-key');
        Config::set('services.sms.sender_id', 'TEST');
        Config::set('services.sms.timeout', 5);

        // Set up mock HTTP client
        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        // Create service with mocked client
        $this->smsService = new SmsService($client);
    }

    public function test_send_sms_successfully(): void
    {
        // Mock successful response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'success' => true,
            'message_id' => '123456',
        ])));

        $result = $this->smsService->send('+1234567890', 'Test message');

        $this->assertTrue($result);
    }

    public function test_send_sms_handles_api_error(): void
    {
        // Mock error response
        $this->mockHandler->append(new Response(400, [], json_encode([
            'success' => false,
            'error' => 'Invalid phone number',
        ])));

        Log::shouldReceive('error')
            ->once()
            ->with('SMS sending failed', \Mockery::any());

        $result = $this->smsService->send('+1234567890', 'Test message');

        $this->assertFalse($result);
    }

    public function test_send_sms_handles_network_error(): void
    {
        // Mock network error
        $this->mockHandler->append(new Response(500));

        Log::shouldReceive('error')
            ->once()
            ->with('SMS sending failed', \Mockery::any());

        $result = $this->smsService->send('+1234567890', 'Test message');

        $this->assertFalse($result);
    }

    public function test_send_sms_handles_timeout(): void
    {
        // Mock timeout error
        $this->mockHandler->append(new Response(408));

        Log::shouldReceive('error')
            ->once()
            ->with('SMS sending failed', \Mockery::any());

        $result = $this->smsService->send('+1234567890', 'Test message');

        $this->assertFalse($result);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
