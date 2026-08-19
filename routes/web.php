<?php

use App\Http\Controllers\ApiClientController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReceiptLookupController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('permission:receipts.view')->group(function () {
        Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
        Route::get('receipts/lookup', [ReceiptLookupController::class, 'create'])
            ->middleware('permission:receipts.import')
            ->name('receipts.lookup.create');
        Route::post('receipts/lookup', [ReceiptLookupController::class, 'store'])
            ->middleware('permission:receipts.import')
            ->name('receipts.lookup.store');
        Route::get('receipts/{receipt}/invoice', [ReceiptController::class, 'create'])
            ->middleware('permission:invoices.create')
            ->name('receipts.invoice.create');
        Route::post('receipts/{receipt}/invoice', [ReceiptController::class, 'store'])
            ->middleware('permission:invoices.create')
            ->name('receipts.invoice.store');
    });

    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/create', [InvoiceController::class, 'create'])
            ->middleware('permission:invoices.create')
            ->name('invoices.create');
        Route::post('invoices', [InvoiceController::class, 'store'])
            ->middleware('permission:invoices.create')
            ->name('invoices.store');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
            ->middleware('permission:invoices.download')
            ->name('invoices.pdf');
        Route::get('invoices/{invoice}/xml', [InvoiceController::class, 'xml'])
            ->middleware('permission:invoices.download')
            ->name('invoices.xml');
        Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
            ->middleware('permission:invoices.cancel')
            ->name('invoices.cancel');
    });

    Route::middleware('permission:customers.view')->group(function () {
        Route::resource('customers', CustomerController::class)
            ->except(['show'])
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:customers.manage');
    });

    Route::middleware('permission:products.view')->group(function () {
        Route::resource('products', ProductController::class)
            ->except(['show'])
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:products.manage');
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::middleware('permission:roles.manage')->group(function () {
        Route::resource('roles', RoleController::class)->except(['show']);
    });

    Route::middleware('permission:api-clients.manage')->group(function () {
        Route::resource('api-clients', ApiClientController::class)->except(['show', 'edit']);
    });
});

require __DIR__.'/settings.php';
