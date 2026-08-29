<?php

namespace App\Transfers\Menu;

interface MenuCategoryTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getDescription(): ?string;

    public function setDescription(?string $description): void;

    public function getSortOrder(): int;

    public function setSortOrder(int $sortOrder): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function toArray(): array;
}
