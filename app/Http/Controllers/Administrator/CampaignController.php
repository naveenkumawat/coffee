<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\CampaignCtaType;
use App\Enums\CampaignFrequencyPolicy;
use App\Enums\CampaignPlacement;
use App\Enums\CampaignStatus;
use App\Enums\CampaignSurface;
use App\Enums\CampaignTriggerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\CampaignStoreRequest;
use App\Http\Requests\Campaign\CampaignUpdateRequest;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Promotion;
use App\Services\Campaign\CampaignCatalogServiceInterface;
use App\Services\Campaign\CampaignRuleValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignCatalogServiceInterface $catalog,
        protected CampaignRuleValidator $ruleValidator,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $status = $request->string('status')->toString() ?: null;

        return view('administrator.campaigns.index', [
            'campaigns' => $this->catalog->paginateForAdmin($status),
            'statusFilter' => $status,
            'statusOptions' => CampaignStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        return view('administrator.campaigns.create', [
            'campaign' => new Campaign([
                'status' => CampaignStatus::Draft,
                'surface' => CampaignSurface::Popup,
                'cta_type' => CampaignCtaType::Close,
                'priority' => 10,
                'frequency_policy' => CampaignFrequencyPolicy::OncePerSession,
                'placement_rules' => [
                    'placements' => [CampaignPlacement::Home->value],
                    'category_ids' => [],
                    'product_ids' => [],
                    'product_tag_ids' => [],
                ],
                'targeting_rules' => [
                    'all' => [
                        ['type' => 'identity', 'op' => 'eq', 'value' => 'everyone'],
                    ],
                    'any' => [],
                    'exclude' => [],
                ],
                'trigger_rules' => [
                    'type' => CampaignTriggerType::Immediate->value,
                    'delay_ms' => null,
                    'scroll_percent' => null,
                    'product_view_count' => null,
                ],
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(CampaignStoreRequest $request): RedirectResponse
    {
        $campaign = $this->catalog->store(
            $this->campaignPayload($request),
            $request->user('admin'),
            $request->file('image'),
        );

        return redirect()
            ->route('administrator.campaigns.edit', $campaign)
            ->with('status', 'Campaign created successfully.');
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        return view('administrator.campaigns.edit', [
            'campaign' => $campaign,
            ...$this->formOptions(),
        ]);
    }

    public function update(CampaignUpdateRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $this->catalog->update(
            $campaign,
            $this->campaignPayload($request),
            $request->user('admin'),
            $request->file('image'),
            (bool) $request->boolean('remove_image'),
        );

        return redirect()
            ->route('administrator.campaigns.edit', $campaign)
            ->with('status', 'Campaign updated successfully.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $this->catalog->delete($campaign);

        return redirect()
            ->route('administrator.campaigns.index')
            ->with('status', 'Campaign archived successfully.');
    }

    public function setStatus(Request $request, Campaign $campaign, string $status): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $statusEnum = CampaignStatus::from($status);

        $this->catalog->setStatus($campaign, $statusEnum, $request->user('admin'));

        return redirect()
            ->route('administrator.campaigns.index')
            ->with('status', 'Campaign marked as '.$statusEnum->label().'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignPayload(CampaignStoreRequest $request): array
    {
        // Nested JSON rule blobs are validated for shape in the catalog layer;
        // keep full decoded arrays (validated() would strip unlisted nested keys).
        return array_merge($request->validated(), [
            'placement_rules' => $request->input('placement_rules', []),
            'targeting_rules' => $request->input('targeting_rules', []),
            'trigger_rules' => $request->input('trigger_rules', []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'statusOptions' => CampaignStatus::options(),
            'surfaceOptions' => CampaignSurface::options(),
            'ctaTypeOptions' => CampaignCtaType::options(),
            'frequencyOptions' => CampaignFrequencyPolicy::options(),
            'triggerOptions' => CampaignTriggerType::options(),
            'placementOptions' => CampaignPlacement::options(),
            'productOptions' => Product::query()->orderBy('name')->pluck('name', 'id')->all(),
            'categoryOptions' => ProductCategory::query()->orderBy('name')->pluck('name', 'id')->all(),
            'promotionOptions' => Promotion::query()->orderBy('name')->pluck('name', 'id')->all(),
            'ruleTypeOptions' => collect($this->ruleValidator->allowedRuleTypes())
                ->mapWithKeys(fn (string $type): array => [$type => str_replace('_', ' ', ucfirst($type))])
                ->all(),
            'operatorOptions' => collect($this->ruleValidator->allowedOperators())
                ->mapWithKeys(fn (string $op): array => [$op => strtoupper($op)])
                ->all(),
        ];
    }
}
