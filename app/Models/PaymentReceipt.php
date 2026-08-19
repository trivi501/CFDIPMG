<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $customer_payload
 */
class PaymentReceipt extends Model
{
    protected $fillable = [
        'external_id',
        'source_system',
        'customer_payload',
        'amount',
        'currency',
        'concept',
        'payment_method',
        'payment_date',
        'status',
        'invoice_id',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_payload' => 'array',
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
