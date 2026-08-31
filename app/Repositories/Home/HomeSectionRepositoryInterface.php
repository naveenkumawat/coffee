<?php

namespace App\Repositories\Home;

use App\Models\HomeSection;
use App\Transfers\Home\HomeSectionFilterTransferInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface HomeSectionRepositoryInterface
{
    public function paginateForAdmin(HomeSectionFilterTransferInterface $filters, int $perPage = 20): LengthAwarePaginator;

    public function activeForHome(): Collection;

    public function create(array $attributes): HomeSection;

    public function update(HomeSection $homeSection, array $attributes): HomeSection;

    public function delete(HomeSection $homeSection): void;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;

    public function attachProduct(HomeSection $homeSection, int $productId, ?int $sortOrder = null): void;

    public function detachProduct(HomeSection $homeSection, int $productId): void;

    public function reorderProducts(HomeSection $homeSection, SupportCollection|array $orderedProductIds): void;

    public function moveSection(HomeSection $homeSection, string $direction): void;

    public function setActive(HomeSection $homeSection, bool $isActive): HomeSection;
}
