<?php

namespace App\Transfers\User;

use App\Transfers\AbstractTransfer;

class UserTransfer extends AbstractTransfer implements UserTransferInterface
{
    protected ?string $name = null;

    protected ?string $email = null;

    protected ?string $phone = null;

    protected string $role = 'customer';

    protected bool $isActive = true;

    protected bool $cashTakeawayAllowed = false;

    protected ?string $password = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function isCashTakeawayAllowed(): bool
    {
        return $this->cashTakeawayAllowed;
    }

    public function setCashTakeawayAllowed(bool $cashTakeawayAllowed): void
    {
        $this->cashTakeawayAllowed = $cashTakeawayAllowed;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'is_active' => $this->isActive,
            'cash_takeaway_allowed' => $this->role === 'customer' ? $this->cashTakeawayAllowed : false,
            'password' => $this->password,
        ], fn ($value, string $key): bool => in_array($key, ['is_active', 'cash_takeaway_allowed'], true) || $value !== null, ARRAY_FILTER_USE_BOTH);
    }
}
