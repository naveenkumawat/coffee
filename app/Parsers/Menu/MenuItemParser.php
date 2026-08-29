<?php

namespace App\Parsers\Menu;

use App\Models\MenuItem;
use App\Parsers\AbstractParser;
use App\Transfers\Menu\MenuItemTransferInterface;

class MenuItemParser extends AbstractParser implements MenuItemParserInterface
{
    public function getTransferFromModelEntity(MenuItem $menuItem): MenuItemTransferInterface
    {
        $transfer = $this->make(MenuItemTransferInterface::class);
        $transfer->setId($menuItem->getKey());
        $transfer->setMenuCategoryId((int) $menuItem->menu_category_id);
        $transfer->setName($menuItem->name);
        $transfer->setSlug($menuItem->slug);
        $transfer->setDescription($menuItem->description);
        $transfer->setPrice(number_format((float) $menuItem->price, 2, '.', ''));
        $transfer->setIsAvailable((bool) $menuItem->is_available);
        $transfer->setIsFeatured((bool) $menuItem->is_featured);
        $transfer->setSortOrder((int) $menuItem->sort_order);
        $transfer->setCreatedAt($menuItem->created_at);
        $transfer->setUpdatedAt($menuItem->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $menuItemData): MenuItemTransferInterface
    {
        $transfer = $this->make(MenuItemTransferInterface::class);
        $transfer->setMenuCategoryId((int) $menuItemData['menu_category_id']);
        $transfer->setName(trim((string) $menuItemData['name']));
        $transfer->setSlug(filled($menuItemData['slug'] ?? null) ? trim((string) $menuItemData['slug']) : null);
        $transfer->setDescription(filled($menuItemData['description'] ?? null) ? trim((string) $menuItemData['description']) : null);
        $transfer->setPrice(number_format((float) $menuItemData['price'], 2, '.', ''));
        $transfer->setIsAvailable((bool) ($menuItemData['is_available'] ?? true));
        $transfer->setIsFeatured((bool) ($menuItemData['is_featured'] ?? false));
        $transfer->setSortOrder((int) ($menuItemData['sort_order'] ?? 0));

        return $transfer;
    }
}
