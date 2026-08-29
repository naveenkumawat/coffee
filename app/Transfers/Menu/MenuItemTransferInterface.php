<?php

namespace App\Transfers\Menu;

interface MenuItemTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getMenuCategoryId(): int;

    public function setMenuCategoryId(int $menuCategoryId): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getPrice(): string;

    public function setPrice(string $price): void;

    public function isAvailable(): bool;

    public function setIsAvailable(bool $isAvailable): void;

    public function isFeatured(): bool;

    public function setIsFeatured(bool $isFeatured): void;

    public function getSortOrder(): int;

    public function setSortOrder(int $sortOrder): void;

    public function toArray(): array;
}
