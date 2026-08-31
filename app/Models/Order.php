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
        'total_amount',
        'customer_notes',
        'pickup_notes',
        'fulfilment_method',
        'cafe_table_id',
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
        'payment_proof_path',
        'payment_proof_disk',
        'payment_proof_mime',
        'payment_proof_size',
        'payment_proof_uploaded_at',
        'payment_proof_rejection_notes',
        'placed_at',
        'payment_confirmed_at',
        'accepted_at',
        'preparing_at',
        'ready_for_pickup_at',
        'completed_at',
        'cancelled_at',
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
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'delivery_fee_amount' => 'decimal:2',
            'payment_proof_size' => 'integer',
            'placed_at' => 'datetime',
            'payment_proof_uploaded_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'preparing_at' => 'datetime',
            'ready_for_pickup_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function assignedBarista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_barista_id')->withTrashed();
    }

    public function cafeTable(): BelongsTo
    {
        return $this->belongsTo(CafeTable::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
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
        return $this->customerLabelForStatus($this->status instanceof OrderStatus ? $this->status : null);
    }

    public function hasPaymentProof(): bool
    {
        return filled($this->payment_proof_path);
    }

    public function canUploadPaymentProof(): bool
    {
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
