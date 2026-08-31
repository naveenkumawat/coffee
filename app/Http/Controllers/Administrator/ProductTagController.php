<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\ProductTagStyle;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductTag\ProductTagStoreRequest;
use App\Http\Requests\ProductTag\ProductTagUpdateRequest;
use App\Models\ProductTag;
use App\Repositories\Product\ProductTagRepositoryInterface;
use App\Services\Product\ProductTagServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductTagController extends Controller
{
    public function __construct(
        protected ProductTagRepositoryInterface $tags,
        protected ProductTagServiceInterface $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', ProductTag::class);

        return view('administrator.products.tags.index', [
            'tags' => $this->tags->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ProductTag::class);

        return view('administrator.products.tags.create', [
            'tag' => new ProductTag([
                'is_active' => true,
                'sort_order' => 100,
                'style_key' => ProductTagStyle::Muted,
            ]),
            'styleOptions' => ProductTagStyle::options(),
        ]);
    }

    public function store(ProductTagStoreRequest $request): RedirectResponse
    {
        $tag = $this->service->store($request->validated());

        return redirect()
            ->route('administrator.products.tags.edit', $tag)
            ->with('status', 'Product tag created successfully.');
    }

    public function edit(ProductTag $productTag): View
    {
        $this->authorize('update', $productTag);

        return view('administrator.products.tags.edit', [
            'tag' => $productTag,
            'styleOptions' => ProductTagStyle::options(),
        ]);
    }

    public function update(ProductTagUpdateRequest $request, ProductTag $productTag): RedirectResponse
    {
        $this->authorize('update', $productTag);

        $this->service->update($productTag, $request->validated());

        return redirect()
            ->route('administrator.products.tags.edit', $productTag)
            ->with('status', 'Product tag updated successfully.');
    }

    public function destroy(ProductTag $productTag): RedirectResponse
    {
        $this->authorize('delete', $productTag);

        $this->service->delete($productTag);

        return redirect()
            ->route('administrator.products.tags.index')
            ->with('status', 'Product tag archived successfully.');
    }
}
