<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\MakesApiCalls;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase, MakesApiCalls;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
    }

    protected function assertRequiresAuthentication(
        string $method,
        string $uri,
        array $data = []
    ): void {
        $response = $this->makeApiCall($method, $uri, $data);
        $this->assertUnauthorized($response);
    }

    protected function assertRequiresTenant(
        string $method,
        string $uri,
        array $data = []
    ): void {
        $user = $this->withoutTenant(function () {
            return User::factory()->create(['tenant_id' => null]);
        });
        
        $response = $this->makeAuthenticatedApiCall($method, $uri, $data, $user);
        $this->assertForbidden($response);
    }

    protected function assertRequiresPermission(
        string $method,
        string $uri,
        string $permission,
        array $data = []
    ): void {
        $user = $this->createTenantUser();
        
        $response = $this->makeAuthenticatedApiCall($method, $uri, $data, $user);
        $this->assertForbidden($response);
    }

    protected function assertRequiresVerification(
        string $method,
        string $uri,
        array $data = []
    ): void {
        $user = User::factory()->unverified()->create([
            'tenant_id' => $this->tenant()->id,
        ]);
        
        $response = $this->makeAuthenticatedApiCall($method, $uri, $data, $user);
        $this->assertForbidden($response);
    }

    protected function assertRequiresActiveTenant(
        string $method,
        string $uri,
        array $data = []
    ): void {
        $this->withTenant(function () use ($method, $uri, $data) {
            $inactiveTenant = $this->createTenant(['is_active' => false]);
            $user = $this->createUserForTenant($inactiveTenant);
            
            $response = $this->makeAuthenticatedApiCall($method, $uri, $data, $user);
            $this->assertForbidden($response);
        });
    }

    protected function assertApiValidationErrors(
        TestResponse $response,
        array $errors
    ): void {
        $response->assertStatus(422)
            ->assertJsonValidationErrors($errors);
    }

    protected function assertApiResponse(
        TestResponse $response,
        int $status,
        array $data = null
    ): void {
        $response->assertStatus($status);

        if ($data !== null) {
            $response->assertJson(['data' => $data]);
        }
    }

    protected function assertApiSuccess(TestResponse $response, array $data = null): void
    {
        $this->assertApiResponse($response, 200, $data);
    }

    protected function assertApiCreated(TestResponse $response, array $data = null): void
    {
        $this->assertApiResponse($response, 201, $data);
    }

    protected function assertApiNoContent(TestResponse $response): void
    {
        $response->assertStatus(204);
    }

    protected function assertApiNotFound(TestResponse $response): void
    {
        $response->assertStatus(404)
            ->assertJson(['message' => 'Not Found.']);
    }

    protected function assertApiUnauthorized(TestResponse $response): void
    {
        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    protected function assertApiForbidden(TestResponse $response): void
    {
        $response->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    protected function assertApiValidationFailed(TestResponse $response): void
    {
        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    protected function assertApiError(TestResponse $response, string $message, int $status = 400): void
    {
        $response->assertStatus($status)
            ->assertJson(['message' => $message]);
    }
}
