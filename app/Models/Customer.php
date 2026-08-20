<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /**
     * The generic RFC SAT reserves for "público en general" — used as the
     * billed-to customer on global invoices.
     */
    public const RFC_PUBLICO_GENERAL = 'XAXX010101000';

    protected $fillable = [
        'legal_name',
        'rfc',
        'tax_system',
        'email',
        'zip',
        'address',
        'facturapi_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
        ];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
