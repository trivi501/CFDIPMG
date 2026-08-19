<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\Facturapi\FacturapiClient;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = Invoice::with('customer')
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'filters' => $request->only('status'),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        return Inertia::render('invoices/show', [
            'invoice' => $invoice->load('customer', 'paymentReceipt'),
        ]);
    }

    public function pdf(Invoice $invoice, FacturapiClient $facturapi): HttpResponse
    {
        $response = $facturapi->downloadPdf($invoice->facturapi_invoice_id);

        return response($response->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="factura-'.$invoice->folio.'.pdf"',
        ]);
    }

    public function xml(Invoice $invoice, FacturapiClient $facturapi): HttpResponse
    {
        $response = $facturapi->downloadXml($invoice->facturapi_invoice_id);

        return response($response->body(), 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="factura-'.$invoice->folio.'.xml"',
        ]);
    }

    public function cancel(Invoice $invoice, InvoiceService $invoiceService): RedirectResponse
    {
        if ($invoice->status !== 'valid') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Solo se pueden cancelar facturas vigentes.']);

            return back();
        }

        $invoiceService->cancel($invoice);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Factura cancelada.']);

        return to_route('invoices.show', $invoice);
    }
}
