<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'payment_receipt_id',
        'customer_id',
        'facturapi_invoice_id',
        'uuid',
        'series',
        'folio',
        'status',
        'total',
        'pdf_url',
        'xml_url',
        'error_message',
        'issued_at',
        'canceled_at',
        'is_global',
        'period_month',
        'period_year',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'canceled_at' => 'datetime',
            'is_global' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<PaymentReceipt, $this>
     */
    public function paymentReceipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class);
    }

    /**
     * All receipts folded into this invoice — plural because a global invoice
     * (is_global) can cover many; a regular receipt-based invoice has exactly one.
     *
     * @return HasMany<PaymentReceipt, $this>
     */
    public function sourceReceipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'invoice_id');
    }
}
