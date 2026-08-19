<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'description',
        'sat_product_key',
        'sat_unit_key',
        'unit_price',
        'taxes',
        'facturapi_product_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'taxes' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
