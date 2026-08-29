<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Consistent JSON response helpers for API controllers.
 *
 * Every success envelope:  `{ data: ..., message: "..." }`
 * Every error envelope:    `{ message: "...", errors: { field: [...] } }`
 * Paginated lists return Laravel's default `{ data: [...], meta: {...}, links: {...} }`.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        if ($data instanceof ResourceCollection) {
            return $data->response()->setStatusCode($status);
        }

        // A bare paginator has no `response()`: the call fell through `AbstractPaginator::__call()`
        // to the underlying Collection and died as `Collection::response does not exist`, so every
        // controller handing a raw paginator to `success()` returned a 500. Encoding it directly
        // gives the `{ data: [...], current_page, total, ... }` payload callers already read.
        if ($data instanceof LengthAwarePaginator) {
            return response()->json($data->toArray(), $status);
        }

        $payload = ['message' => $message];

        if ($data instanceof JsonResource) {
            $payload['data'] = $data->resolve();
        } elseif ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $status);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function noContent(string $message = 'Deleted'): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    protected function validationError(ValidationException $e): JsonResponse
    {
        return $this->error(
            $e->getMessage(),
            422,
            $e->errors(),
        );
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }
}
