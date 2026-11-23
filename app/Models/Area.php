<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    public const TIPO_SECRETARIA = 'secretaria';

    public const TIPO_REGIDURIA = 'regiduria';

    /**
     * privada: cada integrante ve solo lo suyo (p. ej. Cabildo, donde están juntos todos los
     * regidores y sus asistentes). Si no, los integrantes comparten apoyos y facturas de compra.
     */
    protected $fillable = ['nombre', 'tipo', 'privada'];

    protected function casts(): array
    {
        return ['privada' => 'boolean'];
    }

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
