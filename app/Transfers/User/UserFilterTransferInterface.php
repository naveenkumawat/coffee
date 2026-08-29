<?php

namespace App\Transfers\User;

interface UserFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getRole(): ?string;

    public function setRole(?string $role): void;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function toArray(): array;
}
