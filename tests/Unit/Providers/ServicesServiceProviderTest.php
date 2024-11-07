<?php

namespace Tests\Unit\Providers;

use App\Providers\ServicesServiceProvider;
use App\Services\Auth\OtpService;
use App\Services\Communication\SmsService;
use App\Services\Logging\ActivityLogger;
use GuzzleHttp\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServicesServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_service_is_bound_as_singleton(): void
    {
        $provider = new ServicesServiceProvider($this->app);
        $provider->register();

        $instance1 = $this->app->make(OtpService::class);
        $instance2 = $this->app->make(OtpService::class);

        $this->assertInstanceOf(OtpService::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    public function test_sms_service_is_bound_as_singleton(): void
    {
        $provider = new ServicesServiceProvider($this->app);
        $provider->register();

        $instance1 = $this->app->make(SmsService::class);
        $instance2 = $this->app->make(SmsService::class);

        $this->assertInstanceOf(SmsService::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    public function test_activity_logger_is_bound_as_singleton(): void
    {
        $provider = new ServicesServiceProvider($this->app);
        $provider->register();

        $instance1 = $this->app->make(ActivityLogger::class);
        $instance2 = $this->app->make(ActivityLogger::class);

        $this->assertInstanceOf(ActivityLogger::class, $instance1);
        $this->assertSame($instance1, $instance2);
    }

    public function test_sms_service_is_configured_with_http_client(): void
    {
        $provider = new ServicesServiceProvider($this->app);
        $provider->register();

        $smsService = $this->app->make(SmsService::class);
        
        // Use reflection to check if HTTP client is configured
        $reflection = new \ReflectionClass($smsService);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        
        $this->assertInstanceOf(Client::class, $property->getValue($smsService));
    }

    public function test_otp_service_is_configured_with_sms_service(): void
    {
        $provider = new ServicesServiceProvider($this->app);
        $provider->register();

        $otpService = $this->app->make(OtpService::class);
        
        // Use reflection to check if SMS service is configured
        $reflection = new \ReflectionClass($otpService);
        $property = $reflection->getProperty('smsService');
        $property->setAccessible(true);
        
        $this->assertInstanceOf(SmsService::class, $property->getValue($otpService));
    }

    public function test_activity_logger_is_configured_with_request(): void
    {
        $provider = new ServicesServiceProvider($this->app);
        $provider->register();

        $activityLogger = $this->app->make(ActivityLogger::class);
        
        // Use reflection to check if request is configured
        $reflection = new \ReflectionClass($activityLogger);
        $property = $reflection->getProperty('request');
        $property->setAccessible(true);
        
        $this->assertNotNull($property->getValue($activityLogger));
    }

    public function test_services_are_configured_with_correct_settings(): void
    {
        config([
            'services.sms.url' => 'http://test-sms.com',
            'services.sms.api_key' => 'test-key',
            'services.sms.sender_id' => 'TEST',
            'services.sms.timeout' => 30,
        ]);

        $provider = new ServicesServiceProvider($this->app);
        $provider->register();

        $smsService = $this->app->make(SmsService::class);
        
        // Use reflection to check configuration
        $reflection = new \ReflectionClass($smsService);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);
        $config = $property->getValue($smsService);

        $this->assertEquals('http://test-sms.com', $config['url']);
        $this->assertEquals('test-key', $config['api_key']);
        $this->assertEquals('TEST', $config['sender_id']);
        $this->assertEquals(30, $config['timeout']);
    }

    public function test_services_can_be_resolved_from_container(): void
    {
        $this->assertInstanceOf(
            OtpService::class,
            $this->app->make(OtpService::class)
        );

        $this->assertInstanceOf(
            SmsService::class,
            $this->app->make(SmsService::class)
        );

        $this->assertInstanceOf(
            ActivityLogger::class,
            $this->app->make(ActivityLogger::class)
        );
    }

    public function test_services_are_bound_with_correct_abstracts(): void
    {
        $this->assertTrue($this->app->bound(OtpService::class));
        $this->assertTrue($this->app->bound(SmsService::class));
        $this->assertTrue($this->app->bound(ActivityLogger::class));
    }
}
