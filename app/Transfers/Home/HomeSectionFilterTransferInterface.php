<?php

namespace App\Transfers\Home;

interface HomeSectionFilterTransferInterface
{
    public function getSearch(): ?string;

    public function setSearch(?string $search): void;

    public function hasSearch(): bool;

    public function getStatus(): ?string;

    public function setStatus(?string $status): void;

    public function toArray(): array;
}
