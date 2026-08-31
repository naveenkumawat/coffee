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
