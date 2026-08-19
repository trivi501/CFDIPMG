<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
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
