<?php

namespace App\Http\Controllers\Api\V1\Bootstrap;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Services\PublicCache\PublicCacheVersionServiceInterface;
use Illuminate\Http\JsonResponse;

class AppBootstrapController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected PublicCacheVersionServiceInterface $publicCache,
    ) {}

    public function show(): JsonResponse
    {
        $snapshot = $this->publicCache->snapshot();

        return $this->respondWithData([
            'cache_version' => $snapshot['cache_version'],
            'catalog_version' => $snapshot['catalog_version'],
            'content_version' => $snapshot['content_version'],
            'media_version' => $snapshot['media_version'],
        ], 'App bootstrap retrieved.');
    }
}
