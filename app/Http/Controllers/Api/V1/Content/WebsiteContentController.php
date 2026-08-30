<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\JsonResponse;

class WebsiteContentController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
    ) {}

    public function show(): JsonResponse
    {
        return $this->respondWithData(
            $this->websiteSettings->customerContent(),
            'Website content retrieved.',
        );
    }
}
