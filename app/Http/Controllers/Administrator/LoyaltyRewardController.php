<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\LoyaltyRewardStatus;
use App\Enums\LoyaltyRewardType;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoyaltyReward\LoyaltyRewardBulkStatusRequest;
use App\Http\Requests\LoyaltyReward\LoyaltyRewardStoreRequest;
use App\Http\Requests\LoyaltyReward\LoyaltyRewardUpdateRequest;
use App\Models\AddOn;
use App\Models\LoyaltyReward;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Loyalty\LoyaltyReportingServiceInterface;
use App\Services\Loyalty\LoyaltyRewardCatalogServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LoyaltyRewardController extends Controller
{
    public function __construct(
        protected LoyaltyRewardCatalogServiceInterface $catalog,
        protected LoyaltyReportingServiceInterface $reporting,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LoyaltyReward::class);

        $status = trim((string) $request->query('status', 'all'));
        $includeArchived = $request->boolean('include_archived');

        return view('administrator.loyalty-rewards.index', [
            'rewards' => $this->catalog->paginateForAdmin(30, [
                'status' => $status,
                'include_archived' => $includeArchived,
            ]),
            'filters' => [
                'status' => $status !== '' ? $status : 'all',
                'include_archived' => $includeArchived,
            ],
            'statusOptions' => collect(LoyaltyRewardStatus::cases())
                ->mapWithKeys(fn (LoyaltyRewardStatus $case): array => [$case->value => $case->label()])
                ->all(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LoyaltyReward::class);

        return view('administrator.loyalty-rewards.create', [
            'reward' => new LoyaltyReward([
                'status' => LoyaltyRewardStatus::Active,
                'reward_type' => LoyaltyRewardType::FixedOrderDiscount,
                'points_cost' => 100,
                'priority' => 0,
                'config' => ['discount_amount' => '50.00'],
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(LoyaltyRewardStoreRequest $request): RedirectResponse
    {
        $reward = $this->catalog->store($request->validated());

        return redirect()
            ->route('administrator.loyalty-rewards.edit', $reward)
            ->with('status', 'Loyalty reward created successfully.');
    }

    public function edit(LoyaltyReward $loyaltyReward): View
    {
        $this->authorize('update', $loyaltyReward);

        $loyaltyReward->load(['products', 'productCategories', 'addOns']);
        $dashboard = $this->reporting->buildOperationsDashboard([
            'preset' => 'last_7_days',
        ]);
        $performance = collect($dashboard['reward_performance'] ?? [])
            ->firstWhere('reward_id', (int) $loyaltyReward->getKey());

        return view('administrator.loyalty-rewards.edit', [
            'reward' => $loyaltyReward,
            'performance' => $performance,
            'performancePeriod' => [
                'start_local' => $dashboard['start_local'],
                'end_local' => $dashboard['end_local'],
                'timezone' => $dashboard['timezone'],
            ],
            ...$this->formOptions($loyaltyReward),
        ]);
    }

    public function update(LoyaltyRewardUpdateRequest $request, LoyaltyReward $loyaltyReward): RedirectResponse
    {
        $this->authorize('update', $loyaltyReward);

        $this->catalog->update($loyaltyReward, $request->validated());

        return redirect()
            ->route('administrator.loyalty-rewards.edit', $loyaltyReward)
            ->with('status', 'Loyalty reward updated successfully.');
    }

    public function destroy(LoyaltyReward $loyaltyReward): RedirectResponse
    {
        $this->authorize('delete', $loyaltyReward);

        $this->catalog->archive($loyaltyReward);

        return redirect()
            ->route('administrator.loyalty-rewards.index')
            ->with('status', 'Loyalty reward archived successfully.');
    }

    public function setStatus(LoyaltyReward $loyaltyReward, string $status): RedirectResponse
    {
        $this->authorize('update', $loyaltyReward);

        $this->catalog->setStatus($loyaltyReward, LoyaltyRewardStatus::from($status));

        return redirect()
            ->route('administrator.loyalty-rewards.index')
            ->with('status', 'Loyalty reward status updated.');
    }

    public function duplicate(LoyaltyReward $loyaltyReward): RedirectResponse
    {
        $this->authorize('create', LoyaltyReward::class);

        $copy = $this->catalog->duplicate($loyaltyReward);

        return redirect()
            ->route('administrator.loyalty-rewards.edit', $copy)
            ->with('status', 'Loyalty reward duplicated as paused draft.');
    }

    public function bulkStatus(LoyaltyRewardBulkStatusRequest $request): RedirectResponse
    {
        $this->authorize('viewAny', LoyaltyReward::class);

        $validated = $request->validated();
        $result = $this->catalog->bulkSetStatus(
            $validated['reward_ids'],
            LoyaltyRewardStatus::from((string) $validated['status']),
        );

        $message = sprintf(
            'Updated %d reward(s).',
            (int) $result['updated'],
        );

        if ($result['failed'] !== []) {
            $message .= ' '.count($result['failed']).' could not be updated (missing or archived).';
        }

        return redirect()
            ->route('administrator.loyalty-rewards.index')
            ->with('status', $message);
    }

    /**
     * @return array{
     *     typeOptions: array<string, string>,
     *     statusOptions: array<string, string>,
     *     productOptions: array<int, string>,
     *     categoryOptions: array<int, string>,
     *     addOnOptions: array<int, string>,
     *     selectedProductIds: list<string>,
     *     selectedCategoryIds: list<string>,
     *     selectedAddOnIds: list<string>
     * }
     */
    protected function formOptions(?LoyaltyReward $reward = null): array
    {
        $config = is_array($reward?->config) ? $reward->config : [];

        return [
            'typeOptions' => collect(LoyaltyRewardType::cases())
                ->mapWithKeys(fn (LoyaltyRewardType $type): array => [$type->value => $type->label()])
                ->all(),
            'statusOptions' => collect(LoyaltyRewardStatus::cases())
                ->mapWithKeys(fn (LoyaltyRewardStatus $status): array => [$status->value => $status->label()])
                ->all(),
            'productOptions' => Product::query()->orderBy('name')->pluck('name', 'id')->all(),
            'categoryOptions' => ProductCategory::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'addOnOptions' => AddOn::query()->orderBy('name')->pluck('name', 'id')->all(),
            'selectedProductIds' => collect(old('product_ids', $reward?->products->modelKeys() ?? []))
                ->map(fn ($id): string => (string) $id)
                ->all(),
            'selectedCategoryIds' => collect(old('product_category_ids', $reward?->productCategories->modelKeys() ?? []))
                ->map(fn ($id): string => (string) $id)
                ->all(),
            'selectedAddOnIds' => collect(old('add_on_ids', $reward?->addOns->modelKeys() ?? []))
                ->map(fn ($id): string => (string) $id)
                ->all(),
            'configDiscountAmount' => old('discount_amount', $config['discount_amount'] ?? null),
            'configPercent' => old('percent', $config['percent'] ?? null),
            'configMaximumDiscountAmount' => old('maximum_discount_amount', $config['maximum_discount_amount'] ?? null),
        ];
    }
}
