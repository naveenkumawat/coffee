<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\PromotionDiscountType;
use App\Enums\PromotionFulfilmentScope;
use App\Enums\PromotionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\PromotionStoreRequest;
use App\Http\Requests\Promotion\PromotionUpdateRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Promotion\PromotionCatalogServiceInterface;
use App\Services\Promotion\PromotionServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionCatalogServiceInterface $catalog,
        protected PromotionServiceInterface $promotions,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Promotion::class);

        return view('administrator.promotions.index', [
            'promotions' => $this->catalog->paginateForAdmin(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Promotion::class);

        return view('administrator.promotions.create', [
            'promotion' => new Promotion([
                'type' => PromotionType::Automatic,
                'discount_type' => PromotionDiscountType::Percentage,
                'discount_value' => 10,
                'priority' => 0,
                'is_active' => true,
                'stackable' => false,
                'applies_to_all_products' => true,
                'applies_to_all_customers' => true,
                'first_order_only' => false,
                'fulfilment_scope' => PromotionFulfilmentScope::Any,
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(PromotionStoreRequest $request): RedirectResponse
    {
        $promotion = $this->catalog->store($request->validated());

        return redirect()
            ->route('administrator.promotions.edit', $promotion)
            ->with('status', 'Offer created successfully.');
    }

    public function edit(Promotion $promotion): View
    {
        $this->authorize('update', $promotion);

        $promotion->load(['products', 'productCategories', 'customers']);

        return view('administrator.promotions.edit', [
            'promotion' => $promotion,
            'usageCount' => $this->promotions->usageCount($promotion),
            ...$this->formOptions($promotion),
        ]);
    }

    public function update(PromotionUpdateRequest $request, Promotion $promotion): RedirectResponse
    {
        $this->authorize('update', $promotion);

        $this->catalog->update($promotion, $request->validated());

        return redirect()
            ->route('administrator.promotions.edit', $promotion)
            ->with('status', 'Offer updated successfully.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->authorize('delete', $promotion);

        $this->catalog->delete($promotion);

        return redirect()
            ->route('administrator.promotions.index')
            ->with('status', 'Offer archived successfully.');
    }

    public function toggle(Promotion $promotion): RedirectResponse
    {
        $this->authorize('update', $promotion);

        $this->catalog->setActive($promotion, ! $promotion->is_active);

        return redirect()
            ->route('administrator.promotions.index')
            ->with('status', $promotion->fresh()?->is_active ? 'Offer activated.' : 'Offer deactivated.');
    }

    /**
     * @return array{
     *     typeOptions: array<string, string>,
     *     discountTypeOptions: array<string, string>,
     *     fulfilmentScopeOptions: array<string, string>,
     *     weekdayOptions: array<int, string>,
     *     productOptions: array<int, string>,
     *     categoryOptions: array<int, string>,
     *     customerOptions: array<int, string>,
     *     selectedProductIds: list<string>,
     *     selectedCategoryIds: list<string>,
     *     selectedCustomerIds: list<string>,
     *     selectedWeekdays: list<string>
     * }
     */
    protected function formOptions(?Promotion $promotion = null): array
    {
        $selectedProductIds = collect(old('product_ids', $promotion?->products->modelKeys() ?? []))
            ->map(fn ($id): string => (string) $id)
            ->all();
        $selectedCategoryIds = collect(old('product_category_ids', $promotion?->productCategories->modelKeys() ?? []))
            ->map(fn ($id): string => (string) $id)
            ->all();
        $selectedCustomerIds = collect(old('customer_ids', $promotion?->customers->modelKeys() ?? []))
            ->map(fn ($id): string => (string) $id)
            ->all();
        $selectedWeekdays = collect(old('weekdays', $promotion?->weekdays ?? []))
            ->map(fn ($day): string => (string) $day)
            ->all();

        return [
            'typeOptions' => PromotionType::options(),
            'discountTypeOptions' => PromotionDiscountType::options(),
            'fulfilmentScopeOptions' => PromotionFulfilmentScope::options(),
            'weekdayOptions' => [
                0 => 'Sunday',
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
            ],
            'productOptions' => Product::query()->orderBy('name')->pluck('name', 'id')->all(),
            'categoryOptions' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'customerOptions' => User::query()
                ->where('role', UserRole::Customer->value)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all(),
            'selectedProductIds' => $selectedProductIds,
            'selectedCategoryIds' => $selectedCategoryIds,
            'selectedCustomerIds' => $selectedCustomerIds,
            'selectedWeekdays' => $selectedWeekdays,
        ];
    }
}
