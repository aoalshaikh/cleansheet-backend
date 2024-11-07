<?php

namespace App\Providers;

use App\Services\Auth\OtpService;
use App\Services\Communication\SmsService;
use App\Services\Logging\ActivityLogger;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class ServicesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register SmsService as singleton
        $this->app->singleton(SmsService::class, function ($app) {
            $client = new Client([
                'base_uri' => config('services.sms.url'),
                'timeout' => config('services.sms.timeout', 30),
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.sms.api_key'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            return new SmsService($client);
        });

        // Register OtpService as singleton
        $this->app->singleton(OtpService::class, function ($app) {
            return new OtpService(
                $app->make(SmsService::class)
            );
        });

        // Register ActivityLogger as singleton
        $this->app->singleton(ActivityLogger::class, function ($app) {
            return new ActivityLogger(
                $app->make('request')
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Configure default settings for services
        config([
            'services.sms.url' => config('services.sms.url', 'http://sms-provider.test'),
            'services.sms.api_key' => config('services.sms.api_key', 'test-api-key'),
            'services.sms.sender_id' => config('services.sms.sender_id', 'TEST'),
            'services.sms.timeout' => config('services.sms.timeout', 30),
        ]);

        // Register custom validation rules
        $this->registerValidationRules();
    }

    /**
     * Register custom validation rules.
     */
    private function registerValidationRules(): void
    {
        // Add phone number validation rule
        Validator::extend('phone', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\+[1-9]\d{1,14}$/', $value);
        }, 'The :attribute must be a valid E.164 phone number.');

        // Add OTP code validation rule
        Validator::extend('otp', function ($attribute, $value, $parameters, $validator) {
            return preg_match('/^\d{6}$/', $value);
        }, 'The :attribute must be a 6-digit code.');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            SmsService::class,
            OtpService::class,
            ActivityLogger::class,
        ];
    }
}
