<?php

namespace App\Parsers\User;

use App\Models\User;
use App\Parsers\AbstractParser;
use App\Support\PhoneNumber;
use App\Transfers\User\UserFilterTransferInterface;
use App\Transfers\User\UserTransferInterface;

class UserParser extends AbstractParser implements UserParserInterface
{
    public function getTransferFromModelEntity(User $user): UserTransferInterface
    {
        $transfer = $this->make(UserTransferInterface::class);
        $transfer->setId($user->getKey());
        $transfer->setName($user->name);
        $transfer->setEmail($user->email);
        $transfer->setPhone($user->phone);
        $transfer->setRole($user->role->value);
        $transfer->setIsActive((bool) $user->is_active);
        $transfer->setCashTakeawayAllowed((bool) $user->cash_takeaway_allowed);
        $transfer->setCreatedAt($user->created_at);
        $transfer->setUpdatedAt($user->updated_at);

        return $transfer;
    }

    public function getTransferFromArrayData(array $userData): UserTransferInterface
    {
        $transfer = $this->make(UserTransferInterface::class);
        $transfer->setName(trim((string) $userData['name']));
        $transfer->setEmail(strtolower(trim((string) $userData['email'])));
        $transfer->setPhone(
            filled($userData['phone'] ?? null)
                ? PhoneNumber::normalize((string) $userData['phone'])
                : null
        );
        $transfer->setRole((string) $userData['role']);
        $transfer->setIsActive((bool) ($userData['is_active'] ?? true));
        $transfer->setCashTakeawayAllowed((bool) ($userData['cash_takeaway_allowed'] ?? false));
        $transfer->setPassword(filled($userData['password'] ?? null) ? (string) $userData['password'] : null);

        return $transfer;
    }

    public function getFilterTransferFromArrayData(array $filterData): UserFilterTransferInterface
    {
        $transfer = $this->make(UserFilterTransferInterface::class);
        $transfer->setSearch(filled($filterData['search'] ?? null) ? trim((string) $filterData['search']) : null);
        $transfer->setRole(filled($filterData['role'] ?? null) ? (string) $filterData['role'] : null);
        $transfer->setStatus(filled($filterData['status'] ?? null) ? (string) $filterData['status'] : null);

        return $transfer;
    }
}
