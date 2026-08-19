<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
            'canceled_at' => 'datetime',
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
     * @return HasOne<PaymentReceipt, $this>
     */
    public function sourceReceipt(): HasOne
    {
        return $this->hasOne(PaymentReceipt::class, 'invoice_id');
    }
}
