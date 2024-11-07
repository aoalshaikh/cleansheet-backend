<?php

namespace Tests\Unit\Models;

use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_otp_belongs_to_user(): void
    {
        $otp = Otp::factory()->create([
            'otpable_type' => User::class,
            'otpable_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $otp->otpable);
        $this->assertEquals($this->user->id, $otp->otpable->id);
    }

    public function test_is_expired_returns_true_for_expired_otp(): void
    {
        $otp = Otp::factory()->expired()->create([
            'otpable_id' => $this->user->id,
        ]);

        $this->assertTrue($otp->isExpired());
    }

    public function test_is_expired_returns_false_for_valid_otp(): void
    {
        $otp = Otp::factory()->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $this->assertFalse($otp->isExpired());
    }

    public function test_is_verified_returns_true_for_verified_otp(): void
    {
        $otp = Otp::factory()->verified()->create([
            'otpable_id' => $this->user->id,
        ]);

        $this->assertTrue($otp->isVerified());
    }

    public function test_is_verified_returns_false_for_unverified_otp(): void
    {
        $otp = Otp::factory()->create([
            'otpable_id' => $this->user->id,
            'verified_at' => null,
        ]);

        $this->assertFalse($otp->isVerified());
    }

    public function test_verify_marks_otp_as_verified(): void
    {
        $otp = Otp::factory()->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $result = $otp->verify();

        $this->assertTrue($result);
        $this->assertNotNull($otp->verified_at);
        $this->assertTrue($otp->isVerified());
    }

    public function test_verify_fails_for_expired_otp(): void
    {
        $otp = Otp::factory()->expired()->create([
            'otpable_id' => $this->user->id,
        ]);

        $result = $otp->verify();

        $this->assertFalse($result);
        $this->assertNull($otp->verified_at);
        $this->assertFalse($otp->isVerified());
    }

    public function test_verify_fails_for_already_verified_otp(): void
    {
        $otp = Otp::factory()->verified()->create([
            'otpable_id' => $this->user->id,
        ]);

        $originalVerifiedAt = $otp->verified_at;
        $result = $otp->verify();

        $this->assertFalse($result);
        $this->assertEquals($originalVerifiedAt, $otp->verified_at);
    }

    public function test_unverified_scope(): void
    {
        Otp::factory()->count(2)->verified()->create([
            'otpable_id' => $this->user->id,
        ]);
        Otp::factory()->count(3)->create([
            'otpable_id' => $this->user->id,
            'verified_at' => null,
        ]);

        $unverifiedCount = Otp::unverified()->count();

        $this->assertEquals(3, $unverifiedCount);
    }

    public function test_expired_scope(): void
    {
        Otp::factory()->count(2)->expired()->create([
            'otpable_id' => $this->user->id,
        ]);
        Otp::factory()->count(3)->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $expiredCount = Otp::expired()->count();

        $this->assertEquals(2, $expiredCount);
    }

    public function test_active_scope(): void
    {
        // Create expired OTPs
        Otp::factory()->count(2)->expired()->create([
            'otpable_id' => $this->user->id,
        ]);

        // Create verified OTPs
        Otp::factory()->count(2)->verified()->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Create active OTPs
        Otp::factory()->count(3)->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
            'verified_at' => null,
        ]);

        $activeCount = Otp::active()->count();

        $this->assertEquals(3, $activeCount);
    }

    public function test_for_type_scope(): void
    {
        Otp::factory()->count(2)->email()->create([
            'otpable_id' => $this->user->id,
        ]);
        Otp::factory()->count(3)->phone()->create([
            'otpable_id' => $this->user->id,
        ]);

        $emailCount = Otp::forType('email')->count();
        $phoneCount = Otp::forType('phone')->count();

        $this->assertEquals(2, $emailCount);
        $this->assertEquals(3, $phoneCount);
    }

    public function test_for_identifier_scope(): void
    {
        $identifier = 'test@example.com';
        
        Otp::factory()->count(2)->create([
            'otpable_id' => $this->user->id,
            'identifier' => $identifier,
        ]);
        Otp::factory()->count(3)->create([
            'otpable_id' => $this->user->id,
            'identifier' => 'other@example.com',
        ]);

        $count = Otp::forIdentifier($identifier)->count();

        $this->assertEquals(2, $count);
    }

    public function test_with_code_scope(): void
    {
        $code = '123456';
        
        Otp::factory()->count(2)->withCode($code)->create([
            'otpable_id' => $this->user->id,
        ]);
        Otp::factory()->count(3)->create([
            'otpable_id' => $this->user->id,
        ]);

        $count = Otp::withCode($code)->count();

        $this->assertEquals(2, $count);
    }
}
