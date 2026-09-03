<?php

namespace App\Services\Campaign;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface CampaignCatalogServiceInterface
{
    public function paginateForAdmin(?string $status = null): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, ?User $actor = null, ?UploadedFile $image = null): Campaign;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Campaign $campaign, array $data, ?User $actor = null, ?UploadedFile $image = null, bool $removeImage = false): Campaign;

    public function delete(Campaign $campaign): void;

    public function setStatus(Campaign $campaign, CampaignStatus $status, ?User $actor = null): Campaign;
}
