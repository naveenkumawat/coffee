<?php

namespace App\Services\Order;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\Order\OrderPaymentProofReceived;
use App\Events\Order\OrderPaymentProofRejected;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusChanged;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Repositories\CafeTable\CafeTableRepositoryInterface;
use App\Repositories\Order\OrderRepositoryInterface;
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
            $placedAt = now();
            $dailySequence = $this->orders->nextDailySequenceForDate(Carbon::instance($placedAt));

            $fulfilmentMethod = OrderFulfilmentMethod::tryFrom((string) ($data->getFulfilmentMethod() ?: OrderFulfilmentMethod::Takeaway->value))
                ?? OrderFulfilmentMethod::Takeaway;

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
                'discount_total' => '0.00',
                'total_amount' => $subtotal,
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
                'payment_method' => PaymentMethod::Manual->value,
                'payment_status' => PaymentStatus::Pending->value,
                'placed_at' => $placedAt,
            ]);

            $this->orders->createItems($order, $preparedItems);
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

            $order = $order->fresh([
                'customer',
                'assignedBarista',
                'items.recipe.lines.ingredient.brand',
                'statusHistory.changedBy',
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

        if (! $order->canUploadPaymentProof()) {
            throw ValidationException::withMessages([
                'payment_proof' => 'Payment proof can only be uploaded while the order is awaiting payment confirmation.',
            ]);
        }

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

    public function availableTransitions(Order $order, User $actor): array
    {
        $currentStatus = $order->status;

        if (! $currentStatus instanceof OrderStatus || $currentStatus->isTerminal()) {
            return [];
        }

        $workflow = match ($currentStatus) {
            OrderStatus::PendingPayment => [OrderStatus::PaymentConfirmed, OrderStatus::Cancelled, OrderStatus::Rejected],
            OrderStatus::PaymentConfirmed => [OrderStatus::Accepted, OrderStatus::Cancelled, OrderStatus::Rejected],
            OrderStatus::Accepted => [OrderStatus::Preparing, OrderStatus::Cancelled],
            OrderStatus::Preparing => [OrderStatus::ReadyForPickup, OrderStatus::Cancelled],
            OrderStatus::ReadyForPickup => [OrderStatus::Completed],
            OrderStatus::Completed, OrderStatus::Cancelled, OrderStatus::Rejected => [],
        };

        if ($actor->canManageOrders()) {
            return collect($workflow)->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])->all();
        }

        if (! $actor->hasRole(UserRole::Barista)) {
            return [];
        }

        $baristaAllowed = array_filter(
            $workflow,
            fn (OrderStatus $status): bool => in_array(
                $status,
                [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::ReadyForPickup, OrderStatus::Completed],
                true,
            ),
        );

        return collect($baristaAllowed)->mapWithKeys(fn (OrderStatus $status): array => [$status->value => $status->label()])->all();
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

            $unitPrice = $this->normalizeMoney((string) $variant->price);
            $lineSubtotal = bcmul($unitPrice, (string) $quantity, 2);

            $prepared[] = [
                'product_id' => $variant->product?->getKey(),
                'product_variant_id' => $variant->getKey(),
                'recipe_id' => $variant->recipe?->getKey(),
                'product_name' => $variant->product?->name ?? 'Product',
                'variant_name' => $variant->name,
                'customer_ingredient_summary' => $variant->product?->customer_ingredient_summary,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_subtotal' => $lineSubtotal,
            ];
        }

        return $prepared;
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
