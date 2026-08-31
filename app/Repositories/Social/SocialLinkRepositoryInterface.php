<?php

namespace App\Repositories\Social;

use App\Models\SocialLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface SocialLinkRepositoryInterface
{
    public function paginateForAdmin(int $perPage = 20): LengthAwarePaginator;

    /**
     * @return Collection<int, SocialLink>
     */
    public function activeOrdered(): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): SocialLink;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(SocialLink $link, array $attributes): SocialLink;

    public function delete(SocialLink $link): void;

    public function platformKeyExists(string $platformKey, ?int $ignoreId = null): bool;

    public function move(SocialLink $link, string $direction): void;

    public function setActive(SocialLink $link, bool $isActive): SocialLink;
}
