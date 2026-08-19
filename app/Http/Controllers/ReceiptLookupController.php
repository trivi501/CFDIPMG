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
    public function create(): Response
    {
        return Inertia::render('receipts/lookup');
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
