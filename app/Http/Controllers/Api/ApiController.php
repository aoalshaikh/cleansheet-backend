<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

abstract class ApiController extends Controller
{
    /**
     * Return a success response.
     */
    protected function success(mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        if ($data instanceof JsonResource) {
            return $data->response()->setStatusCode($status);
        }

        if ($data instanceof ResourceCollection) {
            return $data->response()->setStatusCode($status);
        }

        if ($data instanceof LengthAwarePaginator) {
            return response()->json([
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'from' => $data->firstItem(),
                    'last_page' => $data->lastPage(),
                    'path' => $data->path(),
                    'per_page' => $data->perPage(),
                    'to' => $data->lastItem(),
                    'total' => $data->total(),
                ],
                'links' => [
                    'first' => $data->url(1),
                    'last' => $data->url($data->lastPage()),
                    'prev' => $data->previousPageUrl(),
                    'next' => $data->nextPageUrl(),
                ],
            ], $status);
        }

        return response()->json([
            'data' => $data,
        ], $status);
    }

    /**
     * Return a created response.
     */
    protected function created(mixed $data = null): JsonResponse
    {
        return $this->success($data, Response::HTTP_CREATED);
    }

    /**
     * Return a no content response.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return an error response.
     */
    protected function error(string $message, int $status = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], $status);
    }

    /**
     * Return a not found response.
     */
    protected function notFound(string $message = 'Not Found.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorized(string $message = 'Unauthorized.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden response.
     */
    protected function forbidden(string $message = 'This action is unauthorized.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a validation error response.
     */
    protected function validationError(array $errors, string $message = 'The given data was invalid.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Return a too many requests response.
     */
    protected function tooManyRequests(string $message = 'Too Many Requests.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * Return a server error response.
     */
    protected function serverError(string $message = 'Server Error.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Return a service unavailable response.
     */
    protected function serviceUnavailable(string $message = 'Service Unavailable.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Return a conflict response.
     */
    protected function conflict(string $message = 'Conflict.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_CONFLICT);
    }

    /**
     * Return a gone response.
     */
    protected function gone(string $message = 'Gone.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_GONE);
    }

    /**
     * Return a precondition failed response.
     */
    protected function preconditionFailed(string $message = 'Precondition Failed.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_PRECONDITION_FAILED);
    }

    /**
     * Return an unprocessable entity response.
     */
    protected function unprocessableEntity(string $message = 'Unprocessable Entity.'): JsonResponse
    {
        return $this->error($message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
