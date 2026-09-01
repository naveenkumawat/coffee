<?php

namespace App\Models;

use App\Enums\DiningSessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DiningSession extends AbstractModel
{
    protected $fillable = [
        'session_number',
        'cafe_table_id',
        'customer_id',
        'opened_by_user_id',
        'status',
        'guest_count',
        'table_name_snapshot',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'opened_at',
        'billing_requested_at',
        'bill_generated_at',
        'paid_at',
        'closed_at',
        'payment_method',
        'payment_status',
        'subtotal_amount',
        'discount_amount',
        'tax_amount',
        'taxable_amount',
        'tax_enabled_snapshot',
        'tax_label_snapshot',
        'tax_percent_snapshot',
        'tax_inclusive_snapshot',
        'total_amount',
        'payment_reference',
        'payment_proof_path',
        'payment_proof_disk',
        'payment_proof_mime',
        'payment_proof_size',
        'payment_proof_uploaded_at',
        'payment_proof_rejection_notes',
        'payment_received_by_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DiningSessionStatus::class,
            'guest_count' => 'integer',
            'opened_at' => 'datetime',
            'billing_requested_at' => 'datetime',
            'bill_generated_at' => 'datetime',
            'paid_at' => 'datetime',
            'closed_at' => 'datetime',
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_enabled_snapshot' => 'boolean',
            'tax_percent_snapshot' => 'decimal:2',
            'tax_inclusive_snapshot' => 'boolean',
            'total_amount' => 'decimal:2',
            'payment_proof_size' => 'integer',
            'payment_proof_uploaded_at' => 'datetime',
        ];
    }

    public function cafeTable(): BelongsTo
    {
        return $this->belongsTo(CafeTable::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id')->withTrashed();
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id')->withTrashed();
    }

    public function paymentReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_received_by_id')->withTrashed();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy('dining_round_number')->orderBy('id');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(DiningRoundDraft::class)->orderBy('id');
    }

    public function allowsNewRounds(): bool
    {
        return $this->status instanceof DiningSessionStatus && $this->status->allowsNewRounds();
    }

    public function occupiesTable(): bool
    {
        return $this->status instanceof DiningSessionStatus && $this->status->occupiesTable();
    }

    public function isActive(): bool
    {
        return $this->status instanceof DiningSessionStatus && $this->status->isActive();
    }

    public function isCashPayment(): bool
    {
        return $this->payment_method === PaymentMethod::Cash;
    }

    public function hasPaymentProof(): bool
    {
        return filled($this->payment_proof_path);
    }

    public function canUploadPaymentProof(): bool
    {
        if ($this->isCashPayment()) {
            return false;
        }

        if (! in_array($this->status, [
            DiningSessionStatus::BillingRequested,
            DiningSessionStatus::AwaitingPayment,
        ], true)) {
            return false;
        }

        return $this->payment_status !== PaymentStatus::Confirmed;
    }

    public function clearPaymentProofFiles(): void
    {
        if (! filled($this->payment_proof_path)) {
            return;
        }

        $disk = filled($this->payment_proof_disk) ? (string) $this->payment_proof_disk : 'local';

        if (Storage::disk($disk)->exists((string) $this->payment_proof_path)) {
            Storage::disk($disk)->delete((string) $this->payment_proof_path);
        }
    }

    public function tableDisplayLabel(): string
    {
        return filled($this->table_name_snapshot)
            ? (string) $this->table_name_snapshot
            : ($this->cafeTable?->displayLabel() ?? 'Table');
    }
}
