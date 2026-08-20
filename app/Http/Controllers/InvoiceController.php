<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
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

    /**
     * Form to generate an "open" invoice: one with no underlying registered
     * payment receipt, for when a payment wasn't captured in any source system.
     */
    public function create(): Response
    {
        return Inertia::render('invoices/create', [
            'customers' => Customer::orderBy('legal_name')->get(['id', 'legal_name', 'rfc']),
            'products' => Product::where('is_active', true)->orderBy('description')->get(),
        ]);
    }

    public function store(Request $request, InvoiceService $invoiceService): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.sat_product_key' => ['required', 'string', 'max:8'],
            'items.*.sat_unit_key' => ['required', 'string', 'max:3'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'payment_form' => ['required', 'string', 'max:2'],
            'payment_method' => ['required', 'in:PUE,PPD'],
            'use' => ['required', 'string', 'max:4'],
        ]);

        $customer = Customer::query()->where('id', $data['customer_id'])->firstOrFail();

        $invoice = $invoiceService->generate(null, $customer, $data['items'], [
            'payment_form' => $data['payment_form'],
            'payment_method' => $data['payment_method'],
            'use' => $data['use'],
        ]);

        if ($invoice->status === 'failed') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Error al facturar: '.$invoice->error_message]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Factura generada correctamente.']);

        return to_route('invoices.show', $invoice);
    }

    public function show(Invoice $invoice): Response
    {
        return Inertia::render('invoices/show', [
            'invoice' => $invoice->load('customer', 'paymentReceipt', 'sourceReceipts'),
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
