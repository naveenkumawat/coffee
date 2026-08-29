<?php

namespace App\Parsers\Menu;

use App\Models\MenuCategory;
use App\Parsers\AbstractParser;
use App\Transfers\Menu\MenuCategoryTransferInterface;

class MenuCategoryParser extends AbstractParser implements MenuCategoryParserInterface
{
    public function getTransferFromModelEntity(MenuCategory $menuCategory): MenuCategoryTransferInterface
    {
        $transfer = $this->make(MenuCategoryTransferInterface::class);
        $transfer->setId($menuCategory->getKey());
        $transfer->setName($menuCategory->name);
        $transfer->setSlug($menuCategory->slug);
        $transfer->setDescription($menuCategory->description);
        $transfer->setSortOrder((int) $menuCategory->sort_order);
        $transfer->setIsActive((bool) $menuCategory->is_active);
        $transfer->setCreatedAt($menuCategory->created_at);
        $transfer->setUpdatedAt($menuCategory->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $menuCategoryData): MenuCategoryTransferInterface
    {
        $transfer = $this->make(MenuCategoryTransferInterface::class);
        $transfer->setName(trim((string) $menuCategoryData['name']));
        $transfer->setSlug(filled($menuCategoryData['slug'] ?? null) ? trim((string) $menuCategoryData['slug']) : null);
        $transfer->setDescription(filled($menuCategoryData['description'] ?? null) ? trim((string) $menuCategoryData['description']) : null);
        $transfer->setSortOrder((int) ($menuCategoryData['sort_order'] ?? 0));
        $transfer->setIsActive((bool) ($menuCategoryData['is_active'] ?? true));

        return $transfer;
    }
}
