<?php

namespace App\Parsers\User;

use App\Models\User;
use App\Transfers\User\UserFilterTransferInterface;
use App\Transfers\User\UserTransferInterface;

interface UserParserInterface
{
    public function getTransferFromModelEntity(User $user): UserTransferInterface;

    public function getTransferFromArrayData(array $userData): UserTransferInterface;

    public function getFilterTransferFromArrayData(array $filterData): UserFilterTransferInterface;
}
