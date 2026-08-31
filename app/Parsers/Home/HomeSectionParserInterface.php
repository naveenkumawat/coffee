<?php

namespace App\Parsers\Home;

use App\Models\HomeSection;
use App\Transfers\Home\HomeSectionFilterTransferInterface;
use App\Transfers\Home\HomeSectionTransferInterface;

interface HomeSectionParserInterface
{
    public function getTransferFromModelEntity(HomeSection $homeSection): HomeSectionTransferInterface;

    public function getTransferFromArrayData(array $homeSectionData): HomeSectionTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): HomeSectionFilterTransferInterface;
}
