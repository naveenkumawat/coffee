<?php

namespace App\Transfers\User;

interface UserTransferInterface
{
    public function getId(): int|string|null;

    public function setId(int|string|null $id): void;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getEmail(): ?string;

    public function setEmail(?string $email): void;

    public function getPhone(): ?string;

    public function setPhone(?string $phone): void;

    public function getRole(): string;

    public function setRole(string $role): void;

    public function isActive(): bool;

    public function setIsActive(bool $isActive): void;

    public function getPassword(): ?string;

    public function setPassword(?string $password): void;

    public function toArray(): array;
}
