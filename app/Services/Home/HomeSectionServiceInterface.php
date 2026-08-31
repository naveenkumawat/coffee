<?php

namespace App\Services\Home;

use App\Models\HomeSection;
use App\Models\Product;
use App\Transfers\Home\HomeSectionTransferInterface;
use Illuminate\Database\Eloquent\Collection;

interface HomeSectionServiceInterface
{
    public function store(HomeSectionTransferInterface $data): HomeSection;

    public function update(HomeSection $homeSection, HomeSectionTransferInterface $data): HomeSection;

    public function delete(HomeSection $homeSection): void;

    public function setActive(HomeSection $homeSection, bool $isActive): HomeSection;

    public function move(HomeSection $homeSection, string $direction): void;

    public function attachProduct(HomeSection $homeSection, Product $product): void;

    public function detachProduct(HomeSection $homeSection, Product $product): void;

    public function moveProduct(HomeSection $homeSection, Product $product, string $direction): void;

    /**
     * @return Collection<int, HomeSection>
     */
    public function activeSectionsForCustomer(): Collection;
}
