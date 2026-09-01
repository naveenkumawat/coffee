<?php

namespace App\Services\Order;

use App\Enums\CustomerRewardStatus;
use App\Enums\CustomerRewardType;
use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\Order\OrderCashReceived;
use App\Events\Order\OrderPaymentProofReceived;
use App\Events\Order\OrderPaymentProofRejected;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusChanged;
use App\Models\CustomerReward;
use App\Models\DiningSession;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\CafeTable\CafeTableRepositoryInterface;
use App\Repositories\Order\OrderRepositoryInterface;
use App\Services\OrderPreparation\OrderPreparationServiceInterface;
use App\Services\OrderSecurity\OrderSecurityServiceInterface;
use App\Services\Payment\PaymentEligibilityServiceInterface;
use App\Services\Promotion\PromotionServiceInterface;
use App\Services\Referral\ReferralServiceInterface;
use App\Services\Tax\TaxCalculatorInterface;
use App\Services\WebsiteSetting\WebsiteSettingServiceInterface;
use App\Transfers\Order\OrderStatusTransitionTransferInterface;
use App\Transfers\Order\OrderTransferInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService implements OrderServiceInterface
{
    public function __construct(
        protected OrderRepositoryInterface $orders,
        protected CafeTableRepositoryInterface $cafeTables,
        protected WebsiteSettingServiceInterface $websiteSettings,
        protected TaxCalculatorInterface $taxCalculator,
        protected PaymentEligibilityServiceInterface $paymentEligibility,
        protected OrderSecurityServiceInterface $orderSecurity,
        protected PromotionServiceInterface $promotions,
        protected ReferralServiceInterface $referrals,
        protected OrderPreparationServiceInterface $preparations,
    ) {}

    public function store(User $actor, OrderTransferInterface $data): Order
    {
        return DB::transaction(function () use ($actor, $data): Order {
            $customerId = $data->getCustomerId();
            $customer = null;

            if ($customerId !== null) {
                $customer = $this->orders->findActiveCustomer($customerId);

                if (! $customer) {
                    throw ValidationException::withMessages([
                        'customer_id' => 'Only active customer accounts can be linked to an order.',
                    ]);
                }
            }

            $preparedItems = $this->prepareItems($data->getItems());

            if ($preparedItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'At least one order item is required.',
                ]);
            }

            $subtotal = $this->sumLineSubtotals($preparedItems);

            $fulfilmentMethod = OrderFulfilmentMethod::tryFrom((string) ($data->getFulfilmentMethod() ?: OrderFulfilmentMethod::Takeaway->value))
                ?? OrderFulfilmentMethod::Takeaway;

            $pricedItems = array_map(static fn (array $item): array => [
                'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                'product_variant_id' => isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                'product_category_id' => isset($item['product_category_id']) ? (int) $item['product_category_id'] : null,
                'quantity' => (int) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'line_subtotal' => (string) $item['line_subtotal'],
            ], $preparedItems);

            $freeDrinkBenefit = '0.00';
            $freeDrinkOriginal = '0.00';
            $referralCouponDiscount = '0.00';
            $freeDrinkResolved = null;
            $lockedFreeDrink = null;
            $lockedCoupon = null;
            $itemsForPromotions = $pricedItems;

            if ($customer instanceof User && $data->getReferralFreeDrinkRewardId() !== null && $data->getReferralCouponRewardId() !== null) {
                throw ValidationException::withMessages([
                    'reward_id' => 'Only one referral reward can be used per order.',
                ]);
            }

            if ($customer instanceof User && $data->getReferralFreeDrinkRewardId() !== null) {
                $lockedFreeDrink = CustomerReward::query()
                    ->whereKey($data->getReferralFreeDrinkRewardId())
                    ->where('user_id', $customer->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($lockedFreeDrink === null) {
                    throw ValidationException::withMessages([
                        'reward_id' => 'That reward is not available.',
                    ]);
                }

                $this->referrals->assertRewardUsable($lockedFreeDrink);
                $freeDrinkResolved = $this->referrals->resolveFreeDrinkBenefit($lockedFreeDrink, $pricedItems);

                if ($freeDrinkResolved === null) {
                    throw ValidationException::withMessages([
                        'reward_id' => 'Add the free drink item to your cart before applying this reward.',
                    ]);
                }

                $freeDrinkBenefit = $freeDrinkResolved['benefit'];
                $freeDrinkOriginal = $freeDrinkResolved['original_amount'];
                $itemsForPromotions = $this->reduceItemsByFreeDrink($pricedItems, $freeDrinkResolved);
            }

            if ($customer instanceof User && $data->getReferralCouponRewardId() !== null) {
                $lockedCoupon = CustomerReward::query()
                    ->whereKey($data->getReferralCouponRewardId())
                    ->where('user_id', $customer->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($lockedCoupon === null) {
                    throw ValidationException::withMessages([
                        'referral_coupon' => 'That referral reward code is not valid.',
                    ]);
                }

                $this->referrals->assertRewardUsable($lockedCoupon);
                $afterFreeDrink = bcsub($subtotal, $freeDrinkOriginal, 2);
                if (bccomp($afterFreeDrink, '0', 2) < 0) {
                    $afterFreeDrink = '0.00';
                }
                $referralCouponDiscount = $this->referrals->resolveCouponBenefit($lockedCoupon, $afterFreeDrink);

                if (bccomp($referralCouponDiscount, '0', 2) <= 0) {
                    throw ValidationException::withMessages([
                        'referral_coupon' => 'Your cart does not meet the minimum for this reward.',
                    ]);
                }
            }

            $promotionResult = $this->promotions->assertAndEvaluateForCheckout([
                'customer' => $customer,
                'fulfilment' => $fulfilmentMethod,
                'promo_code' => $data->getPromoCode(),
                'items' => $itemsForPromotions,
            ]);

            $promoDiscount = $promotionResult['discount_total'];
            // discount_total includes promo + referral coupon; free drink benefit is separate (does not reduce GST basis)
            $discountTotal = bcadd($promoDiscount, $referralCouponDiscount, 2);

            $gstBasis = bcsub($subtotal, $discountTotal, 2);
            if (bccomp($gstBasis, '0', 2) < 0) {
                $gstBasis = '0.00';
            }

            $payable = bcsub($gstBasis, $freeDrinkBenefit, 2);
            if (bccomp($payable, '0', 2) < 0) {
                $payable = '0.00';
            }

            $tax = $this->taxCalculator->calculateForPayableAndGstBasis($payable, $gstBasis);
            $deliveryFeeAmount = null;
            $totalAmount = $this->taxCalculator->payableTotal($tax, $deliveryFeeAmount);
            $placedAt = now();
            $dailySequence = $this->orders->nextDailySequenceForDate(Carbon::instance($placedAt));

            $paymentMethod = PaymentMethod::Manual;
            if ($customer instanceof User) {
                $paymentMethod = $this->paymentEligibility->assertAllowed(
                    $customer,
                    $fulfilmentMethod,
                    $data->getPaymentMethod() ?: PaymentMethod::Manual->apiKey(),
                );
            }

            $cafeTableId = null;
            $tableNameSnapshot = null;

            if ($fulfilmentMethod === OrderFulfilmentMethod::DineIn) {
                if (! $this->websiteSettings->dineInEnabled()) {
                    throw ValidationException::withMessages([
                        'fulfilment_method' => 'Dine-in ordering is not available.',
                    ]);
                }

                $tableId = $data->getCafeTableId();
                $table = $tableId !== null ? $this->cafeTables->findActiveById($tableId) : null;

                if ($table === null) {
                    throw ValidationException::withMessages([
                        'cafe_table_id' => 'Select a valid active table for dine-in.',
                    ]);
                }

                $cafeTableId = (int) $table->getKey();
                $tableNameSnapshot = $table->snapshotLabel();
            }

            $order = $this->orders->create([
                'order_number' => $this->formatOrderNumber(Carbon::instance($placedAt), $dailySequence),
                'order_date' => $placedAt->toDateString(),
                'daily_sequence' => $dailySequence,
                'customer_id' => $customerId,
                'customer_name' => $data->getCustomerName() ?: $customer?->name,
                'customer_email' => $data->getCustomerEmail() ?: $customer?->email,
                'customer_phone' => $data->getCustomerPhone() ?: $customer?->phone,
                'pickup_name' => $fulfilmentMethod === OrderFulfilmentMethod::Takeaway
                    ? ($data->getPickupName() ?: $data->getCustomerName() ?: $customer?->name)
                    : null,
                'pickup_phone' => $fulfilmentMethod === OrderFulfilmentMethod::Takeaway
                    ? ($data->getPickupPhone() ?: $data->getCustomerPhone() ?: $customer?->phone)
                    : null,
                'assigned_barista_id' => null,
                'checkout_token' => $data->getCheckoutToken(),
                'status' => OrderStatus::PendingPayment->value,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_enabled_snapshot' => $tax->enabled,
                'tax_label_snapshot' => $tax->enabled ? $tax->label : null,
                'tax_percent_snapshot' => $tax->enabled ? $tax->percent : null,
                'tax_inclusive_snapshot' => $tax->enabled ? $tax->inclusive : false,
                'taxable_amount' => $tax->taxableAmount,
                'tax_amount' => $tax->taxAmount,
                'total_amount' => $totalAmount,
                'customer_notes' => $data->getCustomerNotes(),
                'pickup_notes' => $fulfilmentMethod === OrderFulfilmentMethod::Takeaway ? $data->getPickupNotes() : null,
                'fulfilment_method' => $fulfilmentMethod->value,
                'cafe_table_id' => $cafeTableId,
                'table_name_snapshot' => $tableNameSnapshot,
                'delivery_address' => $fulfilmentMethod === OrderFulfilmentMethod::Delivery ? $data->getDeliveryAddress() : null,
                'delivery_phone' => $fulfilmentMethod === OrderFulfilmentMethod::Delivery ? $data->getDeliveryPhone() : null,
                'delivery_contact_name' => $fulfilmentMethod === OrderFulfilmentMethod::Delivery ? $data->getDeliveryContactName() : null,
                'delivery_notes' => $fulfilmentMethod === OrderFulfilmentMethod::Delivery
                    ? ($data->getDeliveryNotes() ?: $data->getPickupNotes())
                    : null,
                'delivery_provider' => null,
                'delivery_fee_amount' => null,
                'delivery_tracking_reference' => null,
                'payment_method' => $paymentMethod->value,
                'payment_status' => PaymentStatus::Pending->value,
                'placed_at' => $placedAt,
            ]);

            $this->orders->createItems($order, array_map(static fn (array $item): array => [
                'product_id' => $item['product_id'] ?? null,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'recipe_id' => $item['recipe_id'] ?? null,
                'preparation_station' => $item['preparation_station'] ?? null,
                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'],
                'customer_ingredient_summary' => $item['customer_ingredient_summary'] ?? null,
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_subtotal' => $item['line_subtotal'],
            ], $preparedItems));
            $this->persistOrderPromotions($order, $promotionResult['discounts']);
            $this->persistAndRedeemRewards(
                $order,
                $lockedFreeDrink,
                $freeDrinkResolved,
                $lockedCoupon,
                $referralCouponDiscount,
            );
            $this->orders->createStatusHistory($order, [
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment->value,
                'changed_by' => $actor->getKey(),
                'notes' => 'Order created.',
            ]);

            $order = $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'promotions',
                'rewardRedemptions',
                'statusHistory.changedBy',
            ]);

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    public function transition(Order $order, User $actor, OrderStatusTransitionTransferInterface $data): Order
    {
        return DB::transaction(function () use ($order, $actor, $data): Order {
            $currentStatus = $order->status;
            $nextStatus = OrderStatus::from((string) $data->getStatus());

            if (! $currentStatus instanceof OrderStatus) {
                throw ValidationException::withMessages([
                    'status' => 'The current order status is invalid.',
                ]);
            }

            if ($currentStatus === $nextStatus) {
                throw ValidationException::withMessages([
                    'status' => 'The order is already in the selected status.',
                ]);
            }

            $allowedStatuses = $this->availableTransitions($order, $actor);

            if (! array_key_exists($nextStatus->value, $allowedStatuses)) {
                throw ValidationException::withMessages([
                    'status' => 'The selected status transition is not allowed.',
                ]);
            }

            $attributes = ['status' => $nextStatus->value];

            match ($nextStatus) {
                OrderStatus::PaymentConfirmed => $attributes['payment_confirmed_at'] = $order->payment_confirmed_at ?: now(),
                OrderStatus::Accepted => $attributes['accepted_at'] = $order->accepted_at ?: now(),
                OrderStatus::Preparing => $attributes['preparing_at'] = $order->preparing_at ?: now(),
                OrderStatus::ReadyForPickup => $attributes['ready_for_pickup_at'] = $order->ready_for_pickup_at ?: now(),
                OrderStatus::Completed => $attributes['completed_at'] = $order->completed_at ?: now(),
                OrderStatus::Cancelled => $attributes['cancelled_at'] = $order->cancelled_at ?: now(),
                OrderStatus::Rejected => $attributes['rejected_at'] = $order->rejected_at ?: now(),
                default => null,
            };

            if ($nextStatus === OrderStatus::PaymentConfirmed) {
                $attributes['payment_status'] = PaymentStatus::Confirmed->value;
                $attributes['payment_proof_rejection_notes'] = null;
                $attributes['payment_confirmed_at'] = $order->payment_confirmed_at ?: now();

                if ($order->isCashPayment() && $order->payment_received_by_id === null) {
                    $attributes['payment_received_by_id'] = $actor->getKey();
                }
            }

            if (
                $actor->hasRole(UserRole::Barista)
                && in_array($nextStatus, [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::Completed], true)
                && ! $order->assigned_barista_id
            ) {
                $attributes['assigned_barista_id'] = $actor->getKey();
            }

            $order = $this->orders->update($order, $attributes);
            $this->orders->createStatusHistory($order, [
                'from_status' => $currentStatus->value,
                'to_status' => $nextStatus->value,
                'changed_by' => $actor->getKey(),
                'notes' => $data->getNotes(),
            ]);

            if ($nextStatus === OrderStatus::Accepted) {
                $this->preparations->createTicketsForOrder($order->fresh(['items', 'preparations']));
            }

            if (in_array($nextStatus, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
                $this->preparations->cancelTicketsForOrder($order->fresh(['preparations']), $actor);
            }

            $order = $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'statusHistory.changedBy',
                'preparations',
            ]);

            OrderStatusChanged::dispatch(
                $order,
                $currentStatus,
                $nextStatus,
                $data->getNotes(),
            );

            return $order;
        });
    }

    public function uploadPaymentProof(Order $order, User $customer, UploadedFile $file): Order
    {
        if ((int) $order->customer_id !== (int) $customer->getKey()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'You can only upload payment proof for your own orders.',
            ]);
        }

        if ($order->isCashPayment()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Cash orders do not use payment screenshots.',
            ]);
        }

        if (! $order->canUploadPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Payment proof can only be uploaded while the order is awaiting payment confirmation.',
            ]);
        }

        $this->orderSecurity->assertPaymentProofUploadAllowed($customer, $order);

        $isResubmission = $order->hasPaymentProof()
            || $order->payment_status === PaymentStatus::Rejected;

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $safeExtension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? $extension : 'jpg';
        $filename = Str::uuid()->toString().'.'.$safeExtension;
        $directory = 'payment-proofs/'.$order->getKey();
        $path = $file->storeAs($directory, $filename, 'local');

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'payment_proof' => 'Unable to store the payment proof. Please try again.',
            ]);
        }

        $order->clearPaymentProofFiles();

        $order = $this->orders->update($order, [
            'payment_proof_path' => $path,
            'payment_proof_disk' => 'local',
            'payment_proof_mime' => $file->getMimeType(),
            'payment_proof_size' => $file->getSize(),
            'payment_proof_uploaded_at' => now(),
            'payment_status' => PaymentStatus::AwaitingReview->value,
            'payment_proof_rejection_notes' => null,
        ])->fresh([
            'customer',
            'items',
            'statusHistory.changedBy',
        ]);

        OrderPaymentProofReceived::dispatch($order, $isResubmission);

        return $order;
    }

    public function rejectPaymentProof(Order $order, User $actor, ?string $notes = null): Order
    {
        if (! $actor->canManageOrders()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Only administrators can request a payment proof replacement.',
            ]);
        }

        if ($order->status !== OrderStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Payment proof can only be rejected while the order is pending payment.',
            ]);
        }

        if (! $order->hasPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'This order does not have an uploaded payment proof.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $notes): Order {
            $customerFacingReason = filled($notes)
                ? trim($notes)
                : 'Please upload a clearer payment screenshot.';

            $order = $this->orders->update($order, [
                'payment_status' => PaymentStatus::Rejected->value,
                'payment_proof_rejection_notes' => $customerFacingReason,
            ]);

            $this->orders->createStatusHistory($order, [
                'from_status' => OrderStatus::PendingPayment->value,
                'to_status' => OrderStatus::PendingPayment->value,
                'changed_by' => $actor->getKey(),
                'notes' => 'Payment proof replacement requested.'.(filled($notes) ? ' '.$notes : ''),
            ]);

            $order = $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'statusHistory.changedBy',
            ]);

            OrderPaymentProofRejected::dispatch($order, $customerFacingReason);

            return $order;
        });
    }

    public function markCashReceived(Order $order, User $actor): Order
    {
        if (! $actor->canManageOrders() && ! $actor->canOperateOrders()) {
            throw ValidationException::withMessages([
                'payment' => 'You are not allowed to mark cash as received.',
            ]);
        }

        if (! $order->isCashPayment()) {
            throw ValidationException::withMessages([
                'payment' => 'Only cash orders can be marked as cash received.',
            ]);
        }

        if ($order->payment_status === PaymentStatus::Confirmed) {
            throw ValidationException::withMessages([
                'payment' => 'Cash has already been marked as received for this order.',
            ]);
        }

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true)) {
            throw ValidationException::withMessages([
                'payment' => 'Cash cannot be marked received on a cancelled or rejected order.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor): Order {
            $currentStatus = $order->status;
            $attributes = [
                'payment_status' => PaymentStatus::Confirmed->value,
                'payment_confirmed_at' => $order->payment_confirmed_at ?: now(),
                'payment_received_by_id' => $actor->getKey(),
            ];

            if ($currentStatus === OrderStatus::PendingPayment) {
                $attributes['status'] = OrderStatus::PaymentConfirmed->value;
            }

            $order = $this->orders->update($order, $attributes);

            if ($currentStatus === OrderStatus::PendingPayment) {
                $this->orders->createStatusHistory($order, [
                    'from_status' => OrderStatus::PendingPayment->value,
                    'to_status' => OrderStatus::PaymentConfirmed->value,
                    'changed_by' => $actor->getKey(),
                    'notes' => 'Cash received.',
                ]);
            }

            $order = $order->fresh([
                'customer',
                'assignedBarista',
                'paymentReceivedBy',
                'items.recipe.lines.ingredient.brand',
                'statusHistory.changedBy',
            ]);

            if ($currentStatus === OrderStatus::PendingPayment) {
                OrderStatusChanged::dispatch(
                    $order,
                    OrderStatus::PendingPayment,
                    OrderStatus::PaymentConfirmed,
                    'Cash received.',
                );
            } else {
                OrderCashReceived::dispatch($order, $actor);
            }

            return $order;
        });
    }

    public function availableTransitions(Order $order, User $actor): array
    {
        $currentStatus = $order->status;

        if (! $currentStatus instanceof OrderStatus || $currentStatus->isTerminal()) {
            return [];
        }

        $isCash = $order->isCashPayment();

        $workflow = match ($currentStatus) {
            OrderStatus::PendingPayment => $isCash
                ? [OrderStatus::PaymentConfirmed, OrderStatus::Accepted, OrderStatus::Cancelled, OrderStatus::Rejected]
                : [OrderStatus::PaymentConfirmed, OrderStatus::Cancelled, OrderStatus::Rejected],
            OrderStatus::PaymentConfirmed => [OrderStatus::Accepted, OrderStatus::Cancelled, OrderStatus::Rejected],
            OrderStatus::Accepted => [OrderStatus::Preparing, OrderStatus::Cancelled],
            OrderStatus::Preparing => [OrderStatus::ReadyForPickup, OrderStatus::Cancelled],
            OrderStatus::ReadyForPickup => [OrderStatus::Completed],
            OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Rejected => [],
        };

        if ($actor->canManageOrders()) {
            return collect($workflow)->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])->all();
        }

        if (! $actor->canOperateOrders()) {
            return [];
        }

        $operatorAllowed = array_filter(
            $workflow,
            fn (OrderStatus $status): bool => in_array(
                $status,
                [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::Completed],
                true,
            ),
        );

        return collect($operatorAllowed)->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])->all();
    }

    /**
     * @param  list<array{product_variant_id: int, quantity: int}>  $items
     */
    public function placeDiningRound(User $actor, DiningSession $session, array $items, ?string $customerNotes = null): Order
    {
        return DB::transaction(function () use ($actor, $session, $items, $customerNotes): Order {
            $preparedItems = $this->prepareItems($items);

            if ($preparedItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'At least one order item is required.',
                ]);
            }

            $subtotal = $this->sumLineSubtotals($preparedItems);
            $pricedItems = array_map(static fn (array $item): array => [
                'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                'product_variant_id' => isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null,
                'product_category_id' => isset($item['product_category_id']) ? (int) $item['product_category_id'] : null,
                'quantity' => (int) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'line_subtotal' => (string) $item['line_subtotal'],
            ], $preparedItems);

            $customer = $session->customer;
            $promotionResult = $this->promotions->assertAndEvaluateForCheckout([
                'customer' => $customer,
                'fulfilment' => OrderFulfilmentMethod::DineIn,
                'promo_code' => null,
                'items' => $pricedItems,
            ]);

            $discountTotal = (string) $promotionResult['discount_total'];
            $gstBasis = bcsub($subtotal, $discountTotal, 2);
            if (bccomp($gstBasis, '0', 2) < 0) {
                $gstBasis = '0.00';
            }

            $tax = $this->taxCalculator->calculateForPayableAndGstBasis($gstBasis, $gstBasis);
            $totalAmount = $this->taxCalculator->payableTotal($tax);
            $placedAt = now();
            $dailySequence = $this->orders->nextDailySequenceForDate(Carbon::instance($placedAt));
            $roundNumber = ((int) $session->orders()->max('dining_round_number')) + 1;

            $order = $this->orders->create([
                'order_number' => $this->formatOrderNumber(Carbon::instance($placedAt), $dailySequence),
                'order_date' => $placedAt->toDateString(),
                'daily_sequence' => $dailySequence,
                'customer_id' => $session->customer_id,
                'customer_name' => $session->customer_name_snapshot ?: $customer?->name,
                'customer_email' => $customer?->email,
                'customer_phone' => $session->customer_phone_snapshot ?: $customer?->phone,
                'pickup_name' => null,
                'pickup_phone' => null,
                'assigned_barista_id' => null,
                'checkout_token' => null,
                'status' => OrderStatus::Accepted->value,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_enabled_snapshot' => $tax->enabled,
                'tax_label_snapshot' => $tax->enabled ? $tax->label : null,
                'tax_percent_snapshot' => $tax->enabled ? $tax->percent : null,
                'tax_inclusive_snapshot' => $tax->enabled ? $tax->inclusive : false,
                'taxable_amount' => $tax->taxableAmount,
                'tax_amount' => $tax->taxAmount,
                'total_amount' => $totalAmount,
                'customer_notes' => $customerNotes,
                'pickup_notes' => null,
                'fulfilment_method' => OrderFulfilmentMethod::DineIn->value,
                'cafe_table_id' => $session->cafe_table_id,
                'dining_session_id' => $session->getKey(),
                'dining_round_number' => $roundNumber,
                'table_name_snapshot' => $session->table_name_snapshot,
                'delivery_address' => null,
                'delivery_phone' => null,
                'delivery_contact_name' => null,
                'delivery_notes' => null,
                'delivery_provider' => null,
                'delivery_fee_amount' => null,
                'delivery_tracking_reference' => null,
                'payment_method' => PaymentMethod::Manual->value,
                'payment_status' => PaymentStatus::Pending->value,
                'placed_at' => $placedAt,
                'accepted_at' => $placedAt,
            ]);

            $this->orders->createItems($order, array_map(static fn (array $item): array => [
                'product_id' => $item['product_id'] ?? null,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'recipe_id' => $item['recipe_id'] ?? null,
                'preparation_station' => $item['preparation_station'] ?? null,
                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'],
                'customer_ingredient_summary' => $item['customer_ingredient_summary'] ?? null,
                'unit_price' => $item['unit_price'],
                'quantity' => $item['quantity'],
                'line_subtotal' => $item['line_subtotal'],
            ], $preparedItems));
            $this->persistOrderPromotions($order, $promotionResult['discounts']);
            $this->orders->createStatusHistory($order, [
                'from_status' => null,
                'to_status' => OrderStatus::Accepted->value,
                'changed_by' => $actor->getKey(),
                'notes' => 'Dining round '.$roundNumber.' placed — kitchen can start without session payment.',
            ]);

            $order = $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'promotions',
                'rewardRedemptions',
                'statusHistory.changedBy',
                'diningSession',
                'preparations',
            ]);

            $this->preparations->createTicketsForOrder($order);

            $order = $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'promotions',
                'rewardRedemptions',
                'statusHistory.changedBy',
                'diningSession',
                'preparations',
            ]);

            OrderPlaced::dispatch($order);

            return $order;
        });
    }

    protected function prepareItems(array $items): array
    {
        $prepared = [];
        $variantIds = [];

        foreach ($items as $index => $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }

            if (in_array($variantId, $variantIds, true)) {
                throw ValidationException::withMessages([
                    "items.$index.product_variant_id" => 'Duplicate product variants are not allowed in the same order.',
                ]);
            }

            $variant = $this->validateVariant($variantId, $index);
            $variantIds[] = $variantId;

            $product = $variant->product;
            $station = $product?->preparation_station;

            if ($product?->is_active && $station === null) {
                throw ValidationException::withMessages([
                    "items.$index.product_variant_id" => 'This product must have a preparation station before it can be ordered.',
                ]);
            }

            $unitPrice = $this->normalizeMoney((string) $variant->price);
            $lineSubtotal = bcmul($unitPrice, (string) $quantity, 2);

            $prepared[] = [
                'product_id' => $product?->getKey(),
                'product_category_id' => $product?->product_category_id,
                'product_variant_id' => $variant->getKey(),
                'recipe_id' => $variant->recipe?->getKey(),
                'preparation_station' => $station?->value,
                'product_name' => $product?->name ?? 'Product',
                'variant_name' => $variant->name,
                'customer_ingredient_summary' => $product?->customer_ingredient_summary,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        return $prepared;
    }

    /**
     * @param  list<array{promotion_id: int, name: string, code: ?string, discount_type: string, discount_value: string, amount: string}>  $discounts
     */
    protected function persistOrderPromotions(Order $order, array $discounts): void
    {
        foreach (array_values($discounts) as $index => $discount) {
            $order->promotions()->create([
                'promotion_id' => $discount['promotion_id'],
                'name_snapshot' => $discount['name'],
                'code_snapshot' => $discount['code'],
                'discount_type_snapshot' => $discount['discount_type'],
                'discount_value_snapshot' => $discount['discount_value'],
                'discount_amount' => $discount['amount'],
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array{benefit: string, original_amount: string, preserved_taxable: string, product_id: ?int, variant_id: ?int, quantity: int}|null  $freeDrinkResolved
     */
    protected function persistAndRedeemRewards(
        Order $order,
        ?CustomerReward $freeDrink,
        ?array $freeDrinkResolved,
        ?CustomerReward $coupon,
        string $couponDiscount,
    ): void {
        if ($freeDrink !== null && $freeDrinkResolved !== null) {
            $order->rewardRedemptions()->create([
                'customer_reward_id' => $freeDrink->getKey(),
                'reward_type' => CustomerRewardType::FreeDrink,
                'source_referral_id' => $freeDrink->source_referral_id,
                'description_snapshot' => $freeDrink->displayTitle(),
                'benefit_amount' => $freeDrinkResolved['benefit'],
                'original_amount' => $freeDrinkResolved['original_amount'],
                'preserved_taxable_amount' => $freeDrinkResolved['preserved_taxable'],
                'product_id' => $freeDrink->product_id,
                'variant_id' => $freeDrink->variant_id,
                'product_name_snapshot' => $freeDrink->product_name_snapshot,
                'variant_name_snapshot' => $freeDrink->variant_name_snapshot,
                'quantity' => $freeDrinkResolved['quantity'],
            ]);

            $freeDrink->forceFill([
                'status' => CustomerRewardStatus::Redeemed,
                'redeemed_order_id' => $order->getKey(),
                'redeemed_at' => now(),
            ])->save();
        }

        if ($coupon !== null && bccomp($couponDiscount, '0', 2) > 0) {
            $order->rewardRedemptions()->create([
                'customer_reward_id' => $coupon->getKey(),
                'reward_type' => CustomerRewardType::Coupon,
                'source_referral_id' => $coupon->source_referral_id,
                'description_snapshot' => $coupon->displayTitle(),
                'benefit_amount' => $couponDiscount,
                'original_amount' => $couponDiscount,
                'preserved_taxable_amount' => null,
                'coupon_code_snapshot' => $coupon->coupon_code,
                'discount_type_snapshot' => $coupon->discount_type,
                'discount_value_snapshot' => $coupon->discount_value,
            ]);

            $coupon->forceFill([
                'status' => CustomerRewardStatus::Redeemed,
                'redeemed_order_id' => $order->getKey(),
                'redeemed_at' => now(),
            ])->save();
        }
    }

    /**
     * @param  list<array{product_id: ?int, product_variant_id?: ?int, product_category_id?: ?int, quantity: int, unit_price: string, line_subtotal: string}>  $items
     * @param  array{original_amount: string, product_id: ?int, variant_id: ?int}  $freeDrink
     * @return list<array{product_id: ?int, product_variant_id?: ?int, product_category_id?: ?int, quantity: int, unit_price: string, line_subtotal: string}>
     */
    protected function reduceItemsByFreeDrink(array $items, array $freeDrink): array
    {
        $remaining = $freeDrink['original_amount'];
        $productId = $freeDrink['product_id'];
        $variantId = $freeDrink['variant_id'];
        $adjusted = [];

        foreach ($items as $item) {
            $itemProductId = isset($item['product_id']) ? (int) $item['product_id'] : null;
            $itemVariantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
            $lineSubtotal = (string) $item['line_subtotal'];

            $matches = ($productId === null || $itemProductId === $productId)
                && ($variantId === null || $itemVariantId === $variantId);

            if ($matches && bccomp($remaining, '0', 2) > 0) {
                $take = bccomp($lineSubtotal, $remaining, 2) <= 0 ? $lineSubtotal : $remaining;
                $lineSubtotal = bcsub($lineSubtotal, $take, 2);
                $remaining = bcsub($remaining, $take, 2);
            }

            if (bccomp($lineSubtotal, '0', 2) <= 0) {
                continue;
            }

            $adjusted[] = [
                ...$item,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        return $adjusted;
    }

    protected function validateVariant(int $variantId, int $index): ProductVariant
    {
        $variant = $this->orders->findOrderableVariant($variantId);

        if (
            ! $variant
            || ! $variant->is_active
            || ! $variant->is_available
            || ! $variant->product
            || ! $variant->product->is_active
            || ! $variant->product->is_available
        ) {
            throw ValidationException::withMessages([
                "items.$index.product_variant_id" => 'Only active and available product variants can be ordered.',
            ]);
        }

        return $variant;
    }

    protected function sumLineSubtotals(array $items): string
    {
        return collect($items)->reduce(
            fn (string $carry, array $item): string => bcadd($carry, (string) $item['line_subtotal'], 2),
            '0.00',
        );
    }

    protected function normalizeMoney(string $value): string
    {
        return bcdiv($value, '1', 2);
    }

    protected function formatOrderNumber(Carbon $placedAt, int $dailySequence): string
    {
        return sprintf('CC-%s-%s', $placedAt->format('dmy'), str_pad((string) $dailySequence, 4, '0', STR_PAD_LEFT));
    }
}
