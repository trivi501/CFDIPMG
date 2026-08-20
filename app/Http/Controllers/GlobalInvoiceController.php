<?php

namespace App\Http\Controllers;

use App\Models\PaymentReceipt;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GlobalInvoiceController extends Controller
{
    public function create(Request $request): Response
    {
        $month = $request->integer('month') ?: (int) now()->subMonthNoOverflow()->format('n');
        $year = $request->integer('year') ?: (int) now()->subMonthNoOverflow()->format('Y');

        $receipts = $this->eligibleReceipts($month, $year);

        return Inertia::render('invoices/global', [
            'month' => $month,
            'year' => $year,
            'receipts' => $receipts->values(),
            'total' => $receipts->sum('amount'),
        ]);
    }

    public function store(Request $request, InvoiceService $invoiceService): RedirectResponse
    {
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2020'],
            'payment_form' => ['required', 'string', 'max:2'],
            'payment_method' => ['required', 'in:PUE,PPD'],
        ]);

        $receipts = $this->eligibleReceipts($data['month'], $data['year']);

        if ($receipts->isEmpty()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No hay recibos pendientes de facturar en ese periodo.']);

            return back();
        }

        $invoice = $invoiceService->generateGlobal($receipts, [
            'payment_form' => $data['payment_form'],
            'payment_method' => $data['payment_method'],
        ], $data['month'], $data['year']);

        if ($invoice->status === 'failed') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Error al facturar: '.$invoice->error_message]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Factura global generada correctamente.']);

        return to_route('invoices.show', $invoice);
    }

    /**
     * Every receipt not already individually invoiced whose payment falls
     * within the given month (falling back to when it was received, for
     * receipts with no payment_date on file).
     *
     * @return Collection<int, PaymentReceipt>
     */
    protected function eligibleReceipts(int $month, int $year): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return PaymentReceipt::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('payment_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhere(function ($query) use ($start, $end) {
                        $query->whereNull('payment_date')
                            ->whereBetween('received_at', [$start, $end]);
                    });
            })
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }
}
