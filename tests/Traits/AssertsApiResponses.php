<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Testing\TestResponse;

trait AssertsApiResponses
{
    protected function assertResourceCreated(TestResponse $response, array $resource): void
    {
        $response->assertStatus(201)
            ->assertJson(['data' => $resource]);
    }

    protected function assertResourceUpdated(TestResponse $response, array $resource): void
    {
        $response->assertStatus(200)
            ->assertJson(['data' => $resource]);
    }

    protected function assertResourceDeleted(TestResponse $response): void
    {
        $response->assertStatus(204);
    }

    protected function assertUnauthorized(TestResponse $response): void
    {
        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    protected function assertForbidden(TestResponse $response): void
    {
        $response->assertStatus(403)
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    protected function assertNotFound(TestResponse $response): void
    {
        $response->assertStatus(404)
            ->assertJson(['message' => 'Not Found.']);
    }

    protected function assertValidationError(TestResponse $response, string|array $fields): void
    {
        $response->assertStatus(422)
            ->assertJsonValidationErrors(is_array($fields) ? $fields : [$fields]);
    }

    protected function assertSuccessResponse(TestResponse $response, array $data = null): void
    {
        $response->assertStatus(200);

        if ($data !== null) {
            $response->assertJson(['data' => $data]);
        }
    }

    protected function assertPaginatedResponse(
        TestResponse $response,
        array $data,
        int $total,
        int $perPage = 15
    ): void {
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
            ])
            ->assertJson([
                'data' => $data,
                'meta' => [
                    'total' => $total,
                    'per_page' => $perPage,
                ],
            ]);
    }

    protected function createAndLoginUser(array $attributes = []): User
    {
        $user = $this->createUser($attributes);
        $this->loginAs($user);
        return $user;
    }

    protected function loginAs(User $user): void
    {
        $this->actingAs($user);
        $this->withJWTToken($user);
    }

    protected function assertJsonApiResponse(TestResponse $response, int $status = 200): void
    {
        $response->assertStatus($status)
            ->assertHeader('Content-Type', 'application/json');
    }

    protected function assertJsonApiValidationError(
        TestResponse $response,
        string $field,
        string $message
    ): void {
        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    $field => [$message],
                ],
            ]);
    }

    protected function assertJsonApiErrorResponse(
        TestResponse $response,
        string $message,
        int $status = 400
    ): void {
        $response->assertStatus($status)
            ->assertJson([
                'message' => $message,
            ]);
    }

    protected function assertJsonApiResource(
        TestResponse $response,
        array $resource,
        int $status = 200
    ): void {
        $response->assertStatus($status)
            ->assertJson([
                'data' => $resource,
            ]);
    }

    protected function assertJsonApiCollection(
        TestResponse $response,
        array $collection,
        int $status = 200
    ): void {
        $response->assertStatus($status)
            ->assertJson([
                'data' => $collection,
            ]);
    }

    protected function assertJsonApiRelationship(
        TestResponse $response,
        string $relationship,
        array $data,
        int $status = 200
    ): void {
        $response->assertStatus($status)
            ->assertJson([
                'data' => [
                    'relationships' => [
                        $relationship => [
                            'data' => $data,
                        ],
                    ],
                ],
            ]);
    }

    protected function assertJsonApiIncluded(
        TestResponse $response,
        array $included,
        int $status = 200
    ): void {
        $response->assertStatus($status)
            ->assertJson([
                'included' => $included,
            ]);
    }
}
