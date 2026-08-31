<?php

namespace App\Http\Controllers\Api\V1\CafeTable;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Services\CafeTable\CafeTableServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\JsonResponse;

class CafeTableController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected CafeTableServiceInterface $tables,
        protected WebsiteSettingServiceInterface $websiteSettings,
    ) {}

    public function index(): JsonResponse
    {
        if (! $this->websiteSettings->dineInEnabled()) {
            return $this->respondWithData([], 'Dine-in is not available.');
        }

        return $this->respondWithData(
            $this->tables->publicActiveTables(),
            'Café tables retrieved.',
        );
    }
}
