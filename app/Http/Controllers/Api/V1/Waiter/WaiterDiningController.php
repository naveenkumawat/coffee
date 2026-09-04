<?php

namespace App\Http\Controllers\Api\V1\Waiter;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dining\DiningRoundCancelRequest;
use App\Http\Resources\Api\V1\Dining\WaiterDiningSessionResource;
use App\Models\CafeTable;
use App\Models\DiningRoundDraft;
use App\Models\DiningRoundDraftAddOn;
use App\Models\DiningSession;
use App\Models\Order;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Invoice\DiningInvoiceServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WaiterDiningController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected DiningSessionServiceInterface $dining,
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected DiningInvoiceServiceInterface $invoices,
    ) {}

    public function tables(): JsonResponse
    {
        $this->authorize('viewAny', DiningSession::class);

        $states = $this->dining->tableOperationalStatesForWaiter()
            ->filter(static fn (array $row): bool => (bool) $row['table']->is_active)
            ->map(static fn (array $row): array => [
                'id' => $row['table']->getKey(),
                'code' => $row['table']->code,
                'name' => $row['table']->name,
                'label' => $row['table']->displayLabel(),
                'state' => $row['state'],
                'display_state' => $row['display_state'],
                'display_state_label' => $row['display_state_label'],
                'available' => $row['display_state'] === 'available',
                'session' => $row['session_summary'],
            ])
            ->values()
            ->all();

        return $this->respondWithData($states, 'Waiter tables retrieved.');
    }

    public function storeSession(Request $request): JsonResponse
    {
        $this->authorize('create', DiningSession::class);

        $data = $request->validate([
            'cafe_table_id' => ['required', 'integer', Rule::exists('cafe_tables', 'id')],
            'guest_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $table = CafeTable::query()->findOrFail($data['cafe_table_id']);
        $session = $this->dining->startSession(
            $table,
            null,
            $request->user(),
            ['guest_count' => $data['guest_count'] ?? null],
        );

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Dining session started.',
            201,
        );
    }

    public function showSession(DiningSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Dining session retrieved.',
        );
    }

    public function storeDraft(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('update', $session);

        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', Rule::exists('product_variants', 'id')],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'add_ons' => ['sometimes', 'array'],
            'add_ons.*.add_on_id' => ['required', 'integer', 'distinct', Rule::exists('add_ons', 'id')->whereNull('deleted_at')],
            'add_ons.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->dining->addDraftItem(
            $session,
            (int) $data['product_variant_id'],
            (int) $data['quantity'],
            $request->user(),
            $data['add_ons'] ?? [],
        );

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session->fresh())),
            'Draft item added.',
        );
    }

    public function updateDraft(Request $request, DiningSession $session, DiningRoundDraft $draft): JsonResponse
    {
        $this->authorize('update', $session);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'product_variant_id' => ['sometimes', 'integer', Rule::exists('product_variants', 'id')],
            'add_ons' => ['sometimes', 'array'],
            'add_ons.*.add_on_id' => ['required', 'integer', 'distinct', Rule::exists('add_ons', 'id')->whereNull('deleted_at')],
            'add_ons.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        if (array_key_exists('add_ons', $data) || array_key_exists('product_variant_id', $data)) {
            $variantId = (int) ($data['product_variant_id'] ?? $draft->product_variant_id);
            $quantity = (int) $data['quantity'];
            $addOns = array_key_exists('add_ons', $data)
                ? $data['add_ons']
                : $draft->draftAddOns()->get()->map(static fn (DiningRoundDraftAddOn $row): array => [
                    'add_on_id' => (int) $row->add_on_id,
                    'quantity' => (int) $row->quantity,
                ])->all();

            $this->dining->removeDraftItem($session, $draft);
            $this->dining->addDraftItem($session, $variantId, $quantity, $request->user(), $addOns);
        } else {
            $this->dining->updateDraftItem($session, $draft, (int) $data['quantity']);
        }

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session->fresh())),
            'Draft item updated.',
        );
    }

    public function destroyDraft(DiningSession $session, DiningRoundDraft $draft): JsonResponse
    {
        $this->authorize('update', $session);
        $this->dining->removeDraftItem($session, $draft);

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session->fresh())),
            'Draft item removed.',
        );
    }

    public function clearDrafts(DiningSession $session): JsonResponse
    {
        $this->authorize('update', $session);
        $this->dining->clearDrafts($session);

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session->fresh())),
            'Draft cleared.',
        );
    }

    public function placeRound(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('placeRound', $session);

        $data = $request->validate([
            'customer_notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'discard_draft_conflict' => ['sometimes', 'boolean'],
        ]);

        $idempotencyKey = filled($data['idempotency_key'] ?? null)
            ? trim((string) $data['idempotency_key'])
            : trim((string) $request->header('Idempotency-Key', ''));

        if ($idempotencyKey !== '') {
            $cacheKey = $this->roundIdempotencyCacheKey($session, $idempotencyKey);
            $cachedSessionId = Cache::get($cacheKey);

            // Only short-circuit when the prior place cleared drafts. A reused key with a
            // new unsent draft must place that draft — never silently skip it.
            if ($cachedSessionId && $session->drafts()->doesntExist()) {
                return $this->respondWithResource(
                    new WaiterDiningSessionResource($this->loadSession($session->fresh())),
                    'Dining round already placed.',
                    200,
                );
            }
        }

        if ($session->drafts()->doesntExist()) {
            throw ValidationException::withMessages([
                'drafts' => 'Add at least one item before placing a round.',
            ]);
        }

        $this->dining->placeRound($session, $request->user(), $data['customer_notes'] ?? null);

        if ($idempotencyKey !== '') {
            Cache::put($this->roundIdempotencyCacheKey($session, $idempotencyKey), $session->getKey(), now()->addDay());
        }

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session->fresh())),
            'Dining round placed.',
            201,
        );
    }

    public function requestBill(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('requestBill', $session);

        $data = $request->validate([
            'discard_draft' => ['sometimes', 'boolean'],
        ]);

        if ($session->drafts()->exists()) {
            if (! ($data['discard_draft'] ?? false)) {
                throw ValidationException::withMessages([
                    'drafts' => 'This table has an unsent draft. Send the round, clear the draft, or confirm discard_draft to continue.',
                ]);
            }

            $this->dining->clearDrafts($session);
        }

        $session = $this->dining->requestBill($session, $request->user());

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Bill requested.',
        );
    }

    public function setPaymentMethod(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('changePaymentMethod', $session);

        $data = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(['manual_upi', 'cash'])],
        ]);

        $session = $this->dining->changePaymentMethod(
            $session,
            $data['payment_method'],
            $request->user(),
        );

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Payment method updated.',
            200,
            ['payment' => $this->websiteSettings->paymentInstructions()],
        );
    }

    public function markCashReceived(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('markCashReceived', $session);
        $session = $this->dining->markCashReceived($session, $request->user());

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Cash marked as received.',
        );
    }

    public function markRoundServed(Request $request, DiningSession $session, Order $order): JsonResponse
    {
        $this->authorize('markServed', $session);
        $this->dining->markRoundServed($session, $order, $request->user());

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Round marked as served.',
        );
    }

    public function acceptRound(Request $request, DiningSession $session, Order $order): JsonResponse
    {
        $this->authorize('transition', $order);
        $this->dining->acceptRound($session, $order, $request->user());

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Round accepted.',
        );
    }

    public function cancelRound(
        DiningRoundCancelRequest $request,
        DiningSession $session,
        Order $order,
    ): JsonResponse {
        $data = $request->validated();
        $this->dining->cancelRound(
            $session,
            $order,
            $request->user(),
            $data['reason'] ?? null,
            $data['notes'] ?? null,
        );

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Round cancelled.',
        );
    }

    public function close(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('close', $session);
        $session = $this->dining->closeSession($session, $request->user());

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Dining session closed.',
        );
    }

    public function reopen(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('reopen', $session);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:240'],
        ]);

        $session = $this->dining->reopenSession($session, $request->user(), $data['note'] ?? null);

        return $this->respondWithResource(
            new WaiterDiningSessionResource($this->loadSession($session)),
            'Dining session reopened.',
        );
    }

    public function invoice(DiningSession $session): Response
    {
        $this->authorize('view', $session);

        if (! $this->invoices->isAvailable($session)) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice is not available for this dining session yet.',
            ]);
        }

        return $this->invoices->downloadPdf($session);
    }

    protected function loadSession(DiningSession $session): DiningSession
    {
        return $session->load([
            'cafeTable',
            'customer',
            'drafts.productVariant.product',
            'drafts.draftAddOns.addOn',
            'orders.items.addOns',
            'orders.preparations',
            'serviceRequests',
        ]);
    }

    protected function roundIdempotencyCacheKey(DiningSession $session, string $key): string
    {
        return sprintf('waiter:dining-round:%d:%s', $session->getKey(), $key);
    }
}
