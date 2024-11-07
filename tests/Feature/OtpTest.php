<?php

namespace Tests\Feature;

use App\Models\Otp;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private string $email;
    private string $phone;

    protected function setUp(): void
    {
        parent::setUp();
        
        $tenant = Tenant::factory()->create();
        
        /** @var User $user */
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'test@example.com',
            'phone' => '+1234567890'
        ]);
        $this->user = $user;

        $this->email = $this->user->email;
        $this->phone = $this->user->phone;
    }

    public function test_can_request_email_otp(): void
    {
        $response = $this->postJson('/api/v1/resend-otp', [
            'type' => 'email',
            'identifier' => $this->email
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('otps', [
            'type' => 'email',
            'identifier' => $this->email,
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id
        ]);
    }

    public function test_can_request_phone_otp(): void
    {
        $response = $this->postJson('/api/v1/resend-otp', [
            'type' => 'phone',
            'identifier' => $this->phone
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('otps', [
            'type' => 'phone',
            'identifier' => $this->phone,
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id
        ]);
    }

    public function test_can_verify_valid_otp(): void
    {
        $otp = Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'type' => 'email',
            'identifier' => $this->email,
            'code' => '123456'
        ]);

        $response->assertOk();
        $this->assertNotNull($otp->fresh()->verified_at);
    }

    public function test_cannot_verify_expired_otp(): void
    {
        $otp = Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->subMinutes(1)
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'type' => 'email',
            'identifier' => $this->email,
            'code' => '123456'
        ]);

        $response->assertStatus(422);
        $this->assertNull($otp->fresh()->verified_at);
    }

    public function test_cannot_verify_invalid_otp(): void
    {
        Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'type' => 'email',
            'identifier' => $this->email,
            'code' => '654321' // Wrong code
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_verify_already_verified_otp(): void
    {
        $otp = Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now()
        ]);

        $response = $this->postJson('/api/v1/verify-otp', [
            'type' => 'email',
            'identifier' => $this->email,
            'code' => '123456'
        ]);

        $response->assertStatus(422);
    }

    public function test_can_request_new_otp_after_expiration(): void
    {
        // Create expired OTP
        Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->subMinutes(1)
        ]);

        // Request new OTP
        $response = $this->postJson('/api/v1/resend-otp', [
            'type' => 'email',
            'identifier' => $this->email
        ]);

        $response->assertOk();
        $this->assertEquals(2, Otp::where('identifier', $this->email)->count());
        $this->assertEquals(1, Otp::where('identifier', $this->email)->active()->count());
    }

    public function test_otp_scopes(): void
    {
        // Create various OTPs
        $activeOtp = Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->addMinutes(10)
        ]);

        $expiredOtp = Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '234567',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->subMinutes(1)
        ]);

        $verifiedOtp = Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '345678',
            'type' => 'phone',
            'identifier' => $this->phone,
            'expires_at' => now()->addMinutes(10),
            'verified_at' => now()
        ]);

        // Test scopes
        $this->assertEquals(1, Otp::active()->count());
        $this->assertEquals(1, Otp::expired()->count());
        $this->assertEquals(2, Otp::unverified()->count());
        $this->assertEquals(2, Otp::forType('email')->count());
        $this->assertEquals(1, Otp::forType('phone')->count());
        $this->assertEquals(2, Otp::forIdentifier($this->email)->count());
    }

    public function test_otp_activity_logging(): void
    {
        $otp = Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->addMinutes(10)
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'otp',
            'description' => 'Generated email OTP',
            'subject_type' => Otp::class,
            'subject_id' => $otp->id,
            'causer_type' => null,
            'causer_id' => null,
        ]);

        // Verify OTP
        $otp->verify();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'otp',
            'description' => 'Verified email OTP',
            'subject_type' => Otp::class,
            'subject_id' => $otp->id,
        ]);
    }

    public function test_cleanup_expired_otps(): void
    {
        // Create expired OTPs
        Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '123456',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->subDays(2)
        ]);

        Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '234567',
            'type' => 'phone',
            'identifier' => $this->phone,
            'expires_at' => now()->subDays(1)
        ]);

        // Create active OTP
        Otp::create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
            'code' => '345678',
            'type' => 'email',
            'identifier' => $this->email,
            'expires_at' => now()->addMinutes(10)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/cleanup-otps');

        $response->assertOk();
        $this->assertEquals(1, Otp::count());
        $this->assertEquals(1, Otp::active()->count());
    }
}
