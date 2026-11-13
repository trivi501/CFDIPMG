<?php

namespace App\Support;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Bitácora de auditoría de todo el sistema, sin tocar cada controlador:
 *
 *  - Eventos de Eloquent de cualquier modelo: alta, modificación y eliminación, con el valor
 *    anterior y el nuevo de cada campo que cambió.
 *  - Escrituras que no pasan por un modelo (`Model::where(...)->update()`, `DB::table(...)->update()`,
 *    borrados de relaciones, asignación de roles): se registran como "cambio masivo" con la
 *    instrucción ejecutada.
 *  - Inicio y cierre de sesión e intentos fallidos.
 *
 * Todos los movimientos de una misma petición, comando o proceso en cola comparten un lote.
 */
class RegistroAuditoria
{
    /** Tablas técnicas que no se auditan (sesiones, caché, colas). */
    private const TABLAS_OMITIDAS = [
        'auditoria', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'migrations', 'password_reset_tokens', 'personal_access_tokens',
    ];

    private const CAMPOS_OMITIDOS = ['created_at', 'updated_at', 'remember_token'];

    private const LARGO_MAXIMO_INSTRUCCION = 4000;

    /**
     * Escrituras que va a ejecutar un modelo y que ya registra su evento: "insert:tabla" => pendientes.
     * Evita registrar dos veces la misma escritura (como evento y como cambio masivo).
     *
     * @var array<string, int>
     */
    private static array $pendientes = [];

    /**
     * Inserciones masivas por registrar todavía. No se escriben al momento: QueryExecuted se dispara
     * antes de que Laravel lea lastInsertId(), y un INSERT a la bitácora en ese instante haría que el
     * insertGetId() original regresara el id de la fila de auditoría. Se escriben antes de la
     * siguiente consulta, al confirmar la transacción o al terminar la petición o el comando.
     *
     * @var list<array{nivel: int, datos: array<string, mixed>}>
     */
    private static array $diferidos = [];

    public static function registrar(): void
    {
        DB::beforeExecuting(fn () => self::escribirDiferidos());
        Event::listen(TransactionCommitting::class, fn () => self::escribirDiferidos());
        Event::listen(TransactionRolledBack::class, fn (TransactionRolledBack $e) => self::descartarDiferidos($e->connection->transactionLevel()));
        Event::listen(CommandFinished::class, fn () => self::escribirDiferidos());
        Event::listen(JobProcessed::class, fn () => self::escribirDiferidos());
        app()->terminating(fn () => self::escribirDiferidos());

        Event::listen('eloquent.creating: *', fn (string $evento, array $datos) => self::marcar('insert', $datos[0]));
        Event::listen('eloquent.updating: *', fn (string $evento, array $datos) => self::marcar('update', $datos[0]));
        Event::listen('eloquent.deleting: *', fn (string $evento, array $datos) => self::marcar('delete', $datos[0]));
        Event::listen('eloquent.saved: *', fn (string $evento, array $datos) => self::liberar($datos[0]));

        Event::listen('eloquent.created: *', fn (string $evento, array $datos) => self::creado($datos[0]));
        Event::listen('eloquent.updated: *', fn (string $evento, array $datos) => self::actualizado($datos[0]));
        Event::listen('eloquent.deleted: *', fn (string $evento, array $datos) => self::eliminado($datos[0]));

        DB::listen(fn (QueryExecuted $consulta) => self::consulta($consulta));

        Event::listen(Login::class, fn (Login $e) => self::acceso('inicio de sesión', $e->user));
        Event::listen(Logout::class, fn (Logout $e) => self::acceso('cierre de sesión', $e->user));
        Event::listen(Failed::class, fn (Failed $e) => self::acceso(
            'acceso fallido',
            $e->user,
            'Intento con el correo '.($e->credentials['email'] ?? '(sin correo)'),
        ));

        Event::listen(CommandStarting::class, function (CommandStarting $e) {
            if (! in_array($e->command, ['queue:work', 'queue:listen'], true)) {
                Auditoria::iniciarContexto('artisan '.trim((string) $e->input));
            }
        });
        Event::listen(JobProcessing::class, fn (JobProcessing $e) => Auditoria::iniciarContexto('cola: '.$e->job->resolveName()));
        Event::listen(JobProcessed::class, fn () => Auditoria::iniciarContexto(null));
    }

    private static function auditable(Model $modelo): bool
    {
        return ! in_array($modelo->getTable(), self::TABLAS_OMITIDAS, true);
    }

    private static function marcar(string $tipo, Model $modelo): void
    {
        $clave = $tipo.':'.$modelo->getTable();
        self::$pendientes[$clave] = (self::$pendientes[$clave] ?? 0) + 1;
    }

    /** Un guardado sin cambios no ejecuta su UPDATE: se libera la marca para no ocultar otro cambio. */
    private static function liberar(Model $modelo): void
    {
        unset(self::$pendientes['update:'.$modelo->getTable()], self::$pendientes['insert:'.$modelo->getTable()]);
    }

    private static function creado(Model $modelo): void
    {
        if (self::auditable($modelo)) {
            self::movimiento($modelo, 'alta', null, self::valores($modelo, $modelo->getAttributes()));
        }
    }

    private static function actualizado(Model $modelo): void
    {
        if (! self::auditable($modelo)) {
            return;
        }

        $despues = self::valores($modelo, $modelo->getChanges());
        if ($despues === []) {
            return;
        }

        $antes = self::valores($modelo, Arr::only($modelo->getRawOriginal(), array_keys($despues)));
        self::movimiento($modelo, 'modificación', $antes, $despues);
    }

