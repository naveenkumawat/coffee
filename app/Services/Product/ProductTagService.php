<?php

namespace App\Services\Product;

use App\Enums\ProductTagStyle;
use App\Models\ProductTag;
use App\Repositories\Product\ProductTagRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductTagService implements ProductTagServiceInterface
{
    public function __construct(
        protected ProductTagRepositoryInterface $tags,
        protected ProductCatalogServiceInterface $catalog,
    ) {}

    public function store(array $data): ProductTag
    {
        $tag = DB::transaction(function () use ($data): ProductTag {
            return $this->tags->create($this->prepareAttributes($data));
        });

        $this->catalog->flushPublicCache();

        return $tag;
    }

    public function update(ProductTag $tag, array $data): ProductTag
    {
        $tag = DB::transaction(function () use ($tag, $data): ProductTag {
            return $this->tags->update($tag, $this->prepareAttributes($data, (int) $tag->getKey()));
        });

        $this->catalog->flushPublicCache();

        return $tag;
    }

    public function delete(ProductTag $tag): void
    {
        if ($tag->products()->exists()) {
            throw ValidationException::withMessages([
                'tag' => 'This tag cannot be archived while products still use it.',
            ]);
        }

        DB::transaction(function () use ($tag): void {
            $tag->forceFill(['is_active' => false])->save();
            $this->tags->delete($tag);
        });

        $this->catalog->flushPublicCache();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareAttributes(array $data, ?int $ignoreId = null): array
    {
        $name = trim((string) $data['name']);

        if ($ignoreId !== null) {
            $existingSlug = ProductTag::query()->whereKey($ignoreId)->value('slug');
            $slug = filled($existingSlug)
                ? (string) $existingSlug
                : $this->uniqueSlug(Str::slug($name) ?: 'tag', $ignoreId);
        } else {
            $slug = $this->uniqueSlug(Str::slug($name) ?: 'tag');
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'style_key' => ProductTagStyle::from((string) ($data['style_key'] ?? ProductTagStyle::Muted->value)),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    protected function uniqueSlug(string $baseSlug, ?int $ignoreId = null): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'tag';
        $suffix = 2;

        while ($this->tags->slugExists($slug, $ignoreId)) {
            $slug = sprintf('%s-%d', $baseSlug !== '' ? $baseSlug : 'tag', $suffix);
            $suffix++;
        }

        return $slug;
    }
}
