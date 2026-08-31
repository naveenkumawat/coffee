<?php

namespace App\Transfers\Home;

interface HomeSectionTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getTitle(): ?string;

    public function setTitle(?string $title): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getSubtitle(): ?string;

    public function setSubtitle(?string $subtitle): void;

    public function getSortOrder(): int;

    public function setSortOrder(int $sortOrder): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function getMaxItems(): ?int;

    public function setMaxItems(?int $maxItems): void;

    public function toArray(): array;
}
