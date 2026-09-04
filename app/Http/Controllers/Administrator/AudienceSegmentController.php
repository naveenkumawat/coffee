<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\AudienceSegmentActor;
use App\Enums\AudienceSegmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Segment\AudienceSegmentPreviewRequest;
use App\Http\Requests\Segment\AudienceSegmentStoreRequest;
use App\Http\Requests\Segment\AudienceSegmentUpdateRequest;
use App\Models\AudienceSegment;
use App\Models\User;
use App\Services\Segment\SegmentCatalogServiceInterface;
use App\Services\Segment\SegmentServiceInterface;
use App\Services\Targeting\TargetingRuleValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AudienceSegmentController extends Controller
{
    public function __construct(
        protected SegmentCatalogServiceInterface $catalog,
        protected SegmentServiceInterface $segments,
        protected TargetingRuleValidator $ruleValidator,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', AudienceSegment::class);

        $status = $request->string('status')->toString() ?: null;

        return view('administrator.segments.index', [
            'segments' => $this->catalog->paginateForAdmin($status),
            'statusFilter' => $status,
            'statusOptions' => AudienceSegmentStatus::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AudienceSegment::class);

        return view('administrator.segments.create', [
            'segment' => new AudienceSegment([
                'status' => AudienceSegmentStatus::Draft,
                'actor_scope' => AudienceSegmentActor::Both,
                'rules' => [
                    'all' => [
                        ['type' => 'identity', 'op' => 'eq', 'value' => 'everyone'],
                    ],
                    'any' => [],
                    'exclude' => [],
                ],
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(AudienceSegmentStoreRequest $request): RedirectResponse
    {
        $segment = $this->catalog->store($this->payload($request), $request->user('admin'));

        return redirect()
            ->route('administrator.segments.edit', $segment)
            ->with('status', 'Segment created successfully.');
    }

    public function edit(AudienceSegment $audience_segment): View
    {
        $this->authorize('update', $audience_segment);

        return view('administrator.segments.edit', [
            'segment' => $audience_segment,
            ...$this->formOptions(),
        ]);
    }

    public function update(AudienceSegmentUpdateRequest $request, AudienceSegment $audience_segment): RedirectResponse
    {
        $this->authorize('update', $audience_segment);

        $this->catalog->update($audience_segment, $this->payload($request), $request->user('admin'));

        return redirect()
            ->route('administrator.segments.edit', $audience_segment)
            ->with('status', 'Segment updated successfully.');
    }

    public function destroy(AudienceSegment $audience_segment): RedirectResponse
    {
        $this->authorize('delete', $audience_segment);

        $this->catalog->delete($audience_segment);

        return redirect()
            ->route('administrator.segments.index')
            ->with('status', 'Segment archived successfully.');
    }

    public function setStatus(Request $request, AudienceSegment $audience_segment, string $status): RedirectResponse
    {
        $this->authorize('update', $audience_segment);

        $statusEnum = AudienceSegmentStatus::from($status);
        $this->catalog->setStatus($audience_segment, $statusEnum, $request->user('admin'));

        return redirect()
            ->route('administrator.segments.index')
            ->with('status', 'Segment marked as '.$statusEnum->label().'.');
    }

    public function preview(AudienceSegmentPreviewRequest $request, AudienceSegment $audience_segment): RedirectResponse
    {
        $this->authorize('view', $audience_segment);

        $data = $request->validated();
        $messages = [];

        if (! empty($data['customer_id'])) {
            $customer = User::query()->find((int) $data['customer_id']);

            if ($customer === null || ! $customer->hasRole(UserRole::Customer)) {
                return back()->withErrors(['customer_id' => 'Select a valid customer account.']);
            }

            $result = $this->segments->matches($audience_segment, [], $customer);
            $messages[] = $result['matches']
                ? 'Customer #'.$customer->id.' matches this segment.'
                : 'Customer #'.$customer->id.' does not match ('.$result['reason'].').';
        }

        if (! empty($data['visitor_key'])) {
            $result = $this->segments->matches($audience_segment, [
                'visitor_key' => (string) $data['visitor_key'],
            ]);
            $messages[] = $result['matches']
                ? 'Visitor matches this segment.'
                : 'Visitor does not match ('.$result['reason'].').';
        }

        if ($request->boolean('run_count')) {
            $count = $this->segments->approximateCustomerMatchCount($audience_segment);
            $messages[] = sprintf(
                'Approximate customer matches: %d of %d scanned%s.',
                $count['matched'],
                $count['scanned'],
                $count['capped'] ? ' (capped)' : '',
            );
        }

        if ($messages === []) {
            return back()->withErrors(['preview' => 'Provide a customer, visitor key, or request a count.']);
        }

        return back()->with('status', implode(' ', $messages));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(AudienceSegmentStoreRequest $request): array
    {
        return array_merge($request->validated(), [
            'rules' => $request->input('rules', []),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [
            'statusOptions' => AudienceSegmentStatus::options(),
            'actorOptions' => AudienceSegmentActor::options(),
            'ruleTypeOptions' => collect($this->ruleValidator->segmentRuleTypes())
                ->mapWithKeys(function (string $type): array {
                    $labels = $this->ruleValidator->ruleTypeLabels();

                    return [$type => $labels[$type] ?? str_replace('_', ' ', ucfirst($type))];
                })
                ->all(),
            'operatorOptions' => collect($this->ruleValidator->allowedOperators())
                ->mapWithKeys(fn (string $op): array => [$op => strtoupper($op)])
                ->all(),
        ];
    }
}
