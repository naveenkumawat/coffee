<?php

namespace App\Http\Controllers\Api\V1\Home;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\HomeSectionResource;
use App\Services\Home\HomeSectionServiceInterface;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected HomeSectionServiceInterface $homeSections,
    ) {}

    public function show(): JsonResponse
    {
        $sections = $this->homeSections->activeSectionsForCustomer();

        return $this->respondWithData([
            'sections' => HomeSectionResource::collection($sections)->resolve(),
        ], 'Homepage sections retrieved.');
    }
}
