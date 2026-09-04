<?php

namespace App\Parsers\Home;

use App\Models\HomeSection;
use App\Parsers\AbstractParser;
use App\Transfers\Home\HomeSectionFilterTransferInterface;
use App\Transfers\Home\HomeSectionTransferInterface;

class HomeSectionParser extends AbstractParser implements HomeSectionParserInterface
{
    public function getTransferFromModelEntity(HomeSection $homeSection): HomeSectionTransferInterface
    {
        $transfer = $this->make(HomeSectionTransferInterface::class);
        $transfer->setId($homeSection->getKey());
        $transfer->setName($homeSection->name);
        $transfer->setTitle($homeSection->title);
        $transfer->setSlug($homeSection->slug);
        $transfer->setSubtitle($homeSection->subtitle);
        $transfer->setSortOrder((int) $homeSection->sort_order);
        $transfer->setIsActive((bool) $homeSection->is_active);
        $transfer->setMaxItems($homeSection->max_items !== null ? (int) $homeSection->max_items : null);
        $transfer->setPlacement($homeSection->placement?->value ?? (string) $homeSection->placement);
        $transfer->setSourceType($homeSection->source_type?->value ?? (string) $homeSection->source_type);
        $transfer->setSourceCategoryId($homeSection->source_category_id !== null ? (int) $homeSection->source_category_id : null);
        $transfer->setSourceTagId($homeSection->source_tag_id !== null ? (int) $homeSection->source_tag_id : null);
        $transfer->setRecommendationContext($homeSection->recommendation_context);
        $transfer->setPriority((int) ($homeSection->priority ?? 0));
        $transfer->setTargetingRules(is_array($homeSection->targeting_rules) ? $homeSection->targeting_rules : null);
        $transfer->setStartsAt($homeSection->starts_at?->toDateTimeString());
        $transfer->setEndsAt($homeSection->ends_at?->toDateTimeString());
        $transfer->setDedupeProducts((bool) ($homeSection->dedupe_products ?? true));
        $transfer->setFallbackToCurated((bool) ($homeSection->fallback_to_curated ?? true));
        $transfer->setCreatedAt($homeSection->created_at);
        $transfer->setUpdatedAt($homeSection->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $homeSectionData): HomeSectionTransferInterface
    {
        $transfer = $this->make(HomeSectionTransferInterface::class);
        $title = trim((string) $homeSectionData['title']);
        $name = filled($homeSectionData['name'] ?? null)
            ? trim((string) $homeSectionData['name'])
            : $title;

        $transfer->setName($name);
        $transfer->setTitle($title);
        $transfer->setSlug(filled($homeSectionData['slug'] ?? null) ? trim((string) $homeSectionData['slug']) : null);
        $transfer->setSubtitle(filled($homeSectionData['subtitle'] ?? null) ? trim((string) $homeSectionData['subtitle']) : null);
        $transfer->setSortOrder((int) ($homeSectionData['sort_order'] ?? 0));
        $transfer->setIsActive((bool) ($homeSectionData['is_active'] ?? true));
        $transfer->setMaxItems(
            array_key_exists('max_items', $homeSectionData) && $homeSectionData['max_items'] !== null && $homeSectionData['max_items'] !== ''
                ? (int) $homeSectionData['max_items']
                : null,
        );
        $transfer->setPlacement((string) ($homeSectionData['placement'] ?? 'home'));
        $transfer->setSourceType((string) ($homeSectionData['source_type'] ?? 'curated'));
        $transfer->setSourceCategoryId(
            filled($homeSectionData['source_category_id'] ?? null) ? (int) $homeSectionData['source_category_id'] : null,
        );
        $transfer->setSourceTagId(
            filled($homeSectionData['source_tag_id'] ?? null) ? (int) $homeSectionData['source_tag_id'] : null,
        );
        $transfer->setRecommendationContext(
            filled($homeSectionData['recommendation_context'] ?? null)
                ? trim((string) $homeSectionData['recommendation_context'])
                : null,
        );
        $transfer->setPriority((int) ($homeSectionData['priority'] ?? 0));
        $transfer->setTargetingRules(
            is_array($homeSectionData['targeting_rules'] ?? null)
                ? $homeSectionData['targeting_rules']
                : ['all' => [], 'any' => [], 'exclude' => []],
        );
        $transfer->setStartsAt(filled($homeSectionData['starts_at'] ?? null) ? (string) $homeSectionData['starts_at'] : null);
        $transfer->setEndsAt(filled($homeSectionData['ends_at'] ?? null) ? (string) $homeSectionData['ends_at'] : null);
        $transfer->setDedupeProducts((bool) ($homeSectionData['dedupe_products'] ?? true));
        $transfer->setFallbackToCurated((bool) ($homeSectionData['fallback_to_curated'] ?? true));

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): HomeSectionFilterTransferInterface
    {
        $transfer = $this->make(HomeSectionFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);

        return $transfer;
    }
}
