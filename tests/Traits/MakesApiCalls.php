<?php

namespace Tests\Traits;

use App\Models\User;
use Illuminate\Testing\TestResponse;

trait MakesApiCalls
{
    protected string $apiPrefix = 'api/v1';

    protected function makeApiCall(
        string $method,
        string $uri,
        array $data = [],
        array $headers = []
    ): TestResponse {
        $method = strtolower($method);
        $uri = $this->prefixUri($uri);

        return match($method) {
            'get' => $this->json('GET', $uri, [], $headers),
            'post' => $this->json('POST', $uri, $data, $headers),
            'put' => $this->json('PUT', $uri, $data, $headers),
            'patch' => $this->json('PATCH', $uri, $data, $headers),
            'delete' => $this->json('DELETE', $uri, $data, $headers),
            default => throw new \InvalidArgumentException("Invalid HTTP method: {$method}")
        };
    }

    protected function makeAuthenticatedApiCall(
        string $method,
        string $uri,
        array $data = [],
        ?User $user = null
    ): TestResponse {
        $user = $user ?? $this->user();

        if (!$user) {
            throw new \RuntimeException('No authenticated user available');
        }

        return $this->withJWTToken($user)
            ->makeApiCall($method, $uri, $data);
    }

    protected function prefixUri(string $uri): string
    {
        return "/{$this->apiPrefix}/" . ltrim($uri, '/');
    }

    protected function getJson($uri, array $headers = []): TestResponse
    {
        return $this->makeApiCall('get', $uri, [], $headers);
    }

    protected function postJson($uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->makeApiCall('post', $uri, $data, $headers);
    }

    protected function putJson($uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->makeApiCall('put', $uri, $data, $headers);
    }

    protected function patchJson($uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->makeApiCall('patch', $uri, $data, $headers);
    }

    protected function deleteJson($uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->makeApiCall('delete', $uri, $data, $headers);
    }

    protected function getAuthenticatedJson($uri, ?User $user = null, array $headers = []): TestResponse
    {
        return $this->makeAuthenticatedApiCall('get', $uri, [], $user);
    }

    protected function postAuthenticatedJson($uri, array $data = [], ?User $user = null, array $headers = []): TestResponse
    {
        return $this->makeAuthenticatedApiCall('post', $uri, $data, $user);
    }

    protected function putAuthenticatedJson($uri, array $data = [], ?User $user = null, array $headers = []): TestResponse
    {
        return $this->makeAuthenticatedApiCall('put', $uri, $data, $user);
    }

    protected function patchAuthenticatedJson($uri, array $data = [], ?User $user = null, array $headers = []): TestResponse
    {
        return $this->makeAuthenticatedApiCall('patch', $uri, $data, $user);
    }

    protected function deleteAuthenticatedJson($uri, array $data = [], ?User $user = null, array $headers = []): TestResponse
    {
        return $this->makeAuthenticatedApiCall('delete', $uri, $data, $user);
    }
}
