<?php

namespace App\Services\Dining;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Events\Dining\DiningBillReady;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Dining\DiningRoundPlaced;
use App\Models\CafeTable;
use App\Models\DiningRoundDraft;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\Promotion\PromotionServiceInterface;
use App\Services\Tax\TaxCalculatorInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DiningSessionService implements DiningSessionServiceInterface
{
    public function __construct(
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected CafeAvailabilityServiceInterface $cafeAvailability,
        protected TaxCalculatorInterface $taxCalculator,
        protected PromotionServiceInterface $promotions,
        protected OrderServiceInterface $orders,
        protected StaffNotificationDispatcherInterface $staffNotifications,
    ) {}

    public function startSession(
        CafeTable $table,
        ?User $customer = null,
        ?User $openedBy = null,
        array $options = [],
    ): DiningSession {
        if (! $this->websiteSettings->diningEnabled()) {
            throw ValidationException::withMessages([
                'dining' => 'Dining is not available right now.',
            ]);
        }

        if (! $table->is_active) {
            throw ValidationException::withMessages([
                'cafe_table_id' => 'That table is not available.',
            ]);
        }

        $this->cafeAvailability->assertOrderingAvailable();

        return DB::transaction(function () use ($table, $customer, $openedBy, $options): DiningSession {
            $lockedTable = CafeTable::query()->whereKey($table->getKey())->lockForUpdate()->firstOrFail();

            if ($this->findActiveForTable($lockedTable) !== null) {
                throw ValidationException::withMessages([
                    'cafe_table_id' => 'This table already has an active dining session.',
                ]);
            }

            if ($customer instanceof User && $this->findActiveForCustomer($customer) !== null) {
                throw ValidationException::withMessages([
                    'customer' => 'You already have an open dining session. Finish it before starting another.',
                ]);
            }

            $guestCount = array_key_exists('guest_count', $options) && $options['guest_count'] !== null
                ? (int) $options['guest_count']
                : null;

            if ($guestCount !== null && ($guestCount < 1 || $guestCount > 50)) {
                throw ValidationException::withMessages([
                    'guest_count' => 'Guest count must be between 1 and 50.',
                ]);
            }

            $session = DiningSession::query()->create([
                'session_number' => $this->nextSessionNumber(),
                'cafe_table_id' => $lockedTable->getKey(),
                'customer_id' => $customer?->getKey(),
                'opened_by_user_id' => $openedBy?->getKey(),
                'status' => DiningSessionStatus::Open,
                'guest_count' => $guestCount,
                'table_name_snapshot' => $lockedTable->snapshotLabel(),
                'customer_name_snapshot' => $customer?->name,
                'customer_phone_snapshot' => $customer?->phone,
                'opened_at' => now(),
            ]);

            return $session->fresh(['cafeTable', 'customer', 'openedBy', 'drafts', 'orders']);
        });
    }

    public function addDraftItem(
        DiningSession $session,
        int $productVariantId,
        int $quantity,
        ?User $customer = null,
    ): DiningRoundDraft {
        $this->assertAllowsNewRounds($session);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $variant = $this->assertOrderableVariant($productVariantId);

        return DB::transaction(function () use ($session, $variant, $quantity, $customer): DiningRoundDraft {
            $draft = DiningRoundDraft::query()
                ->where('dining_session_id', $session->getKey())
                ->where('product_variant_id', $variant->getKey())
                ->lockForUpdate()
                ->first();

            if ($draft) {
                $draft->update(['quantity' => $draft->quantity + $quantity]);
            } else {
                $draft = DiningRoundDraft::query()->create([
                    'dining_session_id' => $session->getKey(),
                    'customer_id' => $customer?->getKey() ?? $session->customer_id,
                    'product_variant_id' => $variant->getKey(),
                    'quantity' => $quantity,
                ]);
            }

            return $draft->fresh(['productVariant.product']);
        });
    }

    public function updateDraftItem(
        DiningSession $session,
        DiningRoundDraft $draft,
        int $quantity,
    ): DiningRoundDraft {
        $this->assertDraftBelongs($session, $draft);
        $this->assertAllowsNewRounds($session);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $this->assertOrderableVariant((int) $draft->product_variant_id);
        $draft->update(['quantity' => $quantity]);

        return $draft->fresh(['productVariant.product']);
    }

    public function removeDraftItem(DiningSession $session, DiningRoundDraft $draft): void
    {
        $this->assertDraftBelongs($session, $draft);
        $this->assertAllowsNewRounds($session);
        $draft->delete();
    }

    public function clearDrafts(DiningSession $session): void
    {
        $this->assertAllowsNewRounds($session);
        DiningRoundDraft::query()->where('dining_session_id', $session->getKey())->delete();
    }

    public function placeRound(DiningSession $session, User $actor, ?string $customerNotes = null): Order
    {
        $this->assertAllowsNewRounds($session);

        return DB::transaction(function () use ($session, $actor, $customerNotes): Order {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $this->assertAllowsNewRounds($locked);

            $drafts = DiningRoundDraft::query()
                ->where('dining_session_id', $locked->getKey())
                ->lockForUpdate()
                ->get();

            if ($drafts->isEmpty()) {
                throw ValidationException::withMessages([
                    'drafts' => 'Add at least one item before placing a round.',
                ]);
            }

            $items = $drafts
                ->map(static fn (DiningRoundDraft $draft): array => [
                    'product_variant_id' => (int) $draft->product_variant_id,
                    'quantity' => (int) $draft->quantity,
                ])
                ->values()
                ->all();

            $order = $this->orders->placeDiningRound($actor, $locked, $items, $customerNotes);

            DiningRoundDraft::query()->where('dining_session_id', $locked->getKey())->delete();

            event(new DiningRoundPlaced($order, $locked));

            return $order;
        });
    }

    public function runningBill(DiningSession $session): array
    {
        $session->loadMissing(['orders.items']);

        $rounds = [];
        $subtotal = '0.00';
        $discount = '0.00';

        foreach ($session->orders as $order) {
            if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                continue;
            }

            $subtotal = bcadd($subtotal, (string) $order->subtotal, 2);
            $discount = bcadd($discount, (string) $order->discount_total, 2);
            $rounds[] = [
                'order_id' => (int) $order->getKey(),
                'round_number' => (int) ($order->dining_round_number ?? 0),
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'total' => number_format((float) $order->total_amount, 2, '.', ''),
            ];
        }

        $afterDiscount = bcsub($subtotal, $discount, 2);
        if (bccomp($afterDiscount, '0', 2) < 0) {
            $afterDiscount = '0.00';
        }

        $tax = $this->taxCalculator->calculateForTaxableAmount($afterDiscount);
        $total = $this->taxCalculator->payableTotal($tax);

        return [
            'subtotal' => number_format((float) $subtotal, 2, '.', ''),
            'discount' => number_format((float) $discount, 2, '.', ''),
            'taxable' => $tax->taxableAmount,
            'tax' => $tax->taxAmount,
            'total' => $total,
            'tax_enabled' => $tax->enabled,
            'tax_label' => $tax->enabled ? $tax->label : null,
            'tax_percent' => $tax->enabled ? $tax->percent : null,
            'tax_inclusive' => $tax->enabled ? $tax->inclusive : false,
            'rounds' => $rounds,
        ];
    }

    public function requestBill(DiningSession $session, User $actor): DiningSession
    {
        return $this->generateFinalBill($session, $actor);
    }

    public function generateFinalBill(DiningSession $session, User $actor): DiningSession
    {
        return DB::transaction(function () use ($session, $actor): DiningSession {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [DiningSessionStatus::Open, DiningSessionStatus::BillingRequested], true)) {
                throw ValidationException::withMessages([
                    'status' => 'A bill can only be generated for an open dining session.',
                ]);
            }

            $activeRounds = $locked->orders()
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Rejected->value])
                ->count();

            if ($activeRounds < 1) {
                throw ValidationException::withMessages([
                    'orders' => 'Place at least one round before requesting the bill.',
                ]);
            }

            DiningRoundDraft::query()->where('dining_session_id', $locked->getKey())->delete();

            $bill = $this->runningBill($locked);
            $promotionResult = $this->promotions->assertAndEvaluateForCheckout([
                'customer' => $locked->customer,
                'fulfilment' => OrderFulfilmentMethod::DineIn,
                'promo_code' => null,
                'items' => $this->aggregateItemsForPromotion($locked),
            ]);

            $promoDiscount = (string) ($promotionResult['discount_total'] ?? '0.00');
            $discount = bcadd($bill['discount'], $promoDiscount, 2);
            $afterDiscount = bcsub($bill['subtotal'], $discount, 2);
            if (bccomp($afterDiscount, '0', 2) < 0) {
                $afterDiscount = '0.00';
            }

            $tax = $this->taxCalculator->calculateForTaxableAmount($afterDiscount);
            $total = $this->taxCalculator->payableTotal($tax);
            $now = now();

            $locked->fill([
                'status' => DiningSessionStatus::AwaitingPayment,
                'billing_requested_at' => $locked->billing_requested_at ?? $now,
                'bill_generated_at' => $now,
                'subtotal_amount' => $bill['subtotal'],
                'discount_amount' => number_format((float) $discount, 2, '.', ''),
                'taxable_amount' => $tax->taxableAmount,
                'tax_amount' => $tax->taxAmount,
                'tax_enabled_snapshot' => $tax->enabled,
                'tax_label_snapshot' => $tax->enabled ? $tax->label : null,
                'tax_percent_snapshot' => $tax->enabled ? $tax->percent : null,
                'tax_inclusive_snapshot' => $tax->enabled ? $tax->inclusive : false,
                'total_amount' => $total,
                'payment_status' => PaymentStatus::Pending,
            ])->save();

            $fresh = $locked->fresh(['cafeTable', 'customer', 'orders.items', 'drafts']);
            event(new DiningBillReady($fresh, $actor));

            return $fresh;
        });
    }

    public function setPaymentMethod(DiningSession $session, string $paymentMethodApiKey): DiningSession
    {
        if (! in_array($session->status, [
            DiningSessionStatus::BillingRequested,
            DiningSessionStatus::AwaitingPayment,
        ], true)) {
            throw ValidationException::withMessages([
                'payment_method' => 'Set a payment method after the bill is requested.',
            ]);
        }

        $method = PaymentMethod::tryFromApiKey($paymentMethodApiKey);
        if ($method === null) {
            throw ValidationException::withMessages([
                'payment_method' => 'Choose cash or UPI for dining payment.',
            ]);
        }

        $session->fill([
            'payment_method' => $method,
            'status' => DiningSessionStatus::AwaitingPayment,
            'payment_status' => PaymentStatus::Pending,
        ])->save();

        return $session->fresh(['cafeTable', 'customer', 'orders.items']);
    }

    public function uploadPaymentProof(DiningSession $session, User $actor, UploadedFile $file): DiningSession
    {
        $isOwner = $session->customer_id && (int) $session->customer_id === (int) $actor->getKey();
        if (! $isOwner && ! $actor->canOperateDining() && ! $actor->canManageOrders()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'You cannot upload payment proof for this session.',
            ]);
        }

        if (! $session->canUploadPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Payment proof cannot be uploaded for this session right now.',
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $safeExtension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'jpg';
        $filename = Str::uuid()->toString().'.'.$safeExtension;
        $path = $file->storeAs('dining-payment-proofs/'.$session->getKey(), $filename, 'local');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'payment_proof' => 'Unable to store the payment proof. Please try again.',
            ]);
        }

        $session->clearPaymentProofFiles();
        $session->fill([
            'payment_proof_path' => $path,
            'payment_proof_disk' => 'local',
            'payment_proof_mime' => $file->getMimeType(),
            'payment_proof_size' => $file->getSize(),
            'payment_proof_uploaded_at' => now(),
            'payment_status' => PaymentStatus::AwaitingReview,
            'payment_proof_rejection_notes' => null,
            'status' => DiningSessionStatus::AwaitingPayment,
        ])->save();

        $relatedOrder = $session->orders()->latest('id')->first();
        if ($relatedOrder instanceof Order) {
            $this->staffNotifications->notify(
                StaffNotificationType::PaymentProofReceived,
                'staff:dining_payment_proof:'.$session->getKey().':'.now()->timestamp,
                StaffNotificationAudience::Administrators,
                StaffNotificationContext::forOrder($relatedOrder),
                true,
            );
        }

        return $session->fresh(['cafeTable', 'customer', 'orders.items']);
    }

    public function confirmPayment(DiningSession $session, User $actor): DiningSession
    {
        if (! $actor->canOperateDining() && ! $actor->canManageOrders()) {
            throw ValidationException::withMessages([
                'payment' => 'You are not allowed to confirm dining payment.',
            ]);
        }

        return DB::transaction(function () use ($session, $actor): DiningSession {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === DiningSessionStatus::Paid || $locked->payment_status === PaymentStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'payment' => 'This dining session is already paid.',
                ]);
            }

            if (! in_array($locked->status, [
                DiningSessionStatus::BillingRequested,
                DiningSessionStatus::AwaitingPayment,
            ], true)) {
                throw ValidationException::withMessages([
                    'payment' => 'Confirm payment only after the bill is ready.',
                ]);
            }

            $locked->fill([
                'status' => DiningSessionStatus::Paid,
                'payment_status' => PaymentStatus::Confirmed,
                'paid_at' => now(),
                'payment_received_by_id' => $actor->getKey(),
                'payment_proof_rejection_notes' => null,
            ])->save();

            $fresh = $locked->fresh(['cafeTable', 'customer', 'orders.items', 'paymentReceivedBy']);
            event(new DiningPaymentConfirmed($fresh, $actor));

            return $fresh;
        });
    }

    public function markCashReceived(DiningSession $session, User $actor): DiningSession
    {
        if ($session->payment_method !== PaymentMethod::Cash) {
            $session = $this->setPaymentMethod($session, PaymentMethod::Cash->apiKey());
        }

        return $this->confirmPayment($session, $actor);
    }

    public function closeSession(DiningSession $session, User $actor): DiningSession
    {
        if (! $actor->canOperateDining() && ! $actor->canManageOrders()) {
            throw ValidationException::withMessages([
                'session' => 'You are not allowed to close this dining session.',
            ]);
        }

        return DB::transaction(function () use ($session): DiningSession {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== DiningSessionStatus::Paid && $locked->payment_status !== PaymentStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'session' => 'Close the session only after payment is confirmed.',
                ]);
            }

            $locked->fill([
                'status' => DiningSessionStatus::Closed,
                'closed_at' => now(),
            ])->save();

            DiningRoundDraft::query()->where('dining_session_id', $locked->getKey())->delete();

            return $locked->fresh(['cafeTable', 'customer', 'orders.items']);
        });
    }

    public function reopenSession(DiningSession $session, User $actor, ?string $note = null): DiningSession
    {
        if (! $actor->canOperateDining() && ! $actor->canManageOrders()) {
            throw ValidationException::withMessages([
                'session' => 'You are not allowed to reopen this dining session.',
            ]);
        }

        return DB::transaction(function () use ($session, $note): DiningSession {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [
                DiningSessionStatus::BillingRequested,
                DiningSessionStatus::AwaitingPayment,
            ], true)) {
                throw ValidationException::withMessages([
                    'session' => 'Only unpaid bill-requested sessions can be reopened for more orders.',
                ]);
            }

            if ($locked->payment_status === PaymentStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'session' => 'A paid session cannot be reopened.',
                ]);
            }

            $locked->fill([
                'status' => DiningSessionStatus::Open,
                'billing_requested_at' => null,
                'bill_generated_at' => null,
                'subtotal_amount' => null,
                'discount_amount' => null,
                'tax_amount' => null,
                'taxable_amount' => null,
                'tax_enabled_snapshot' => null,
                'tax_label_snapshot' => null,
                'tax_percent_snapshot' => null,
                'tax_inclusive_snapshot' => null,
                'total_amount' => null,
                'payment_method' => null,
                'payment_status' => null,
                'payment_reference' => $note !== null && $note !== ''
                    ? Str::limit('Reopened: '.$note, 240)
                    : $locked->payment_reference,
            ])->save();

            return $locked->fresh(['cafeTable', 'customer', 'orders.items', 'drafts']);
        });
    }

    public function tableOperationalStates(): Collection
    {
        $tables = CafeTable::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $activeSessions = DiningSession::query()
            ->with(['customer', 'orders'])
            ->whereIn('status', [
                DiningSessionStatus::Open->value,
                DiningSessionStatus::BillingRequested->value,
                DiningSessionStatus::AwaitingPayment->value,
                DiningSessionStatus::Paid->value,
            ])
            ->get()
            ->keyBy('cafe_table_id');

        return $tables->map(function (CafeTable $table) use ($activeSessions): array {
            /** @var DiningSession|null $session */
            $session = $activeSessions->get($table->getKey());
            $state = $session?->status instanceof DiningSessionStatus
                ? $session->status->tableOperationalState()
                : 'available';

            if (! $table->is_active && $state === 'available') {
                $state = 'inactive';
            }

            return [
                'table' => $table,
                'state' => $state,
                'session' => $session,
            ];
        });
    }

    public function findActiveForCustomer(User $customer): ?DiningSession
    {
        return DiningSession::query()
            ->with(['cafeTable', 'drafts.productVariant.product', 'orders.items'])
            ->where('customer_id', $customer->getKey())
            ->whereIn('status', [
                DiningSessionStatus::Open->value,
                DiningSessionStatus::BillingRequested->value,
                DiningSessionStatus::AwaitingPayment->value,
                DiningSessionStatus::Paid->value,
            ])
            ->latest('id')
            ->first();
    }

    public function findActiveForTable(CafeTable $table): ?DiningSession
    {
        return DiningSession::query()
            ->where('cafe_table_id', $table->getKey())
            ->whereIn('status', [
                DiningSessionStatus::Open->value,
                DiningSessionStatus::BillingRequested->value,
                DiningSessionStatus::AwaitingPayment->value,
                DiningSessionStatus::Paid->value,
            ])
            ->latest('id')
            ->first();
    }

    protected function nextSessionNumber(): string
    {
        $date = now()->format('ymd');
        $sequence = DiningSession::query()
            ->whereDate('opened_at', now()->toDateString())
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('DS-%s-%s', $date, str_pad((string) $sequence, 4, '0', STR_PAD_LEFT));
    }

    protected function assertAllowsNewRounds(DiningSession $session): void
    {
        if (! $session->allowsNewRounds()) {
            throw ValidationException::withMessages([
                'session' => 'This dining session is no longer accepting new rounds.',
            ]);
        }
    }

    protected function assertDraftBelongs(DiningSession $session, DiningRoundDraft $draft): void
    {
        if ((int) $draft->dining_session_id !== (int) $session->getKey()) {
            throw ValidationException::withMessages([
                'draft' => 'That draft item does not belong to this dining session.',
            ]);
        }
    }

    protected function assertOrderableVariant(int $productVariantId): ProductVariant
    {
        $variant = ProductVariant::query()
            ->with('product')
            ->whereKey($productVariantId)
            ->first();

        if (
            ! $variant
            || ! $variant->is_active
            || ! $variant->is_available
            || ! $variant->product
            || ! $variant->product->is_active
            || ! $variant->product->is_available
        ) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'Only active and available product variants can be ordered.',
            ]);
        }

        return $variant;
    }

    /**
     * @return list<array{product_id: ?int, product_variant_id: ?int, product_category_id: ?int, quantity: int, unit_price: string, line_subtotal: string}>
     */
    protected function aggregateItemsForPromotion(DiningSession $session): array
    {
        $session->loadMissing(['orders.items.product']);
        $aggregated = [];

        foreach ($session->orders as $order) {
            if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                continue;
            }

            foreach ($order->items as $item) {
                $key = (int) $item->product_variant_id;
                if (! isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'product_id' => $item->product_id ? (int) $item->product_id : null,
                        'product_variant_id' => $key,
                        'product_category_id' => $item->product?->product_category_id
                            ? (int) $item->product->product_category_id
                            : null,
                        'quantity' => 0,
                        'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                        'line_subtotal' => '0.00',
                    ];
                }

                $aggregated[$key]['quantity'] += (int) $item->quantity;
                $aggregated[$key]['line_subtotal'] = bcadd(
                    $aggregated[$key]['line_subtotal'],
                    (string) $item->line_subtotal,
                    2,
                );
            }
        }

        return array_values($aggregated);
    }
}
