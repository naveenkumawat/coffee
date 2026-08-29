<?php

namespace App\Transfers\User;

class UserFilterTransfer implements UserFilterTransferInterface
{
    protected ?string $search = null;

    protected ?string $role = null;

    protected ?string $status = null;

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): void
    {
        $this->search = $search;
    }

    public function hasSearch(): bool
    {
        return filled($this->search);
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): void
    {
        $this->role = $role;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'role' => $this->role,
            'status' => $this->status,
        ]);
    }
}