    private static function eliminado(Model $modelo): void
    {
        if (self::auditable($modelo)) {
            self::movimiento($modelo, 'eliminación', self::valores($modelo, $modelo->getRawOriginal() ?: $modelo->getAttributes()), null);
        }
    }

    /**
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    private static function movimiento(Model $modelo, string $accion, ?array $antes, ?array $despues): void
    {
        Auditoria::escribir([
            'accion' => $accion,
            'tabla' => $modelo->getTable(),
            'auditable_type' => $modelo::class,
            'auditable_id' => $modelo->getKey(),
            'antes' => $antes,
            'despues' => $despues,
        ]);
    }

    /**
     * Escrituras que no vienen de un modelo. Las de un modelo ya quedaron registradas por su evento
     * (con antes y después), así que se descartan aquí.
     */
    private static function consulta(QueryExecuted $consulta): void
    {
        if (! preg_match('/^\s*(insert(?:\s+ignore)?\s+into|update|delete\s+from)\s+[`"\[]?([^`"\]\s]+)[`"\]]?/i', $consulta->sql, $m)) {
            return;
        }

        $tabla = $m[2];
        if (in_array($tabla, self::TABLAS_OMITIDAS, true)) {
            return;
        }

        $tipo = match (true) {
            str_starts_with(strtolower($m[1]), 'insert') => 'insert',
            strtolower($m[1]) === 'update' => 'update',
            default => 'delete',
        };

        $clave = "{$tipo}:{$tabla}";
        if ((self::$pendientes[$clave] ?? 0) > 0) {
            self::$pendientes[$clave]--;

            return;
        }

        $instruccion = self::interpolar($consulta, in_array($tabla, ['users', 'api_clients', 'passkeys'], true));
        if (mb_strlen($instruccion) > self::LARGO_MAXIMO_INSTRUCCION) {
            $instruccion = mb_substr($instruccion, 0, self::LARGO_MAXIMO_INSTRUCCION).' …';
        }

        $datos = [
            'accion' => 'cambio masivo',
            'tabla' => $tabla,
            'descripcion' => match ($tipo) {
                'insert' => "Inserción en {$tabla}",
                'update' => "Actualización en {$tabla}",
                default => "Eliminación en {$tabla}",
            },
            'despues' => ['instrucción' => $instruccion],
        ];

        if ($tipo === 'insert') {
            self::$diferidos[] = ['nivel' => $consulta->connection->transactionLevel(), 'datos' => $datos];

            return;
        }

        Auditoria::escribir($datos);
    }

    private static function escribirDiferidos(): void
    {
        if (self::$diferidos === []) {
            return;
        }

        // Se vacía antes de escribir: los INSERT a la bitácora vuelven a pasar por beforeExecuting.
        $diferidos = self::$diferidos;
        self::$diferidos = [];

        foreach ($diferidos as $diferido) {
            Auditoria::escribir($diferido['datos']);
        }
    }

    /** Lo insertado dentro de la transacción (o savepoint) revertida ya no existe: no se registra. */
    private static function descartarDiferidos(int $nivelActual): void
    {
        self::$diferidos = array_values(array_filter(
            self::$diferidos,
            fn (array $diferido) => $diferido['nivel'] <= $nivelActual,
        ));
    }

    private static function interpolar(QueryExecuted $consulta, bool $ocultarValores): string
    {
        $valores = array_map(function (mixed $v) use ($ocultarValores): string {
            if ($ocultarValores) {
                return "'[oculto]'";
            }

            return match (true) {
                $v === null => 'NULL',
                is_bool($v) => $v ? '1' : '0',
                is_int($v), is_float($v) => (string) $v,
                $v instanceof \DateTimeInterface => "'".$v->format('Y-m-d H:i:s')."'",
                default => "'".str_replace("'", "''", (string) $v)."'",
            };
        }, $consulta->bindings);

        return (string) preg_replace_callback('/\?/', function () use (&$valores): string {
            return array_shift($valores) ?? '?';
        }, $consulta->sql);
    }

    private static function acceso(string $accion, ?Authenticatable $usuario, ?string $descripcion = null): void
    {
        Auditoria::escribir([
            'user_id' => $accion === 'acceso fallido' ? null : $usuario?->getAuthIdentifier(),
            'accion' => $accion,
            'tabla' => 'users',
            'auditable_type' => User::class,
            'auditable_id' => $usuario?->getAuthIdentifier(),
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * La llave ya queda en auditable_id. Contraseñas, tokens y atributos ocultos se registran como
     * "[oculto]": queda constancia de que cambiaron, sin guardar el valor.
     *
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private static function valores(Model $modelo, array $valores): array
    {
        $ocultos = $modelo->getHidden();

        return collect(Arr::except($valores, [$modelo->getKeyName(), ...self::CAMPOS_OMITIDOS]))
            ->map(fn (mixed $v, string $campo) => match (true) {
                in_array($campo, $ocultos, true) || preg_match('/password|token|secret|key_hash/i', $campo) === 1 => $v === null ? null : '[oculto]',
                $v instanceof \DateTimeInterface => $v->format('Y-m-d H:i:s'),
                is_string($v) && mb_strlen($v) > 2000 => mb_substr($v, 0, 2000).' …',
                default => $v,
            })
            ->all();
    }
}
