<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CleanupExpiredOtps;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupExpiredOtpsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_cleanup_removes_expired_otps(): void
    {
        // Create expired OTPs
        Otp::factory()->count(3)->expired()->create([
            'otpable_id' => $this->user->id,
        ]);

        // Create valid OTPs
        Otp::factory()->count(2)->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $this->assertDatabaseCount('otps', 5);

        // Run the cleanup job
        (new CleanupExpiredOtps)->handle();

        // Assert expired OTPs were removed
        $this->assertDatabaseCount('otps', 2);
        $this->assertEquals(
            0,
            Otp::expired()->count(),
            'Expected no expired OTPs to remain'
        );
    }

    public function test_cleanup_keeps_valid_otps(): void
    {
        // Create valid OTPs
        $validOtps = Otp::factory()->count(3)->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Create expired OTPs
        Otp::factory()->count(2)->expired()->create([
            'otpable_id' => $this->user->id,
        ]);

        $this->assertDatabaseCount('otps', 5);

        // Run the cleanup job
        (new CleanupExpiredOtps)->handle();

        // Assert valid OTPs were kept
        $this->assertDatabaseCount('otps', 3);
        foreach ($validOtps as $otp) {
            $this->assertModelExists($otp);
        }
    }

    public function test_cleanup_handles_no_expired_otps(): void
    {
        // Create only valid OTPs
        Otp::factory()->count(3)->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        $this->assertDatabaseCount('otps', 3);

        // Run the cleanup job
        (new CleanupExpiredOtps)->handle();

        // Assert nothing was removed
        $this->assertDatabaseCount('otps', 3);
    }

    public function test_cleanup_handles_empty_table(): void
    {
        $this->assertDatabaseCount('otps', 0);

        // Run the cleanup job
        (new CleanupExpiredOtps)->handle();

        // Assert nothing changed
        $this->assertDatabaseCount('otps', 0);
    }

    public function test_cleanup_only_removes_expired_otps(): void
    {
        // Create a mix of OTPs
        Otp::factory()->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->subMinutes(10), // Expired
        ]);

        Otp::factory()->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addMinutes(5), // Valid
        ]);

        Otp::factory()->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->subSeconds(1), // Just expired
        ]);

        Otp::factory()->create([
            'otpable_id' => $this->user->id,
            'expires_at' => Carbon::now()->addSeconds(1), // About to expire
        ]);

        $this->assertDatabaseCount('otps', 4);

        // Run the cleanup job
        (new CleanupExpiredOtps)->handle();

        // Assert only expired OTPs were removed
        $this->assertDatabaseCount('otps', 2);
        $this->assertEquals(
            2,
            Otp::where('expires_at', '>', Carbon::now())->count(),
            'Expected only future OTPs to remain'
        );
    }
}
