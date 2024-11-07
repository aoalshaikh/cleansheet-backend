<?php

namespace Database\Factories;

use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class OtpFactory extends Factory
{
    protected $model = Otp::class;

    public function definition(): array
    {
        return [
            'otpable_type' => User::class,
            'otpable_id' => User::factory(),
            'code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'type' => $this->faker->randomElement(['email', 'phone']),
            'identifier' => fn (array $attributes) => 
                $attributes['type'] === 'email' 
                    ? $this->faker->email() 
                    : $this->faker->e164PhoneNumber(),
            'expires_at' => Carbon::now()->addMinutes(10),
            'verified_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(function () {
            return [
                'expires_at' => Carbon::now()->subMinutes(1),
            ];
        });
    }

    public function verified(): self
    {
        return $this->state(function () {
            return [
                'verified_at' => Carbon::now(),
            ];
        });
    }

    public function forUser(User $user): self
    {
        return $this->state(function () use ($user) {
            return [
                'otpable_type' => User::class,
                'otpable_id' => $user->id,
                'identifier' => $user->phone ?? $user->email,
                'type' => $user->phone ? 'phone' : 'email',
            ];
        });
    }

    public function email(): self
    {
        return $this->state(function () {
            return [
                'type' => 'email',
                'identifier' => $this->faker->email(),
            ];
        });
    }

    public function phone(): self
    {
        return $this->state(function () {
            return [
                'type' => 'phone',
                'identifier' => $this->faker->e164PhoneNumber(),
            ];
        });
    }

    public function withCode(string $code): self
    {
        return $this->state(function () use ($code) {
            return [
                'code' => $code,
            ];
        });
    }
}
