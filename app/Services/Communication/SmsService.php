<?php

namespace App\Services\Communication;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $client;
    protected $config;

    public function __construct(?Client $client = null)
    {
        $this->config = [
            'url' => config('services.sms.url'),
            'api_key' => config('services.sms.api_key'),
            'sender_id' => config('services.sms.sender_id'),
            'timeout' => config('services.sms.timeout', 30),
        ];

        $this->client = $client ?? new Client([
            'base_uri' => $this->config['url'],
            'timeout' => $this->config['timeout'],
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function send(string $to, string $message): bool
    {
        try {
            $response = $this->client->post('/send', [
                'json' => [
                    'to' => $to,
                    'message' => $message,
                    'sender_id' => $this->config['sender_id'],
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error('SMS sending failed', [
                    'status' => $response->getStatusCode(),
                    'response' => json_decode($response->getBody()->getContents(), true),
                    'to' => $to,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('SMS sending failed', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);
            return false;
        }
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
