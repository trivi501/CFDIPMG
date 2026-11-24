<?php

namespace App\Services\Facturapi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FacturapiClient
{
    protected function client(): PendingRequest
    {
        return Http::withBasicAuth(config('services.facturapi.key'), '')
            ->baseUrl(config('services.facturapi.base_url'))
            ->acceptJson();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCustomer(array $payload): Response
    {
        return $this->client()->post('/customers', $payload)->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createInvoice(array $payload): Response
    {
        return $this->client()->post('/invoices', $payload)->throw();
    }

    public function getInvoice(string $facturapiInvoiceId): Response
    {
        return $this->client()->get("/invoices/{$facturapiInvoiceId}")->throw();
    }

    public function cancelInvoice(string $facturapiInvoiceId, string $motive = '02'): Response
    {
        return $this->client()
            ->delete("/invoices/{$facturapiInvoiceId}?motive={$motive}")
            ->throw();
    }

    public function downloadPdf(string $facturapiInvoiceId): Response
    {
        return $this->client()->get("/invoices/{$facturapiInvoiceId}/pdf")->throw();
    }

    public function downloadXml(string $facturapiInvoiceId): Response
    {
        return $this->client()->get("/invoices/{$facturapiInvoiceId}/xml")->throw();
    }

    /**
     * Facturapi envía la factura (PDF y XML adjuntos) a estos correos. La emisión no la manda sola.
     *
     * @param  list<string>  $emails
     */
    public function sendInvoiceByEmail(string $facturapiInvoiceId, array $emails): Response
    {
        return $this->client()
            ->post("/invoices/{$facturapiInvoiceId}/email", ['email' => $emails])
            ->throw();
    }
}
