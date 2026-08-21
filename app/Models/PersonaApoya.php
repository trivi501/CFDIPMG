<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonaApoya extends Model
{
    protected $table = 'personas_apoya';

    protected $fillable = [
        'nombre',
    ];

    /**
     * @return HasMany<Apoyo, $this>
     */
    public function apoyos(): HasMany
    {
        return $this->hasMany(Apoyo::class);
    }
}
