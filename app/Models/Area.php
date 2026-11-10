<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    public const TIPO_SECRETARIA = 'secretaria';

    public const TIPO_REGIDURIA = 'regiduria';

    protected $fillable = ['nombre', 'tipo'];

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Apoyo, $this>
     */
    public function apoyos(): HasMany
    {
        return $this->hasMany(Apoyo::class);
    }
}
