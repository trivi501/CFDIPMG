<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReceiptRequest;
use App\Models\PaymentReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptIngestController extends Controller
{
    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $data = $request->validated();

        $receipt = PaymentReceipt::firstOrCreate(
            [
                'source_system' => $data['source_system'],
                'external_id' => $data['external_id'],
            ],
            [
                'customer_payload' => $data['customer'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'MXN',
                'concept' => $data['concept'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_date' => $data['payment_date'] ?? null,
                'status' => 'pending',
                'received_at' => now(),
            ]
        );

        return response()->json([
            'id' => $receipt->id,
            'status' => $receipt->status,
        ], $receipt->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, string $external_id): JsonResponse
    {
        $receipt = PaymentReceipt::query()
            ->where('external_id', $external_id)
            ->where('source_system', $request->query('source_system'))
            ->with('invoice')
            ->firstOrFail();

        return response()->json([
            'id' => $receipt->id,
            'status' => $receipt->status,
            'invoice' => $receipt->invoice ? [
                'uuid' => $receipt->invoice->uuid,
                'series' => $receipt->invoice->series,
                'folio' => $receipt->invoice->folio,
                'status' => $receipt->invoice->status,
            ] : null,
        ]);
    }
}
