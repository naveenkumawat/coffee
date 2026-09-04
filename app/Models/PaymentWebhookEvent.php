<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends AbstractModel
{
    protected $fillable = [
        'provider',
        'event_id',
        'payload_hash',
        'payment_attempt_id',
        'processing_result',
    ];

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }
}
