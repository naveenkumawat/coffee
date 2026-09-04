<?php

namespace App\Transfers\Home;

use App\Transfers\AbstractTransfer;

class HomeSectionTransfer extends AbstractTransfer implements HomeSectionTransferInterface
{
    protected ?string $name = null;

    protected ?string $title = null;

    protected ?string $slug = null;

    protected ?string $subtitle = null;

    protected int $sortOrder = 0;

    protected bool $isActive = true;

    protected ?int $maxItems = null;

    protected string $placement = 'home';

    protected string $sourceType = 'curated';

    protected ?int $sourceCategoryId = null;

    protected ?int $sourceTagId = null;

    protected ?string $recommendationContext = null;

    protected int $priority = 0;

    /** @var array<string, mixed>|null */
    protected ?array $targetingRules = null;

    protected ?string $startsAt = null;

    protected ?string $endsAt = null;

    protected bool $dedupeProducts = true;

    protected bool $fallbackToCurated = true;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getSubtitle(): ?string
    {
        return $this->subtitle;
    }

    public function setSubtitle(?string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    public function setMaxItems(?int $maxItems): void
    {
        $this->maxItems = $maxItems;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    public function setPlacement(string $placement): void
    {
        $this->placement = $placement;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): void
    {
        $this->sourceType = $sourceType;
    }

    public function getSourceCategoryId(): ?int
    {
        return $this->sourceCategoryId;
    }

    public function setSourceCategoryId(?int $sourceCategoryId): void
    {
        $this->sourceCategoryId = $sourceCategoryId;
    }

    public function getSourceTagId(): ?int
    {
        return $this->sourceTagId;
    }

    public function setSourceTagId(?int $sourceTagId): void
    {
        $this->sourceTagId = $sourceTagId;
    }

    public function getRecommendationContext(): ?string
    {
        return $this->recommendationContext;
    }

    public function setRecommendationContext(?string $recommendationContext): void
    {
        $this->recommendationContext = $recommendationContext;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTargetingRules(): ?array
    {
        return $this->targetingRules;
    }

    /**
     * @param  array<string, mixed>|null  $targetingRules
     */
    public function setTargetingRules(?array $targetingRules): void
    {
        $this->targetingRules = $targetingRules;
    }

    public function getStartsAt(): ?string
    {
        return $this->startsAt;
    }

    public function setStartsAt(?string $startsAt): void
    {
        $this->startsAt = $startsAt;
    }

    public function getEndsAt(): ?string
    {
        return $this->endsAt;
    }

    public function setEndsAt(?string $endsAt): void
    {
        $this->endsAt = $endsAt;
    }

    public function getDedupeProducts(): bool
    {
        return $this->dedupeProducts;
    }

    public function setDedupeProducts(bool $dedupeProducts): void
    {
        $this->dedupeProducts = $dedupeProducts;
    }

    public function getFallbackToCurated(): bool
    {
        return $this->fallbackToCurated;
    }

    public function setFallbackToCurated(bool $fallbackToCurated): void
    {
        $this->fallbackToCurated = $fallbackToCurated;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'max_items' => $this->maxItems,
            'placement' => $this->placement,
            'source_type' => $this->sourceType,
            'source_category_id' => $this->sourceCategoryId,
            'source_tag_id' => $this->sourceTagId,
            'recommendation_context' => $this->recommendationContext,
            'priority' => $this->priority,
            'targeting_rules' => $this->targetingRules ?? ['all' => [], 'any' => [], 'exclude' => []],
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
            'dedupe_products' => $this->dedupeProducts,
            'fallback_to_curated' => $this->fallbackToCurated,
        ];
    }
}
