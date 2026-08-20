<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceipt;
use App\Services\Pagos\PagosClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptLookupController extends Controller
{
    /**
     * "Pagos generales" here means the Ingresos category in the external Pagos
     * system (as opposed to predial_urbano/predial_rustico) — general income
     * payments that aren't tied to a specific property tax account.
     */
    protected const TIPO_PAGO_GENERALES = 'Ingresos';

    public function create(): Response
    {
        return Inertia::render('receipts/lookup');
    }

    public function index(PagosClient $pagos): Response
    {
        $result = $pagos->list([
            'tipo_pago' => self::TIPO_PAGO_GENERALES,
            'estatus' => 'pagado',
            'per_page' => 100,
        ]);

        $pagosList = collect($result['data']);

        $existingReceipts = PaymentReceipt::query()
            ->where('source_system', PaymentReceipt::SOURCE_PAGOS_MUNICIPALES)
            ->whereIn('external_id', $pagosList->pluck('folio'))
            ->get(['external_id', 'status', 'invoice_id'])
            ->keyBy('external_id');

        return Inertia::render('receipts/pagos-generales', [
            'pagos' => $pagosList->map(function (array $pago) use ($existingReceipts) {
                $receipt = $existingReceipts->get($pago['folio']);

                return [
                    'folio' => $pago['folio'],
                    'fecha' => $pago['fecha'] ?? null,
                    'descripcion' => $pago['descripcion'] ?? null,
                    'monto' => $pago['monto'] ?? 0,
                    'contribuyente_nombre' => $pago['contribuyente']['nombre'] ?? null,
                    'receipt_status' => $receipt?->status,
                    'invoice_id' => $receipt?->invoice_id,
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, PagosClient $pagos): RedirectResponse
    {
        $data = $request->validate([
            'folio' => ['required', 'string', 'max:191'],
        ]);

        $pago = $pagos->find($data['folio']);

        if ($pago === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "No se encontró un pago con el folio \"{$data['folio']}\"."]);

            return back()->withErrors(['folio' => 'Folio no encontrado.']);
        }

        if (($pago['estatus'] ?? null) !== 'pagado') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Este pago no tiene estatus "pagado" (estatus actual: '.($pago['estatus'] ?? 'desconocido').'), no se puede facturar.',
            ]);

            return back()->withErrors(['folio' => 'El pago no está pagado.']);
        }

        $receipt = PaymentReceipt::firstOrCreate(
            [
                'source_system' => PaymentReceipt::SOURCE_PAGOS_MUNICIPALES,
                'external_id' => $pago['folio'] ?? $data['folio'],
            ],
            [
                'customer_payload' => $pago,
                'amount' => $pago['monto'] ?? 0,
                'currency' => 'MXN',
                'concept' => $pago['descripcion'] ?? null,
                'payment_method' => $pago['formas_pago'][0]['forma_pago'] ?? null,
                'payment_date' => $pago['fecha'] ?? null,
                'status' => 'pending',
                'received_at' => now(),
            ]
        );

        if ($receipt->status === 'invoiced') {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Este recibo ya fue facturado anteriormente.']);

            return to_route('invoices.show', $receipt->invoice_id);
        }

        return to_route('receipts.invoice.create', $receipt);
    }
}
