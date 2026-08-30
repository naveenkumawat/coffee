<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait InteractsWithApiResponses
{
    protected function respondWithData(mixed $data, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
        ], $status);
    }

    protected function respondWithResource(JsonResource $resource, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        return $this->respondWithData($resource->resolve(), $message, $status, $meta);
    }

    protected function respondWithCollection(iterable $collection, string $resourceClass, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        return $this->respondWithData($resourceClass::collection($collection)->resolve(), $message, $status, $meta);
    }

    protected function respondWithPaginator(LengthAwarePaginator $paginator, string $resourceClass, ?string $message = null, int $status = 200, array $meta = []): JsonResponse
    {
        return $this->respondWithData(
            $resourceClass::collection(collect($paginator->items()))->resolve(),
            $message,
            $status,
            array_merge($meta, [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
            ]),
        );
    }
}
