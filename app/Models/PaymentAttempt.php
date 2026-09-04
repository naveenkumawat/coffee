<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use App\Enums\PaymentMethod;
use Database\Factories\PaymentAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends AbstractModel
{
    /** @use HasFactory<PaymentAttemptFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'customer_id',
        'provider',
        'provider_payment_id',
        'provider_order_id',
        'provider_reference',
        'amount',
        'currency',
        'status',
        'initiated_at',
        'confirmed_at',
        'failed_at',
        'failure_code',
        'failure_message',
        'client_payload',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentAttemptStatus::class,
            'initiated_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'failed_at' => 'datetime',
            'client_payload' => 'array',
            'meta' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function paymentMethod(): ?PaymentMethod
    {
        return PaymentMethod::tryFromApiKey($this->provider);
    }

    public function isConfirmed(): bool
    {
        return $this->status === PaymentAttemptStatus::Confirmed;
    }
}
