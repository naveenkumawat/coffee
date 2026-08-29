<?php

namespace App\Transfers;

abstract class AbstractTransfer
{
    protected int|string|null $id = null;

    protected mixed $createdAt = null;

    protected mixed $updatedAt = null;

    public function getId(): int|string|null
    {
        return $this->id;
    }

    public function setId(int|string|null $id): void
    {
        $this->id = $id;
    }

    public function getCreatedAt(): mixed
    {
        return $this->createdAt;
    }

    public function setCreatedAt(mixed $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): mixed
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(mixed $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
