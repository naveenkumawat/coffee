<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Dining\DiningSessionResource;
use App\Models\CafeTable;
use App\Models\DiningRoundDraft;
use App\Models\DiningSession;
use App\Services\Dining\DiningSessionServiceInterface;
use App\Services\Invoice\DiningInvoiceServiceInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CustomerDiningController extends Controller
{
    use InteractsWithApiResponses;

    public function __construct(
        protected DiningSessionServiceInterface $dining,
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected DiningInvoiceServiceInterface $invoices,
    ) {}

    public function tables(): JsonResponse
    {
        if (! $this->websiteSettings->diningEnabled()) {
            return $this->respondWithData([], 'Dining is disabled.');
        }

        $states = $this->dining->tableOperationalStates()
            ->filter(static fn (array $row): bool => (bool) $row['table']->is_active)
            ->map(static fn (array $row): array => [
                'id' => $row['table']->getKey(),
                'code' => $row['table']->code,
                'name' => $row['table']->name,
                'label' => $row['table']->displayLabel(),
                'state' => $row['state'],
                'available' => $row['state'] === 'available',
            ])
            ->values()
            ->all();

        return $this->respondWithData($states, 'Dining tables retrieved.');
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
            $request->user(),
            $request->user(),
            ['guest_count' => $data['guest_count'] ?? null],
        );

        return $this->respondWithResource(
            new DiningSessionResource($session->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Dining session started.',
            201,
        );
    }

    public function activeSession(Request $request): JsonResponse
    {
        $session = $this->dining->findActiveForCustomer($request->user());

        if ($session === null) {
            return $this->respondWithData(null, 'No active dining session.');
        }

        $this->authorize('view', $session);

        return $this->respondWithResource(
            new DiningSessionResource($session->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Active dining session retrieved.',
        );
    }

    public function showSession(DiningSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        return $this->respondWithResource(
            new DiningSessionResource($session->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
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
            new DiningSessionResource($session->fresh()->load([
                'cafeTable',
                'customer',
                'drafts.productVariant.product',
                'drafts.draftAddOns.addOn',
                'orders.items.addOns',
            ])),
            'Draft item added.',
        );
    }

    public function updateDraft(Request $request, DiningSession $session, DiningRoundDraft $draft): JsonResponse
    {
        $this->authorize('update', $session);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $this->dining->updateDraftItem($session, $draft, (int) $data['quantity']);

        return $this->respondWithResource(
            new DiningSessionResource($session->fresh()->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Draft item updated.',
        );
    }

    public function destroyDraft(DiningSession $session, DiningRoundDraft $draft): JsonResponse
    {
        $this->authorize('update', $session);
        $this->dining->removeDraftItem($session, $draft);

        return $this->respondWithResource(
            new DiningSessionResource($session->fresh()->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Draft item removed.',
        );
    }

    public function clearDrafts(DiningSession $session): JsonResponse
    {
        $this->authorize('update', $session);
        $this->dining->clearDrafts($session);

        return $this->respondWithResource(
            new DiningSessionResource($session->fresh()->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Draft cleared.',
        );
    }

    public function placeRound(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('placeRound', $session);

        $data = $request->validate([
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->dining->placeRound($session, $request->user(), $data['customer_notes'] ?? null);

        return $this->respondWithResource(
            new DiningSessionResource($session->fresh()->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Dining round placed.',
            201,
        );
    }

    public function requestBill(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('requestBill', $session);
        $session = $this->dining->requestBill($session, $request->user());

        return $this->respondWithResource(
            new DiningSessionResource($session->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Bill requested.',
        );
    }

    public function setPaymentMethod(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('pay', $session);

        $data = $request->validate([
            'payment_method' => ['required', 'string', Rule::in(['manual_upi', 'manual', 'cash'])],
        ]);

        $session = $this->dining->setPaymentMethod($session, $data['payment_method']);

        return $this->respondWithResource(
            new DiningSessionResource($session->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Payment method saved.',
            200,
            ['payment' => $this->websiteSettings->paymentInstructions()],
        );
    }

    public function uploadPaymentProof(Request $request, DiningSession $session): JsonResponse
    {
        $this->authorize('pay', $session);

        $request->validate([
            'payment_proof' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $session = $this->dining->uploadPaymentProof($session, $request->user(), $request->file('payment_proof'));

        return $this->respondWithResource(
            new DiningSessionResource($session->load(['cafeTable', 'customer', 'drafts.productVariant.product', 'drafts.draftAddOns.addOn', 'orders.items.addOns'])),
            'Payment proof uploaded.',
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
}
