<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bitácora de auditoría. Los movimientos los escribe App\Support\RegistroAuditoria (eventos de
 * modelos, escrituras masivas y accesos) y el middleware RegistrarConsultaAuditoria (descargas);
 * aquí solo se guardan y se consultan.
 *
 * @property array<string, mixed>|null $antes
 * @property array<string, mixed>|null $despues
 */
class Auditoria extends Model
{
    protected $table = 'auditoria';

    public const UPDATED_AT = null;

    public const ACCIONES = [
        'alta', 'modificación', 'eliminación', 'cambio masivo',
        'descarga', 'consulta',
        'inicio de sesión', 'cierre de sesión', 'acceso fallido',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['antes' => 'array', 'despues' => 'array', 'created_at' => 'datetime'];
    }

    /**
     * Operación en curso (petición, comando o proceso en cola): todos sus movimientos comparten el lote.
     *
     * @var array{lote: string, origen: string}|null
     */
    private static ?array $contexto = null;

    private static bool $escribiendo = false;

    private static bool $tablaExiste = false;

    public static function iniciarContexto(?string $origen): void
    {
        self::$contexto = $origen === null ? null : ['lote' => (string) Str::uuid(), 'origen' => mb_substr($origen, 0, 255)];
    }

    /**
     * @return array{lote: string, origen: string}
     */
    private static function contexto(): array
    {
        if (self::$contexto === null) {
            if (app()->runningInConsole()) {
                $origen = 'consola';
            } else {
                $request = request();
                $ruta = $request->route()?->getName();
                $origen = $request->method().' /'.ltrim($request->path(), '/').($ruta ? " ({$ruta})" : '');
            }
            self::iniciarContexto($origen);
        }

        /** @var array{lote: string, origen: string} */
        return self::$contexto;
    }

    /**
     * Guarda un movimiento. Nunca interrumpe la operación que lo origina: si la bitácora falla,
     * solo queda el aviso en el log.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function escribir(array $datos): void
    {
        if (self::$escribiendo) {
            return;
        }

        self::$escribiendo = true;

        try {
            // Mientras no exista la tabla (antes de correr la migración) se vuelve a revisar cada vez.
            if (! self::$tablaExiste && ! (self::$tablaExiste = Schema::hasTable('auditoria'))) {
                return;
            }

            $contexto = self::contexto();
            $texto = fn (string $campo, int $largo) => isset($datos[$campo]) ? mb_substr((string) $datos[$campo], 0, $largo) : null;
            $json = fn (string $campo) => isset($datos[$campo])
                ? json_encode($datos[$campo], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)
                : null;

            DB::table('auditoria')->insert([
                'user_id' => array_key_exists('user_id', $datos) ? $datos['user_id'] : auth()->id(),
                'accion' => $datos['accion'],
                'tabla' => $texto('tabla', 100),
                'auditable_type' => $datos['auditable_type'] ?? null,
                'auditable_id' => $texto('auditable_id', 60),
                'descripcion' => $texto('descripcion', 300),
                'antes' => $json('antes'),
                'despues' => $json('despues'),
                'lote' => $contexto['lote'],
                'origen' => $contexto['origen'],
                'ip' => app()->runningInConsole() ? null : request()->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar en la bitácora de auditoría: '.$e->getMessage());
        } finally {
            self::$escribiendo = false;
        }
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
