<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\Facturapi\FacturapiClient;
use App\Services\InvoiceService;
use App\Support\ImpuestosFactura;
use App\Support\UsoCfdi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class InvoiceController extends Controller
{
    /** Tope de facturas por ZIP: cada XML es una petición a Facturapi. */
    public const MAX_XMLS = 200;

    public function index(Request $request): Response
    {
        $invoices = Invoice::with('customer:id,legal_name,rfc')
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->string('search')->trim()->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('uuid', 'like', "%{$search}%")
                        ->orWhere('folio', 'like', "%{$search}%")
                        ->orWhere('series', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($query) => $query
                            ->where('legal_name', 'like', "%{$search}%")
                            ->orWhere('rfc', 'like', "%{$search}%"));
                });
            })
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
            'filters' => $request->only('status', 'search'),
            'maxXmls' => self::MAX_XMLS,
        ]);
    }

    /**
     * ZIP con los XML de las facturas marcadas en la lista. Las que no están timbradas (fallidas)
     * se omiten, y si Facturapi no entrega alguna se anota en ERRORES.txt en vez de abortar todo.
     */
    public function xmls(Request $request, FacturapiClient $facturapi): BinaryFileResponse|HttpResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_XMLS],
            'ids.*' => ['integer'],
        ]);

        $invoices = Invoice::whereIn('id', $data['ids'])
            ->whereNotNull('facturapi_invoice_id')
            ->whereNotNull('uuid')
            ->get();

        if ($invoices->isEmpty()) {
            return response('Ninguna de las facturas seleccionadas tiene XML (solo las timbradas lo tienen).', 404);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'facturas-xml');

        if ($zipPath === false) {
            abort(500, 'No se pudo preparar el archivo ZIP.');
        }

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $errores = [];

        foreach ($invoices as $invoice) {
            $nombre = trim(($invoice->series ?? '').($invoice->folio ?? '').'-'.$invoice->uuid, '-').'.xml';

            try {
                $zip->addFromString($nombre, $facturapi->downloadXml((string) $invoice->facturapi_invoice_id)->body());
            } catch (Throwable $e) {
                Log::warning('No se pudo descargar el XML de la factura', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
                $errores[] = "{$nombre}: {$e->getMessage()}";
            }
        }

        if ($errores !== []) {
            $zip->addFromString('ERRORES.txt', "No se pudieron descargar estos XML:\n\n".implode("\n", $errores)."\n");
        }

        $zip->close();

        return response()->download($zipPath, 'facturas-xml-'.now('America/Mexico_City')->format('Y-m-d').'.zip')
            ->deleteFileAfterSend();
    }

    /**
     * Form to generate an "open" invoice: one with no underlying registered
     * payment receipt, for when a payment wasn't captured in any source system.
     */
    public function create(): Response
    {
        return Inertia::render('invoices/create', [
            'customers' => Customer::orderBy('legal_name')->get(['id', 'legal_name', 'rfc', 'tax_system']),
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
            ...ImpuestosFactura::reglas(),
        ]);

        $customer = Customer::query()->where('id', $data['customer_id'])->firstOrFail();

        UsoCfdi::validar($data['use'], $customer->tax_system);

        $invoice = $invoiceService->generate(null, $customer, $data['items'], [
            'payment_form' => $data['payment_form'],
            'payment_method' => $data['payment_method'],
            'use' => $data['use'],
            ...ImpuestosFactura::desde($request, ivaPorDefecto: true),
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
