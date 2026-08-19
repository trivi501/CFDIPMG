<?php

namespace App\Services\Pagos;

use Illuminate\Support\Facades\Http;

class PagosClient
{
    /**
     * Look up a payment by its folio in the external "Pagos" system.
     *
     * @return array<string, mixed>|null null when the folio does not exist (404)
     */
    public function find(string $folio): ?array
    {
        $response = Http::withToken(config('services.pagos.token'))
            ->acceptJson()
            ->baseUrl(config('services.pagos.base_url'))
            ->get("/api/pagos/{$folio}");

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json();
    }
}
