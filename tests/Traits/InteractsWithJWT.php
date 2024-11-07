<?php

namespace Tests\Traits;

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

trait InteractsWithJWT
{
    protected function withJWTToken(?User $user = null): self
    {
        $user = $user ?? $this->user;

        if (!$user) {
            throw new \RuntimeException('No user available for JWT token generation');
        }

        $token = JWTAuth::fromUser($user);
        $this->withHeader('Authorization', 'Bearer ' . $token);

        return $this;
    }

    protected function withInvalidJWTToken(): self
    {
        $this->withHeader('Authorization', 'Bearer invalid.token.here');
        return $this;
    }

    protected function withExpiredJWTToken(User $user): self
    {
        // Generate token with custom TTL of -1 minute (expired)
        $token = JWTAuth::customClaims(['exp' => time() - 60])->fromUser($user);
        $this->withHeader('Authorization', 'Bearer ' . $token);
        return $this;
    }

    protected function withoutJWTToken(): self
    {
        $this->withoutHeader('Authorization');
        return $this;
    }

    protected function refreshJWTToken(string $token): self
    {
        $this->withHeader('Authorization', 'Bearer ' . $token);
        return $this;
    }

    protected function getJWTToken(?User $user = null): string
    {
        $user = $user ?? $this->user;

        if (!$user) {
            throw new \RuntimeException('No user available for JWT token generation');
        }

        return JWTAuth::fromUser($user);
    }

    protected function assertJWTTokenIsValid(string $token): void
    {
        $this->assertNotNull(JWTAuth::setToken($token)->check());
    }

    protected function assertJWTTokenIsInvalid(string $token): void
    {
        try {
            JWTAuth::setToken($token)->check();
            $this->fail('Token is valid when it should be invalid');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }
    }

    protected function assertJWTTokenHasClaim(string $token, string $claim, $value): void
    {
        $payload = JWTAuth::setToken($token)->getPayload();
        $this->assertEquals($value, $payload->get($claim));
    }

    protected function assertResponseHasJWTToken(): void
    {
        $this->assertNotNull(
            $this->response->headers->get('Authorization'),
            'Response is missing JWT token in Authorization header'
        );
    }

    protected function assertResponseHasValidJWTToken(): void
    {
        $this->assertResponseHasJWTToken();
        
        $token = str_replace('Bearer ', '', $this->response->headers->get('Authorization'));
        $this->assertJWTTokenIsValid($token);
    }

    protected function assertResponseDoesNotHaveJWTToken(): void
    {
        $this->assertNull(
            $this->response->headers->get('Authorization'),
            'Response has JWT token when it should not'
        );
    }

    protected function getJWTTokenFromResponse(): ?string
    {
        $header = $this->response->headers->get('Authorization');
        return $header ? str_replace('Bearer ', '', $header) : null;
    }

    protected function assertJWTTokenBelongsToUser(string $token, User $user): void
    {
        $payload = JWTAuth::setToken($token)->getPayload();
        $this->assertEquals($user->id, $payload->get('sub'));
        $this->assertEquals($user->tenant_id, $payload->get('tenant_id'));
        $this->assertEquals($user->email, $payload->get('email'));
    }

    protected function assertJWTTokenHasCorrectTTL(string $token): void
    {
        $payload = JWTAuth::setToken($token)->getPayload();
        $ttl = config('jwt.ttl');
        
        $this->assertEquals(
            time() + ($ttl * 60),
            $payload->get('exp'),
            'JWT token TTL does not match configured value'
        );
    }
}
