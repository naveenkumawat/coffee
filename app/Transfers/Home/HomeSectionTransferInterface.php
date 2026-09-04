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

    public function getPlacement(): string;

    public function setPlacement(string $placement): void;

    public function getSourceType(): string;

    public function setSourceType(string $sourceType): void;

    public function getSourceCategoryId(): ?int;

    public function setSourceCategoryId(?int $sourceCategoryId): void;

    public function getSourceTagId(): ?int;

    public function setSourceTagId(?int $sourceTagId): void;

    public function getRecommendationContext(): ?string;

    public function setRecommendationContext(?string $recommendationContext): void;

    public function getPriority(): int;

    public function setPriority(int $priority): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getTargetingRules(): ?array;

    /**
     * @param  array<string, mixed>|null  $targetingRules
     */
    public function setTargetingRules(?array $targetingRules): void;

    public function getStartsAt(): ?string;

    public function setStartsAt(?string $startsAt): void;

    public function getEndsAt(): ?string;

    public function setEndsAt(?string $endsAt): void;

    public function getDedupeProducts(): bool;

    public function setDedupeProducts(bool $dedupeProducts): void;

    public function getFallbackToCurated(): bool;

    public function setFallbackToCurated(bool $fallbackToCurated): void;

    public function toArray(): array;
}
