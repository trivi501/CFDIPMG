<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizarDocumentos extends Command
{
    protected $signature = 'app:privatizar-documentos';

    protected $description = 'Mueve los documentos de apoyos y los XML de compra del disco público al privado';

    public function handle(): int
    {
        $publico = Storage::disk('public');
        $privado = Storage::disk('local');
        $movidos = 0;
        $fallidos = 0;

        foreach (['apoyos', 'facturas-compra'] as $carpeta) {
            foreach ($publico->allFiles($carpeta) as $archivo) {
                $contenido = (string) $publico->get($archivo);
                $yaExiste = $privado->exists($archivo);

                if ($yaExiste && (string) $privado->get($archivo) !== $contenido) {
                    $this->error("Ya existe con otro contenido, no se tocó: {$archivo}");
                    $fallidos++;

                    continue;
                }

                if (! $yaExiste && ! $privado->put($archivo, $contenido)) {
                    $this->error("No se pudo copiar: {$archivo}");
                    $fallidos++;

                    continue;
                }

                $publico->delete($archivo);
                $movidos++;
            }
        }

        $this->info("Movidos: {$movidos}. Fallidos: {$fallidos}.");

        return $fallidos === 0 ? self::SUCCESS : self::FAILURE;
    }
}
