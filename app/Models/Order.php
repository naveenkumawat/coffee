<?php

namespace App\Models;

use App\Enums\OrderFulfilmentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Order extends AbstractModel
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_date',
        'daily_sequence',
        'customer_id',
        'placed_by_user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'pickup_name',
        'pickup_phone',
        'assigned_barista_id',
        'checkout_token',
        'status',
        'subtotal',
        'discount_total',
        'loyalty_reward_id',
        'loyalty_reward_name_snapshot',
        'loyalty_reward_type_snapshot',
        'loyalty_reward_points_cost_snapshot',
        'loyalty_discount_amount',
        'loyalty_reward_description_snapshot',
        'loyalty_reward_snapshot',
        'tax_enabled_snapshot',
        'tax_label_snapshot',
        'tax_percent_snapshot',
        'tax_inclusive_snapshot',
        'taxable_amount',
        'tax_amount',
        'total_amount',
        'customer_notes',
        'pickup_notes',
        'fulfilment_method',
        'cafe_table_id',
        'dining_session_id',
        'dining_round_number',
        'table_name_snapshot',
        'delivery_address',
        'delivery_phone',
        'delivery_contact_name',
        'delivery_notes',
        'delivery_provider',
        'delivery_fee_amount',
        'delivery_tracking_reference',
        'delivery_status',
        'payment_method',
        'payment_status',
        'payment_reference',
        'payment_transaction_id',
        'payment_proof_path',
        'payment_proof_disk',
        'payment_proof_mime',
        'payment_proof_size',
        'payment_proof_uploaded_at',
        'payment_proof_rejection_notes',
        'placed_at',
        'payment_expires_at',
        'payment_confirmed_at',
        'payment_received_by_id',
        'accepted_at',
        'preparing_at',
        'ready_for_pickup_at',
        'served_at',
        'served_by_user_id',
        'completed_at',
        'cancelled_at',
        'cancellation_source',
        'cancellation_reason',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'fulfilment_method' => OrderFulfilmentMethod::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'order_date' => 'date',
            'daily_sequence' => 'integer',
            'dining_round_number' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'loyalty_reward_points_cost_snapshot' => 'integer',
            'loyalty_discount_amount' => 'decimal:2',
            'loyalty_reward_snapshot' => 'array',
            'tax_enabled_snapshot' => 'boolean',
            'tax_percent_snapshot' => 'decimal:2',
            'tax_inclusive_snapshot' => 'boolean',
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'delivery_fee_amount' => 'decimal:2',
            'payment_proof_size' => 'integer',
            'placed_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_for_pickup_at' => 'datetime',
            'served_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by_user_id')->withTrashed();
    }

    public function assignedBarista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_barista_id')->withTrashed();
    }

    public function paymentReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_received_by_id')->withTrashed();
    }

    public function servedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'served_by_user_id')->withTrashed();
    }

    public function cafeTable(): BelongsTo
    {
        return $this->belongsTo(CafeTable::class)->withTrashed();
    }

    public function diningSession(): BelongsTo
    {
        return $this->belongsTo(DiningSession::class);
    }

    public function isDiningRound(): bool
    {
        return $this->dining_session_id !== null;
    }

    public function isServed(): bool
    {
        return $this->served_at !== null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function preparations(): HasMany
    {
        return $this->hasMany(OrderPreparation::class)->orderBy('station')->orderBy('id');
    }

    public function inventoryConsumptions(): HasMany
    {
        return $this->hasMany(OrderInventoryConsumption::class)->orderBy('id');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(OrderPromotion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rewardRedemptions(): HasMany
    {
        return $this->hasMany(OrderRewardRedemption::class)->orderBy('id');
    }

    public function loyaltyReward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    public function isDelivery(): bool
    {
        return $this->fulfilment_method === OrderFulfilmentMethod::Delivery;
    }

    public function isTakeaway(): bool
    {
        return $this->fulfilment_method === OrderFulfilmentMethod::Takeaway;
    }

    public function isDineIn(): bool
    {
        return $this->fulfilment_method === OrderFulfilmentMethod::DineIn;
    }

    public function tableDisplayLabel(): ?string
    {
        return filled($this->table_name_snapshot) ? (string) $this->table_name_snapshot : null;
    }

    public function customerLabelForStatus(?OrderStatus $status): string
    {
        if ($status === OrderStatus::ReadyForPickup && $this->fulfilment_method instanceof OrderFulfilmentMethod) {
            return $this->fulfilment_method->readyLabel();
        }

        return $status?->label() ?? '';
    }

    public function customerStatusLabel(): string
    {
        if (
            $this->status === OrderStatus::Cancelled
            && $this->cancellation_reason === 'payment_timeout'
        ) {
            return 'Cancelled — payment window expired';
        }

        return $this->customerLabelForStatus($this->status instanceof OrderStatus ? $this->status : null);
    }

    /**
     * Customer may cancel own unpaid retail Pending Payment order only.
     */
    public function canCustomerCancel(?User $customer = null): bool
    {
        if ($customer !== null && (int) $this->customer_id !== (int) $customer->getKey()) {
            return false;
        }

        if ($this->isDiningRound() || $this->fulfilment_method === OrderFulfilmentMethod::DineIn) {
            return false;
        }

        if ($this->status !== OrderStatus::PendingPayment) {
            return false;
        }

        if ($this->payment_status === PaymentStatus::Confirmed || $this->payment_confirmed_at !== null) {
            return false;
        }

        return true;
    }

    public function isPaymentWindowExpired(): bool
    {
        if ($this->payment_expires_at !== null) {
            return $this->payment_expires_at->isPast();
        }

        if ($this->placed_at === null) {
            return false;
        }

        $minutes = max(1, (int) config('coffee.orders.pending_payment_expiry_minutes', 120));

        return $this->placed_at->copy()->addMinutes($minutes)->isPast();
    }

    public function isCashPayment(): bool
    {
        return $this->payment_method === PaymentMethod::Cash;
    }

    public function canMarkCashReceived(): bool
    {
        return $this->isCashPayment()
            && $this->payment_status !== PaymentStatus::Confirmed
            && ! in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Rejected], true);
    }

    public function hasPaymentProof(): bool
    {
        return filled($this->payment_proof_path);
    }

    public function hasPaymentTransactionId(): bool
    {
        return filled($this->payment_transaction_id);
    }

    /**
     * Manual UPI evidence: submitted Transaction ID/UTR and/or historical screenshot.
     */
    public function hasManualPaymentEvidence(): bool
    {
        return $this->hasPaymentTransactionId() || $this->hasPaymentProof();
    }

    public function canUploadPaymentProof(): bool
    {
        return $this->canSubmitManualPaymentEvidence();
    }

    public function canSubmitManualPaymentEvidence(): bool
    {
        if ($this->isCashPayment() || ! ($this->payment_method?->requiresPaymentProof() ?? true)) {
            return false;
        }

        if ($this->payment_method?->isOnline()) {
            return false;
        }

        return $this->status === OrderStatus::PendingPayment
            && in_array($this->payment_status, [PaymentStatus::Pending, PaymentStatus::AwaitingReview, PaymentStatus::Rejected], true);
    }

    public function clearPaymentProofFiles(): void
    {
        if (! filled($this->payment_proof_path)) {
            return;
        }

        $disk = $this->payment_proof_disk ?: 'local';

        if (Storage::disk($disk)->exists($this->payment_proof_path)) {
            Storage::disk($disk)->delete($this->payment_proof_path);
        }
    }
}
