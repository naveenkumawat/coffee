<?php

namespace App\Services\Dining;

use App\Enums\DiningSessionStatus;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderPreparationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationType;
use App\Events\Dining\DiningBillReady;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Dining\DiningPaymentProofReceived;
use App\Events\Dining\DiningPaymentProofRejected;
use App\Events\Dining\DiningRoundPlaced;
use App\Events\Dining\DiningSessionClosed;
use App\Events\Dining\DiningSessionOpened;
use App\Events\Dining\DiningSessionReopened;
use App\Models\CafeTable;
use App\Models\DiningRoundDraft;
use App\Models\DiningRoundDraftAddOn;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AddOn\AddOnServiceInterface;
use App\Services\CafeAvailability\CafeAvailabilityServiceInterface;
use App\Services\Notification\StaffNotificationContext;
use App\Services\Notification\StaffNotificationDispatcherInterface;
use App\Services\Order\OrderServiceInterface;
use App\Services\Promotion\PromotionServiceInterface;
use App\Services\Tax\TaxCalculatorInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use App\Support\AddOnConfiguration;
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
        protected AddOnServiceInterface $addOns,
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

            $fresh = $session->fresh(['cafeTable', 'customer', 'openedBy', 'drafts', 'orders']);
            event(new DiningSessionOpened($fresh ?? $session));

            return $fresh ?? $session;
        });
    }

    /**
     * @param  list<array{add_on_id: int, quantity: int}>  $addOns
     */
    public function addDraftItem(
        DiningSession $session,
        int $productVariantId,
        int $quantity,
        ?User $customer = null,
        array $addOns = [],
    ): DiningRoundDraft {
        $this->assertAllowsNewRounds($session);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be at least 1.',
            ]);
        }

        $variant = $this->assertOrderableVariant($productVariantId);
        $resolvedAddOns = $this->addOns->resolveSelectionForProduct($variant->product, $addOns);
        $configurationHash = AddOnConfiguration::hash((int) $variant->getKey(), $resolvedAddOns);

        return DB::transaction(function () use ($session, $variant, $quantity, $customer, $resolvedAddOns, $configurationHash): DiningRoundDraft {
            $draft = DiningRoundDraft::query()
                ->where('dining_session_id', $session->getKey())
                ->where('configuration_hash', $configurationHash)
                ->lockForUpdate()
                ->first();

            if ($draft === null && $resolvedAddOns === []) {
                $draft = DiningRoundDraft::query()
                    ->where('dining_session_id', $session->getKey())
                    ->where('product_variant_id', $variant->getKey())
                    ->where(function ($query) use ($configurationHash): void {
                        $query->whereNull('configuration_hash')
                            ->orWhere('configuration_hash', $configurationHash);
                    })
                    ->lockForUpdate()
                    ->first();

                if ($draft && $draft->configuration_hash === null) {
                    $draft->forceFill(['configuration_hash' => $configurationHash])->save();
                }
            }

            if ($draft) {
                $draft->update(['quantity' => $draft->quantity + $quantity]);
            } else {
                $draft = DiningRoundDraft::query()->create([
                    'dining_session_id' => $session->getKey(),
                    'customer_id' => $customer?->getKey() ?? $session->customer_id,
                    'product_variant_id' => $variant->getKey(),
                    'configuration_hash' => $configurationHash,
                    'quantity' => $quantity,
                ]);
                $this->syncDraftAddOns($draft, $resolvedAddOns);
            }

            return $draft->fresh(['productVariant.product', 'draftAddOns.addOn']);
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

        return $draft->fresh(['productVariant.product', 'draftAddOns.addOn']);
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
                ->with('draftAddOns')
                ->lockForUpdate()
                ->get();

            if ($drafts->isEmpty()) {
                throw ValidationException::withMessages([
                    'drafts' => 'Add at least one item before placing a round.',
                ]);
            }

            $items = $drafts
                ->map(static function (DiningRoundDraft $draft): array {
                    return [
                        'product_variant_id' => (int) $draft->product_variant_id,
                        'quantity' => (int) $draft->quantity,
                        'add_ons' => $draft->draftAddOns
                            ->map(static fn (DiningRoundDraftAddOn $row): array => [
                                'add_on_id' => (int) $row->add_on_id,
                                'quantity' => (int) $row->quantity,
                            ])
                            ->values()
                            ->all(),
                    ];
                })
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

        foreach ($session->orders as $order) {
            if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                continue;
            }

            // Merchandise only — dining promotions apply once at final bill.
            $subtotal = bcadd($subtotal, (string) $order->subtotal, 2);
            $rounds[] = [
                'order_id' => (int) $order->getKey(),
                'round_number' => (int) ($order->dining_round_number ?? 0),
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'total' => number_format((float) $order->total_amount, 2, '.', ''),
            ];
        }

        $discount = '0.00';
        $tax = $this->taxCalculator->calculateForTaxableAmount($subtotal);
        $total = $this->taxCalculator->payableTotal($tax);

        return [
            'subtotal' => number_format((float) $subtotal, 2, '.', ''),
            'discount' => $discount,
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

    /**
     * Prefer finalized session money snapshots after bill generation; otherwise live preview.
     *
     * @return array{
     *     subtotal: string,
     *     discount: string,
     *     taxable: string,
     *     tax: string,
     *     total: string,
     *     tax_enabled: bool,
     *     tax_label: ?string,
     *     tax_percent: ?string,
     *     tax_inclusive: bool,
     *     rounds: list<array{order_id: int, round_number: int, status: string, subtotal: string, total: string}>,
     *     finalized: bool
     * }
     */
    public function displayBill(DiningSession $session): array
    {
        if ($session->hasFinalizedBill()) {
            return $this->finalizedBill($session);
        }

        return [
            ...$this->runningBill($session),
            'finalized' => false,
        ];
    }

    /**
     * @return array{
     *     subtotal: string,
     *     discount: string,
     *     taxable: string,
     *     tax: string,
     *     total: string,
     *     tax_enabled: bool,
     *     tax_label: ?string,
     *     tax_percent: ?string,
     *     tax_inclusive: bool,
     *     rounds: list<array{order_id: int, round_number: int, status: string, subtotal: string, total: string}>,
     *     finalized: bool
     * }
     */
    public function finalizedBill(DiningSession $session): array
    {
        if (! $session->hasFinalizedBill()) {
            throw ValidationException::withMessages([
                'bill' => 'This dining session does not have a finalized bill yet.',
            ]);
        }

        $preview = $this->runningBill($session);

        return [
            'subtotal' => number_format((float) $session->subtotal_amount, 2, '.', ''),
            'discount' => number_format((float) ($session->discount_amount ?? 0), 2, '.', ''),
            'taxable' => number_format((float) ($session->taxable_amount ?? 0), 2, '.', ''),
            'tax' => number_format((float) ($session->tax_amount ?? 0), 2, '.', ''),
            'total' => number_format((float) $session->total_amount, 2, '.', ''),
            'tax_enabled' => (bool) $session->tax_enabled_snapshot,
            'tax_label' => $session->tax_enabled_snapshot ? ($session->tax_label_snapshot ?: null) : null,
            'tax_percent' => $session->tax_enabled_snapshot
                ? number_format((float) ($session->tax_percent_snapshot ?? 0), 2, '.', '')
                : null,
            'tax_inclusive' => (bool) $session->tax_inclusive_snapshot,
            'rounds' => $preview['rounds'],
            'finalized' => true,
            'payment_status' => $session->payment_status?->value,
            'payment_status_label' => $session->payment_status?->label(),
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

            if ($locked->hasFinalizedBill() && $locked->payment_status !== PaymentStatus::Confirmed) {
                return $locked->fresh(['cafeTable', 'customer', 'orders.items', 'drafts', 'promotions']);
            }

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
            $locked->promotions()->delete();

            $merchandise = $this->runningBill($locked);
            $promotionResult = $this->promotions->assertAndEvaluateForCheckout([
                'customer' => $locked->customer,
                'fulfilment' => OrderFulfilmentMethod::DineIn,
                'promo_code' => null,
                'items' => $this->aggregateItemsForPromotion($locked),
            ]);

            $promoDiscount = (string) ($promotionResult['discount_total'] ?? '0.00');
            $afterDiscount = bcsub($merchandise['subtotal'], $promoDiscount, 2);
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
                'subtotal_amount' => $merchandise['subtotal'],
                'discount_amount' => number_format((float) $promoDiscount, 2, '.', ''),
                'taxable_amount' => $tax->taxableAmount,
                'tax_amount' => $tax->taxAmount,
                'tax_enabled_snapshot' => $tax->enabled,
                'tax_label_snapshot' => $tax->enabled ? $tax->label : null,
                'tax_percent_snapshot' => $tax->enabled ? $tax->percent : null,
                'tax_inclusive_snapshot' => $tax->enabled ? $tax->inclusive : false,
                'total_amount' => $total,
                'payment_status' => PaymentStatus::Pending,
            ])->save();

            foreach (array_values($promotionResult['discounts']) as $index => $discount) {
                $locked->promotions()->create([
                    'promotion_id' => $discount['promotion_id'],
                    'name_snapshot' => $discount['name'],
                    'code_snapshot' => $discount['code'],
                    'discount_type_snapshot' => $discount['discount_type'],
                    'discount_value_snapshot' => $discount['discount_value'],
                    'discount_amount' => $discount['amount'],
                    'sort_order' => $index,
                ]);
            }

            $fresh = $locked->fresh(['cafeTable', 'customer', 'orders.items', 'drafts', 'promotions']);
            event(new DiningBillReady($fresh, $actor));

            return $fresh;
        });
    }

    public function setPaymentMethod(DiningSession $session, string $paymentMethodApiKey): DiningSession
    {
        return $this->changePaymentMethod($session, $paymentMethodApiKey, null);
    }

    public function changePaymentMethod(
        DiningSession $session,
        string $paymentMethodApiKey,
        ?User $actor = null,
    ): DiningSession {
        return DB::transaction(function () use ($session, $paymentMethodApiKey, $actor): DiningSession {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === PaymentStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Payment method cannot change after payment is confirmed.',
                ]);
            }

            if (! in_array($locked->status, [
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

            $previous = $locked->payment_method;
            $attributes = [
                'payment_method' => $method,
                'status' => DiningSessionStatus::AwaitingPayment,
                'payment_status' => $locked->payment_status === PaymentStatus::AwaitingReview
                    && $method === PaymentMethod::Manual
                    ? PaymentStatus::AwaitingReview
                    : PaymentStatus::Pending,
            ];

            if ($previous !== null && $previous !== $method) {
                $attributes['payment_method_previous'] = $previous;
                $attributes['payment_method_changed_at'] = now();
                $attributes['payment_method_changed_by_id'] = $actor?->getKey();
            }

            if ($method === PaymentMethod::Cash) {
                $locked->clearPaymentProofFiles();
                $attributes['payment_proof_path'] = null;
                $attributes['payment_proof_disk'] = null;
                $attributes['payment_proof_mime'] = null;
                $attributes['payment_proof_size'] = null;
                $attributes['payment_proof_uploaded_at'] = null;
                $attributes['payment_proof_rejection_notes'] = null;
                $attributes['payment_status'] = PaymentStatus::Pending;
            }

            $locked->fill($attributes)->save();

            $fresh = $locked->fresh(['cafeTable', 'customer', 'orders.items']) ?? $locked;
            $hint = 'method_'.$method->apiKey();

            DB::afterCommit(function () use ($fresh, $hint): void {
                app(DiningRealtimePublisher::class)->paymentChanged($fresh, $hint);
            });

            return $fresh;
        });
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

        $wasResubmission = $session->payment_status === PaymentStatus::Rejected
            || filled($session->payment_proof_rejection_notes);

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
            'payment_method' => $session->payment_method ?? PaymentMethod::Manual,
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
            $this->staffNotifications->notify(
                StaffNotificationType::PaymentProofReceived,
                'staff:dining_payment_proof:ops:'.$session->getKey().':'.now()->timestamp,
                StaffNotificationAudience::Operators,
                StaffNotificationContext::forOrder($relatedOrder),
                true,
            );
        }

        event(new DiningPaymentProofReceived($session->fresh(['customer']) ?? $session, $wasResubmission));

        return $session->fresh(['cafeTable', 'customer', 'orders.items']);
    }

    public function rejectPaymentProof(DiningSession $session, User $actor, ?string $notes = null): DiningSession
    {
        if (! $actor->canManageOrders() && ! $actor->canOperateOrders()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Only administrators or operators can reject dining payment proof.',
            ]);
        }

        return DB::transaction(function () use ($session, $notes): DiningSession {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_status === PaymentStatus::Confirmed) {
                throw ValidationException::withMessages([
                    'payment_proof' => 'Cannot reject proof after payment is confirmed.',
                ]);
            }

            if (! $locked->hasPaymentProof()) {
                throw ValidationException::withMessages([
                    'payment_proof' => 'This session does not have an uploaded payment proof.',
                ]);
            }

            $reason = filled($notes) ? trim($notes) : 'Please upload a clearer payment screenshot.';

            $locked->fill([
                'payment_status' => PaymentStatus::Rejected,
                'payment_proof_rejection_notes' => $reason,
                'status' => DiningSessionStatus::AwaitingPayment,
            ])->save();

            $fresh = $locked->fresh(['cafeTable', 'customer', 'orders.items']);

            event(new DiningPaymentProofRejected($fresh ?? $locked, $reason));

            return $fresh ?? $locked;
        });
    }

    public function confirmPayment(DiningSession $session, User $actor): DiningSession
    {
        if (! $actor->canManageOrders() && ! $actor->canOperateOrders()) {
            throw ValidationException::withMessages([
                'payment' => 'Only administrators or operators can confirm dining UPI payment.',
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

            if ($locked->payment_method === PaymentMethod::Manual && ! $locked->hasPaymentProof()) {
                throw ValidationException::withMessages([
                    'payment' => 'UPI payment confirmation requires an uploaded payment proof.',
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
        if (! $actor->canOperateDining() && ! $actor->canManageOrders() && ! $actor->canOperateOrders()) {
            throw ValidationException::withMessages([
                'payment' => 'You are not allowed to mark dining cash as received.',
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
                    'payment' => 'Mark cash received only after the bill is ready.',
                ]);
            }

            if ($locked->payment_method === PaymentMethod::Manual) {
                throw ValidationException::withMessages([
                    'payment' => 'This session is set to UPI. Change the payment method to cash before marking cash received.',
                ]);
            }

            if ($locked->payment_method === null) {
                $locked->fill([
                    'payment_method' => PaymentMethod::Cash,
                    'status' => DiningSessionStatus::AwaitingPayment,
                    'payment_status' => PaymentStatus::Pending,
                ])->save();
            }

            if ($locked->payment_method !== PaymentMethod::Cash) {
                throw ValidationException::withMessages([
                    'payment' => 'Only cash dining sessions can be marked as cash received.',
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

    public function closeSession(DiningSession $session, User $actor): DiningSession
    {
        if (! $actor->canOperateDining() && ! $actor->canManageOrders()) {
            throw ValidationException::withMessages([
                'session' => 'You are not allowed to close this dining session.',
            ]);
        }

        return DB::transaction(function () use ($session): DiningSession {
            $locked = DiningSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === DiningSessionStatus::Closed) {
                return $locked->fresh(['cafeTable', 'customer', 'orders.items']) ?? $locked;
            }

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

            $fresh = $locked->fresh(['cafeTable', 'customer', 'orders.items']);
            event(new DiningSessionClosed($fresh ?? $locked));

            return $fresh ?? $locked;
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
                'payment_method_previous' => null,
                'payment_method_changed_at' => null,
                'payment_method_changed_by_id' => null,
                'payment_status' => null,
                'payment_reference' => $note !== null && $note !== ''
                    ? Str::limit('Reopened: '.$note, 240)
                    : $locked->payment_reference,
            ])->save();

            $locked->promotions()->delete();

            $fresh = $locked->fresh(['cafeTable', 'customer', 'orders.items', 'drafts', 'promotions']);
            event(new DiningSessionReopened($fresh ?? $locked));

            return $fresh ?? $locked;
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

    /**
     * Waiter mobile dashboard enrichment — display_state is derived from
     * session status + preparation tickets; session status remains authoritative.
     *
     * @return Collection<int, array{
     *     table: CafeTable,
     *     state: string,
     *     display_state: string,
     *     display_state_label: string,
     *     session: ?DiningSession,
     *     session_summary: ?array<string, mixed>
     * }>
     */
    public function tableOperationalStatesForWaiter(): Collection
    {
        $tables = CafeTable::query()
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get();

        $activeSessions = DiningSession::query()
            ->with([
                'customer',
                'drafts',
                'orders.items',
                'orders.preparations',
            ])
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

            [$displayState, $displayLabel] = $this->waiterDisplayState($table, $session, $state);
            $bill = $session ? $this->displayBill($session) : null;

            return [
                'table' => $table,
                'state' => $state,
                'display_state' => $displayState,
                'display_state_label' => $displayLabel,
                'session' => $session,
                'session_summary' => $session ? [
                    'id' => $session->getKey(),
                    'session_number' => $session->session_number,
                    'status' => $session->status?->value,
                    'status_label' => $session->status?->label(),
                    'guest_count' => $session->guest_count,
                    'round_count' => $session->orders
                        ->reject(static fn ($order): bool => in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true))
                        ->count(),
                    'has_unsent_draft' => $session->drafts->isNotEmpty(),
                    'draft_item_count' => (int) $session->drafts->sum('quantity'),
                    'running_total' => $bill['total'] ?? null,
                    'ready_to_serve' => $displayState === 'ready_to_serve',
                    'is_preparing' => $displayState === 'preparing',
                    'station_summary' => $this->waiterStationSummary($session),
                ] : null,
            ];
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function waiterDisplayState(CafeTable $table, ?DiningSession $session, string $baseState): array
    {
        if ($baseState === 'inactive') {
            return ['inactive', 'Inactive'];
        }

        if ($session === null || $baseState === 'available') {
            return ['available', 'Available'];
        }

        return match ($session->status) {
            DiningSessionStatus::BillingRequested => ['bill_requested', 'Bill Requested'],
            DiningSessionStatus::AwaitingPayment => ['payment_pending', 'Payment Pending'],
            DiningSessionStatus::Paid => ['paid', 'Paid'],
            DiningSessionStatus::Open => $this->openSessionDisplayState($session),
            default => ['active', 'Active'],
        };
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function openSessionDisplayState(DiningSession $session): array
    {
        $activeOrders = $session->orders->reject(
            static fn ($order): bool => in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true),
        );

        $hasReady = false;
        $hasPreparing = false;

        foreach ($activeOrders as $order) {
            $tickets = $order->preparations->filter(
                static fn ($ticket): bool => $ticket->status !== OrderPreparationStatus::Cancelled,
            );

            if ($tickets->isEmpty()) {
                continue;
            }

            if ($tickets->every(static fn ($ticket): bool => $ticket->status === OrderPreparationStatus::Ready)) {
                $hasReady = true;

                continue;
            }

            if ($tickets->contains(static fn ($ticket): bool => in_array(
                $ticket->status,
                [OrderPreparationStatus::Accepted, OrderPreparationStatus::Preparing, OrderPreparationStatus::Pending],
                true,
            ))) {
                $hasPreparing = true;
            }
        }

        if ($hasReady) {
            return ['ready_to_serve', 'Ready to Serve'];
        }

        if ($hasPreparing) {
            return ['preparing', 'Preparing'];
        }

        return ['active', 'Active'];
    }

    /**
     * @return list<array{station: string, status: string, status_label: string}>
     */
    protected function waiterStationSummary(DiningSession $session): array
    {
        $latest = $session->orders
            ->reject(static fn ($order): bool => in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true))
            ->sortByDesc('dining_round_number')
            ->first();

        if (! $latest) {
            return [];
        }

        return $latest->preparations
            ->filter(static fn ($ticket): bool => $ticket->status !== OrderPreparationStatus::Cancelled)
            ->map(static fn ($ticket): array => [
                'station' => (string) $ticket->station?->value,
                'status' => (string) $ticket->status?->value,
                'status_label' => (string) $ticket->status?->label(),
            ])
            ->values()
            ->all();
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

    /**
     * @param  list<array{add_on_id: int, name: string, quantity: int, unit_price: string, line_total: string}>  $resolvedAddOns
     */
    protected function syncDraftAddOns(DiningRoundDraft $draft, array $resolvedAddOns): void
    {
        $draft->draftAddOns()->delete();

        foreach ($resolvedAddOns as $row) {
            DiningRoundDraftAddOn::query()->create([
                'dining_round_draft_id' => $draft->getKey(),
                'add_on_id' => $row['add_on_id'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
            ]);
        }
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
