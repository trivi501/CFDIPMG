<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:create-api-client {name : Nombre descriptivo del sistema externo}')]
#[Description('Genera una API key para que un sistema externo pueda enviar recibos de pago')]
class CreateApiClient extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $result = ApiClient::generate($this->argument('name'));

        $this->info('API client creado: '.$result['client']->name);
        $this->newLine();
        $this->line('API key (guárdala ahora, no se volverá a mostrar):');
        $this->warn($result['plainTextKey']);
        $this->newLine();
        $this->line('Úsala en el encabezado X-Api-Key al llamar a POST /api/v1/receipts');

        return self::SUCCESS;
    }
}
