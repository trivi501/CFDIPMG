<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentReceipt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'stats' => [
                'pendingReceipts' => PaymentReceipt::where('status', 'pending')->count(),
                'invoicedThisMonth' => Invoice::where('status', 'valid')
                    ->whereMonth('issued_at', now()->month)
                    ->whereYear('issued_at', now()->year)
                    ->count(),
                'failedReceipts' => PaymentReceipt::where('status', 'failed')->count(),
                'totalAmountThisMonth' => Invoice::where('status', 'valid')
                    ->whereMonth('issued_at', now()->month)
                    ->whereYear('issued_at', now()->year)
                    ->sum('total'),
            ],
            'recentInvoices' => Invoice::with('customer')
                ->latest('issued_at')
                ->take(5)
                ->get(),
        ]);
    }
}
