<?php

use App\Http\Controllers\Api\ReceiptIngestController;
use Illuminate\Support\Facades\Route;

Route::middleware('api.key')->prefix('v1')->group(function () {
    Route::post('/receipts', [ReceiptIngestController::class, 'store']);
    Route::get('/receipts/{external_id}', [ReceiptIngestController::class, 'show']);
});
