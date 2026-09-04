<?php

namespace App\Models;

use Database\Factories\CustomerDeliveryAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerDeliveryAddress extends AbstractModel
{
    /** @use HasFactory<CustomerDeliveryAddressFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'label',
        'recipient_name',
        'phone',
        'address_line_1',
        'address_line_2',
        'landmark',
        'city',
        'state',
        'postal_code',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function formattedAddress(): string
    {
        $lines = array_values(array_filter([
            trim((string) $this->address_line_1),
            filled($this->address_line_2) ? trim((string) $this->address_line_2) : null,
            filled($this->landmark) ? 'Near '.trim((string) $this->landmark) : null,
            trim(implode(', ', array_filter([
                trim((string) $this->city),
                trim((string) $this->state),
                trim((string) $this->postal_code),
            ]))),
        ]));

        return implode("\n", $lines);
    }
}
