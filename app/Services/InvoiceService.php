<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Services\Facturapi\FacturapiClient;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(protected FacturapiClient $facturapi) {}

    /**
     * Convert a payment receipt into a CFDI invoice via Facturapi. Pass a null
     * $receipt for an "open" invoice with no underlying registered payment.
     *
     * @param  list<array{description: string, sat_product_key: string, sat_unit_key: string, quantity: float, unit_price: float}>  $items
     * @param  array{payment_form: string, payment_method: string, use: string}  $cfdi
     */
    public function generate(?PaymentReceipt $receipt, Customer $customer, array $items, array $cfdi): Invoice
    {
        $total = collect($items)->sum(fn (array $item) => $item['quantity'] * $item['unit_price']);

        // Payments pulled from the municipal Pagos system (predial) already have IVA
        // baked into their amount, unlike manually-priced items elsewhere in the app.
        $taxIncluded = $receipt?->source_system === PaymentReceipt::SOURCE_PAGOS_MUNICIPALES;

        try {
            $facturapiCustomerId = $customer->facturapi_customer_id
                ?? $this->syncCustomerToFacturapi($customer);

            $response = $this->facturapi->createInvoice([
                'customer' => $facturapiCustomerId,
                'items' => collect($items)->map(fn (array $item) => [
                    'quantity' => $item['quantity'],
                    'product' => [
                        'description' => $item['description'],
                        'product_key' => $item['sat_product_key'],
                        'unit_key' => $item['sat_unit_key'],
                        'price' => $item['unit_price'],
                        'tax_included' => $taxIncluded,
                        'taxes' => [
                            ['type' => 'IVA', 'rate' => 0.16, 'factor' => 'Tasa'],
                        ],
                    ],
                ])->all(),
                'payment_form' => $cfdi['payment_form'],
                'payment_method' => $cfdi['payment_method'],
                'use' => $cfdi['use'],
                'currency' => $receipt === null ? 'MXN' : $receipt->currency,
            ])->json();

            return DB::transaction(function () use ($receipt, $customer, $response, $total) {
                $invoice = Invoice::create([
                    'payment_receipt_id' => $receipt?->id,
                    'customer_id' => $customer->id,
                    'facturapi_invoice_id' => $response['id'] ?? null,
                    'uuid' => $response['uuid'] ?? null,
                    'series' => $response['series'] ?? null,
                    'folio' => $response['folio_number'] ?? null,
                    'status' => 'valid',
                    'total' => $response['total'] ?? $total,
                    'issued_at' => now(),
                ]);

                $receipt?->update(['status' => 'invoiced', 'invoice_id' => $invoice->id]);

                return $invoice;
            });
        } catch (RequestException $exception) {
            $invoice = Invoice::create([
                'payment_receipt_id' => $receipt?->id,
                'customer_id' => $customer->id,
                'status' => 'failed',
                'total' => $total,
                'error_message' => $exception->response->json('message') ?? $exception->getMessage(),
            ]);

            $receipt?->update(['status' => 'failed']);

            return $invoice;
        }
    }

    public function cancel(Invoice $invoice, string $motive = '02'): Invoice
    {
        $this->facturapi->cancelInvoice($invoice->facturapi_invoice_id, $motive);

        $invoice->update(['status' => 'canceled', 'canceled_at' => now()]);

        $invoice->sourceReceipts()->update(['status' => 'canceled']);

        return $invoice;
    }

    /**
     * Generate a monthly "factura global" (billed to público en general) that
     * folds in every receipt not already individually invoiced during the
     * given period. SAT requires this to be filed within the first 3 days of
     * the following month, but that deadline is enforced by staff, not here.
     *
     * @param  Collection<int, PaymentReceipt>  $receipts
     * @param  array{payment_form: string, payment_method: string}  $cfdi
     */
    public function generateGlobal(Collection $receipts, array $cfdi, int $month, int $year): Invoice
    {
        $total = $receipts->sum('amount');

        $customer = Customer::firstOrCreate(
            ['rfc' => Customer::RFC_PUBLICO_GENERAL],
            [
                'legal_name' => 'PUBLICO EN GENERAL',
                'tax_system' => '616',
                'zip' => '01000',
            ]
        );

        try {
            $facturapiCustomerId = $customer->facturapi_customer_id
                ?? $this->syncCustomerToFacturapi($customer);

            $response = $this->facturapi->createInvoice([
                'customer' => $facturapiCustomerId,
                'items' => [[
                    'quantity' => 1,
                    'product' => [
                        'description' => 'Venta y/o prestación de servicios al público en general',
                        'product_key' => '84111506',
                        'unit_key' => 'E48',
                        'price' => (float) $total,
                        'tax_included' => true,
                        'taxes' => [
                            ['type' => 'IVA', 'rate' => 0.16, 'factor' => 'Tasa'],
                        ],
                    ],
                ]],
                'payment_form' => $cfdi['payment_form'],
                'payment_method' => $cfdi['payment_method'],
                'use' => 'S01',
                'type' => 'I',
                'global' => [
                    'periodicity' => 'month',
                    'months' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                    'year' => $year,
                ],
            ])->json();

            return DB::transaction(function () use ($receipts, $customer, $response, $total, $month, $year) {
                $invoice = Invoice::create([
                    'customer_id' => $customer->id,
                    'facturapi_invoice_id' => $response['id'] ?? null,
                    'uuid' => $response['uuid'] ?? null,
                    'series' => $response['series'] ?? null,
                    'folio' => $response['folio_number'] ?? null,
                    'status' => 'valid',
                    'total' => $response['total'] ?? $total,
                    'issued_at' => now(),
                    'is_global' => true,
                    'period_month' => $month,
                    'period_year' => $year,
                ]);

                PaymentReceipt::whereIn('id', $receipts->pluck('id'))
                    ->update(['status' => 'invoiced', 'invoice_id' => $invoice->id]);

                return $invoice;
            });
        } catch (RequestException $exception) {
            return Invoice::create([
                'customer_id' => $customer->id,
                'status' => 'failed',
                'total' => $total,
                'error_message' => $exception->response->json('message') ?? $exception->getMessage(),
                'is_global' => true,
                'period_month' => $month,
                'period_year' => $year,
            ]);
        }
    }

    protected function syncCustomerToFacturapi(Customer $customer): string
    {
        $response = $this->facturapi->createCustomer([
            'legal_name' => $customer->legal_name,
            'tax_id' => $customer->rfc,
            'tax_system' => $customer->tax_system,
            'email' => $customer->email,
            'address' => ['zip' => $customer->zip],
        ])->json();

        $customer->update(['facturapi_customer_id' => $response['id']]);

        return $response['id'];
    }
}
