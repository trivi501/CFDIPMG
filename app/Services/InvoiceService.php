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
    public const NOMBRE_PUBLICO_GENERAL = 'PUBLICO EN GENERAL';

    public function __construct(protected FacturapiClient $facturapi) {}

    /**
     * Convert a payment receipt into a CFDI invoice via Facturapi. Pass a null
     * $receipt for an "open" invoice with no underlying registered payment.
     *
     * $cfdi['receptor_nombre'] solo aplica cuando el cliente es el RFC genérico (público en general):
     * es el nombre con el que se emite esa factura en particular, p. ej. el beneficiario de un apoyo.
     *
     * @param  list<array{description: string, sat_product_key: string, sat_unit_key: string, quantity: float, unit_price: float}>  $items
     * @param  array{payment_form: string, payment_method: string, use: string, type?: 'I'|'E', iva?: bool, isr_rate?: float|null, receptor_nombre?: string|null}  $cfdi
     */
    public function generate(?PaymentReceipt $receipt, Customer $customer, array $items, array $cfdi): Invoice
    {
        $total = collect($items)->sum(fn (array $item) => $item['quantity'] * $item['unit_price']);

        $conIva = $cfdi['iva'] ?? true;

        // Payments pulled from the municipal Pagos system (predial) already have IVA
        // baked into their amount, unlike manually-priced items elsewhere in the app.
        $taxIncluded = $conIva && $receipt?->source_system === PaymentReceipt::SOURCE_PAGOS_MUNICIPALES;

        $impuestos = $this->impuestos($conIva, $cfdi['isr_rate'] ?? null);

        $nombreReceptor = $this->nombreReceptor($customer, $cfdi['receptor_nombre'] ?? null);

        try {
            $response = $this->facturapi->createInvoice([
                'customer' => $this->receptorFacturapi($customer, $nombreReceptor),
                'items' => collect($items)->map(fn (array $item) => [
                    'quantity' => $item['quantity'],
                    'product' => [
                        'description' => $item['description'],
                        'product_key' => $item['sat_product_key'],
                        'unit_key' => $item['sat_unit_key'],
                        'price' => $item['unit_price'],
                        'tax_included' => $taxIncluded,
                        ...$impuestos,
                    ],
                ])->all(),
                'type' => $cfdi['type'] ?? 'I',
                'payment_form' => $cfdi['payment_form'],
                'payment_method' => $cfdi['payment_method'],
                'use' => $cfdi['use'],
                'currency' => $receipt === null ? 'MXN' : $receipt->currency,
            ])->json();

            return DB::transaction(function () use ($receipt, $customer, $nombreReceptor, $response, $total) {
                $invoice = Invoice::create([
                    'payment_receipt_id' => $receipt?->id,
                    'customer_id' => $customer->id,
                    'receptor_nombre' => $nombreReceptor,
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
                'receptor_nombre' => $nombreReceptor,
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

        // El SAT exige "PUBLICO EN GENERAL" en la factura global, sin importar cómo se llame el
        // Customer compartido del RFC genérico.
        $nombreReceptor = self::NOMBRE_PUBLICO_GENERAL;

        try {
            $response = $this->facturapi->createInvoice([
                'customer' => $this->receptorFacturapi($customer, $nombreReceptor),
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

            return DB::transaction(function () use ($receipts, $customer, $nombreReceptor, $response, $total, $month, $year) {
                $invoice = Invoice::create([
                    'customer_id' => $customer->id,
                    'receptor_nombre' => $nombreReceptor,
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
                'receptor_nombre' => $nombreReceptor,
                'status' => 'failed',
                'total' => $total,
                'error_message' => $exception->response->json('message') ?? $exception->getMessage(),
                'is_global' => true,
                'period_month' => $month,
                'period_year' => $year,
            ]);
        }
    }

    /**
     * Impuestos de cada concepto. Sin IVA ni ISR el concepto va como "No objeto de impuesto"
     * (ObjetoImp 01) y sin arreglo de impuestos, que es como Facturapi documenta ese caso.
     *
     * @return array{taxability: string, taxes?: list<array<string, mixed>>}
     */
    protected function impuestos(bool $conIva, ?float $isrRate): array
    {
        $taxes = [];

        if ($conIva) {
            $taxes[] = ['type' => 'IVA', 'rate' => 0.16, 'factor' => 'Tasa'];
        }

        if ($isrRate !== null && $isrRate > 0) {
            $taxes[] = ['type' => 'ISR', 'rate' => $isrRate, 'factor' => 'Tasa', 'withholding' => true];
        }

        return $taxes === [] ? ['taxability' => '01'] : ['taxability' => '02', 'taxes' => $taxes];
    }

    /**
     * Nombre con el que se emite la factura. Un RFC real es de una sola persona y se factura con su
     * nombre registrado. El RFC genérico lo comparten todos: cada factura lleva el nombre que se le
     * indique (p. ej. el beneficiario de un apoyo) o "PUBLICO EN GENERAL".
     */
    protected function nombreReceptor(Customer $customer, ?string $nombre): string
    {
        if ($customer->rfc !== Customer::RFC_PUBLICO_GENERAL) {
            return $customer->legal_name;
        }

        $nombre = trim((string) $nombre);

        return $nombre !== '' ? $nombre : self::NOMBRE_PUBLICO_GENERAL;
    }

    /**
     * Receptor para Facturapi. Con un RFC real se usa el cliente guardado en Facturapi. Con el RFC
     * genérico NO: ese cliente es uno solo para todos y su nombre es el del primero que se facturó,
     * así que los datos del receptor van en la factura misma con el nombre que le corresponde.
     *
     * @return string|array<string, mixed>
     */
    protected function receptorFacturapi(Customer $customer, string $nombre): string|array
    {
        if ($customer->rfc !== Customer::RFC_PUBLICO_GENERAL) {
            return $customer->facturapi_customer_id ?? $this->syncCustomerToFacturapi($customer);
        }

        return [
            'legal_name' => $nombre,
            'tax_id' => Customer::RFC_PUBLICO_GENERAL,
            'tax_system' => '616',
            'address' => ['zip' => $customer->zip],
        ];
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
