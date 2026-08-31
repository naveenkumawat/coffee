<?php

namespace App\Repositories\Social;

use App\Models\SocialLink;
use App\Repositories\AbstractRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SocialLinkRepository extends AbstractRepository implements SocialLinkRepositoryInterface
{
    public function __construct(
        protected SocialLink $model,
    ) {}

    public function paginateForAdmin(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeOrdered(): Collection
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function create(array $attributes): SocialLink
    {
        /** @var SocialLink $link */
        $link = $this->persist($this->model->newInstance(), $attributes);

        return $link;
    }

    public function update(SocialLink $link, array $attributes): SocialLink
    {
        /** @var SocialLink $link */
        $link = $this->persist($link, $attributes);

        return $link;
    }

    public function delete(SocialLink $link): void
    {
        $this->remove($link);
    }

    public function platformKeyExists(string $platformKey, ?int $ignoreId = null): bool
    {
        return $this->model->newQuery()
            ->where('platform_key', $platformKey)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }

    public function move(SocialLink $link, string $direction): void
    {
        $siblings = $this->model->newQuery()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = $siblings->search(
            fn (SocialLink $item): bool => (int) $item->getKey() === (int) $link->getKey(),
        );

        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapWith < 0 || $swapWith >= $siblings->count()) {
            return;
        }

        /** @var SocialLink $current */
        $current = $siblings[$index];
        /** @var SocialLink $neighbor */
        $neighbor = $siblings[$swapWith];

        $currentOrder = (int) $current->sort_order;
        $neighborOrder = (int) $neighbor->sort_order;

        if ($currentOrder === $neighborOrder) {
            $currentOrder = ($index + 1) * 10;
            $neighborOrder = ($swapWith + 1) * 10;
        }

        $current->forceFill(['sort_order' => $neighborOrder])->save();
        $neighbor->forceFill(['sort_order' => $currentOrder])->save();
    }

    public function setActive(SocialLink $link, bool $isActive): SocialLink
    {
        $link->forceFill(['is_active' => $isActive])->save();

        return $link->refresh();
    }
}
