<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\MenuCategory\MenuCategoryCreateRequest;
use App\Http\Requests\MenuCategory\MenuCategoryUpdateRequest;
use App\Models\MenuCategory;
use App\Parsers\Menu\MenuCategoryParserInterface;
use App\Repositories\Menu\MenuCategoryRepositoryInterface;
use App\Services\Menu\MenuCategoryServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MenuCategoryController extends Controller
{
    public function __construct(
        protected MenuCategoryParserInterface $parser,
        protected MenuCategoryRepositoryInterface $categories,
        protected MenuCategoryServiceInterface $service,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MenuCategory::class);

        return view('administrator.menu.categories.index', [
            'categories' => $this->categories->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', MenuCategory::class);

        return view('administrator.menu.categories.create', [
            'category' => new MenuCategory(['is_active' => true, 'sort_order' => 10]),
        ]);
    }

    public function store(MenuCategoryCreateRequest $request): RedirectResponse
    {
        $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.menu.categories.index')
            ->with('status', 'Menu category created successfully.');
    }

    public function show(MenuCategory $menuCategory): RedirectResponse
    {
        return redirect()->route('administrator.menu.categories.edit', $menuCategory);
    }

    public function edit(MenuCategory $menuCategory): View
    {
        $this->authorize('update', $menuCategory);

        return view('administrator.menu.categories.edit', [
            'category' => $menuCategory,
        ]);
    }

    public function update(MenuCategoryUpdateRequest $request, MenuCategory $menuCategory): RedirectResponse
    {
        $this->authorize('update', $menuCategory);
        $this->service->update($menuCategory, $this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.menu.categories.edit', $menuCategory)
            ->with('status', 'Menu category updated successfully.');
    }

    public function destroy(MenuCategory $menuCategory): RedirectResponse
    {
        $this->authorize('delete', $menuCategory);
        $this->service->delete($menuCategory);

        return redirect()
            ->route('administrator.menu.categories.index')
            ->with('status', 'Menu category removed successfully.');
    }
}
