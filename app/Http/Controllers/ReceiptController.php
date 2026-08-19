<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentReceipt;
use App\Models\Product;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptController extends Controller
{
    public function index(Request $request): Response
    {
        $receipts = PaymentReceipt::query()
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->latest('received_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('receipts/index', [
            'receipts' => $receipts,
            'filters' => $request->only('status'),
        ]);
    }

    public function create(PaymentReceipt $receipt): Response
    {
        return Inertia::render('receipts/invoice', [
            'receipt' => $receipt,
            'customers' => Customer::orderBy('legal_name')->get(['id', 'legal_name', 'rfc']),
            'products' => Product::where('is_active', true)->orderBy('description')->get(),
        ]);
    }

    public function store(Request $request, PaymentReceipt $receipt, InvoiceService $invoiceService): RedirectResponse
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

        if ($receipt->status === 'invoiced') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Este recibo ya fue facturado.']);

            return to_route('receipts.index');
        }

        $customer = Customer::query()->where('id', $data['customer_id'])->firstOrFail();

        $invoice = $invoiceService->generate($receipt, $customer, $data['items'], [
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
}
