<?php

namespace Tests\Unit\Services\Auth;

use App\Models\Otp;
use App\Models\User;
use App\Services\Auth\OtpService;
use App\Services\Communication\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otpService;
    private SmsService $smsService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->smsService = Mockery::mock(SmsService::class);
        $this->otpService = new OtpService($this->smsService);
        
        $this->user = User::factory()->create([
            'phone' => '+1234567890',
        ]);
    }

    public function test_generate_creates_valid_otp(): void
    {
        $this->smsService->shouldReceive('send')
            ->once()
            ->andReturn(true);

        $otp = $this->otpService->generate($this->user, 'phone', $this->user->phone);

        $this->assertNotNull($otp);
        $this->assertInstanceOf(Otp::class, $otp);
        $this->assertEquals('phone', $otp->type);
        $this->assertEquals($this->user->phone, $otp->identifier);
        $this->assertNull($otp->verified_at);
        $this->assertTrue(strlen($otp->code) === 6);
        $this->assertTrue($otp->expires_at->isFuture());
    }

    public function test_verify_validates_correct_otp(): void
    {
        $otp = $this->user->otps()->create([
            'code' => '123456',
            'type' => 'phone',
            'identifier' => $this->user->phone,
            'expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->otpService->verify(
            $this->user,
            'phone',
            $this->user->phone,
            '123456'
        );

        $this->assertTrue($result);
        $this->assertNotNull($otp->fresh()->verified_at);
    }

    public function test_verify_rejects_incorrect_otp(): void
    {
        $this->user->otps()->create([
            'code' => '123456',
            'type' => 'phone',
            'identifier' => $this->user->phone,
            'expires_at' => now()->addMinutes(10),
        ]);

        $result = $this->otpService->verify(
            $this->user,
            'phone',
            $this->user->phone,
            'wrong-code'
        );

        $this->assertFalse($result);
    }

    public function test_verify_rejects_expired_otp(): void
    {
        $this->user->otps()->create([
            'code' => '123456',
            'type' => 'phone',
            'identifier' => $this->user->phone,
            'expires_at' => now()->subMinutes(1),
        ]);

        $result = $this->otpService->verify(
            $this->user,
            'phone',
            $this->user->phone,
            '123456'
        );

        $this->assertFalse($result);
    }

    public function test_verify_rejects_already_verified_otp(): void
    {
        $this->user->otps()->create([
            'code' => '123456',
            'type' => 'phone',
            'identifier' => $this->user->phone,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now(),
        ]);

        $result = $this->otpService->verify(
            $this->user,
            'phone',
            $this->user->phone,
            '123456'
        );

        $this->assertFalse($result);
    }

    public function test_cleanup_removes_expired_otps(): void
    {
        // Create expired OTP
        $this->user->otps()->create([
            'code' => '123456',
            'type' => 'phone',
            'identifier' => $this->user->phone,
            'expires_at' => now()->subMinutes(1),
        ]);

        // Create valid OTP
        $this->user->otps()->create([
            'code' => '654321',
            'type' => 'phone',
            'identifier' => $this->user->phone,
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->otpService->cleanup();

        $this->assertDatabaseCount('otps', 1);
        $this->assertDatabaseHas('otps', [
            'code' => '654321',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
