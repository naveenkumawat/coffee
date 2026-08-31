<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\HomeSection\HomeSectionAttachProductRequest;
use App\Http\Requests\HomeSection\HomeSectionIndexRequest;
use App\Http\Requests\HomeSection\HomeSectionStoreRequest;
use App\Http\Requests\HomeSection\HomeSectionUpdateRequest;
use App\Models\HomeSection;
use App\Models\Product;
use App\Parsers\Home\HomeSectionParserInterface;
use App\Repositories\Home\HomeSectionRepositoryInterface;
use App\Services\Home\HomeSectionServiceInterface;
use App\Services\Product\ProductReadinessServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HomeSectionController extends Controller
{
    public function __construct(
        protected HomeSectionParserInterface $parser,
        protected HomeSectionRepositoryInterface $sections,
        protected HomeSectionServiceInterface $service,
        protected ProductReadinessServiceInterface $readiness,
    ) {}

    public function index(HomeSectionIndexRequest $request): View
    {
        $this->authorize('viewAny', HomeSection::class);

        return view('administrator.home-sections.index', [
            'sections' => $this->sections->paginateForAdmin(
                $this->parser->getFilterTransferFromArrayData($request->validated()),
            ),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', HomeSection::class);

        return view('administrator.home-sections.create', [
            'section' => new HomeSection([
                'is_active' => true,
                'sort_order' => 10,
            ]),
        ]);
    }

    public function store(HomeSectionStoreRequest $request): RedirectResponse
    {
        $section = $this->service->store($this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.home-sections.products', $section)
            ->with('status', 'Homepage section created. Add products next.');
    }

    public function edit(HomeSection $homeSection): View
    {
        $this->authorize('update', $homeSection);

        return view('administrator.home-sections.edit', [
            'section' => $homeSection,
        ]);
    }

    public function update(HomeSectionUpdateRequest $request, HomeSection $homeSection): RedirectResponse
    {
        $this->authorize('update', $homeSection);

        $this->service->update($homeSection, $this->parser->getTransferFromArrayData($request->validated()));

        return redirect()
            ->route('administrator.home-sections.edit', $homeSection)
            ->with('status', 'Homepage section updated successfully.');
    }

    public function destroy(HomeSection $homeSection): RedirectResponse
    {
        $this->authorize('delete', $homeSection);

        $this->service->delete($homeSection);

        return redirect()
            ->route('administrator.home-sections.index')
            ->with('status', 'Homepage section archived successfully.');
    }

    public function toggle(HomeSection $homeSection): RedirectResponse
    {
        $this->authorize('update', $homeSection);

        $this->service->setActive($homeSection, ! $homeSection->is_active);

        return redirect()
            ->route('administrator.home-sections.index')
            ->with('status', $homeSection->fresh()?->is_active ? 'Section activated.' : 'Section deactivated.');
    }

    public function moveUp(HomeSection $homeSection): RedirectResponse
    {
        $this->authorize('update', $homeSection);
        $this->service->move($homeSection, 'up');

        return redirect()
            ->route('administrator.home-sections.index')
            ->with('status', 'Section order updated.');
    }

    public function moveDown(HomeSection $homeSection): RedirectResponse
    {
        $this->authorize('update', $homeSection);
        $this->service->move($homeSection, 'down');

        return redirect()
            ->route('administrator.home-sections.index')
            ->with('status', 'Section order updated.');
    }

    public function products(HomeSection $homeSection): View
    {
        $this->authorize('update', $homeSection);

        $homeSection->load([
            'sectionProducts.product.category',
            'sectionProducts.product.variants.recipe.lines.ingredient',
            'sectionProducts' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
        ]);

        $assignedIds = $homeSection->sectionProducts->pluck('product_id')->all();

        $productOptions = Product::query()
            ->where('is_active', true)
            ->where('is_available', true)
            ->when($assignedIds !== [], fn ($query) => $query->whereKeyNot($assignedIds))
            ->orderBy('name')
            ->pluck('name', 'id');

        $assignedProducts = $homeSection->sectionProducts
            ->pluck('product')
            ->filter()
            ->values();

        return view('administrator.home-sections.products', [
            'section' => $homeSection,
            'productOptions' => $productOptions,
            'readinessReports' => $this->readiness->evaluateMany($assignedProducts),
        ]);
    }

    public function attachProduct(HomeSectionAttachProductRequest $request, HomeSection $homeSection): RedirectResponse
    {
        $this->authorize('update', $homeSection);

        $product = Product::query()->findOrFail((int) $request->validated('product_id'));
        $this->service->attachProduct($homeSection, $product);

        return redirect()
            ->route('administrator.home-sections.products', $homeSection)
            ->with('status', 'Product added to section.');
    }

    public function detachProduct(HomeSection $homeSection, Product $product): RedirectResponse
    {
        $this->authorize('update', $homeSection);
        $this->service->detachProduct($homeSection, $product);

        return redirect()
            ->route('administrator.home-sections.products', $homeSection)
            ->with('status', 'Product removed from section.');
    }

    public function moveProductUp(HomeSection $homeSection, Product $product): RedirectResponse
    {
        $this->authorize('update', $homeSection);
        $this->service->moveProduct($homeSection, $product, 'up');

        return redirect()
            ->route('administrator.home-sections.products', $homeSection)
            ->with('status', 'Product order updated.');
    }

    public function moveProductDown(HomeSection $homeSection, Product $product): RedirectResponse
    {
        $this->authorize('update', $homeSection);
        $this->service->moveProduct($homeSection, $product, 'down');

        return redirect()
            ->route('administrator.home-sections.products', $homeSection)
            ->with('status', 'Product order updated.');
    }
}
