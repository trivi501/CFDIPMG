<?php

namespace Tests\Feature\Api;

use App\Models\ApiClient;
use App\Models\PaymentReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(): array
    {
        return [
            'external_id' => 'REC-100',
            'source_system' => 'sistema-cobranza',
            'amount' => 1000.50,
            'concept' => 'Pago de prueba',
            'customer' => ['name' => 'Cliente de prueba', 'email' => 'cliente@example.com'],
        ];
    }

    public function test_it_rejects_requests_without_an_api_key(): void
    {
        $response = $this->postJson('/api/v1/receipts', $this->validPayload());

        $response->assertStatus(401);
    }

    public function test_it_rejects_requests_with_an_invalid_api_key(): void
    {
        $response = $this->postJson('/api/v1/receipts', $this->validPayload(), [
            'X-Api-Key' => 'not-a-real-key',
        ]);

        $response->assertStatus(401);
    }

    public function test_it_creates_a_pending_receipt_with_a_valid_api_key(): void
    {
        $result = ApiClient::generate('Sistema externo de prueba');

        $response = $this->postJson('/api/v1/receipts', $this->validPayload(), [
            'X-Api-Key' => $result['plainTextKey'],
        ]);

        $response->assertStatus(201)->assertJson(['status' => 'pending']);

        $this->assertDatabaseHas('payment_receipts', [
            'external_id' => 'REC-100',
            'source_system' => 'sistema-cobranza',
            'status' => 'pending',
        ]);
    }

    public function test_it_is_idempotent_for_the_same_external_id_and_source_system(): void
    {
        $result = ApiClient::generate('Sistema externo de prueba');
        $headers = ['X-Api-Key' => $result['plainTextKey']];

        $this->postJson('/api/v1/receipts', $this->validPayload(), $headers)->assertStatus(201);
        $this->postJson('/api/v1/receipts', $this->validPayload(), $headers)->assertStatus(200);

        $this->assertSame(1, PaymentReceipt::count());
    }

    public function test_it_updates_the_api_clients_last_used_timestamp(): void
    {
        $result = ApiClient::generate('Sistema externo de prueba');

        $this->assertNull($result['client']->last_used_at);

        $this->postJson('/api/v1/receipts', $this->validPayload(), [
            'X-Api-Key' => $result['plainTextKey'],
        ])->assertStatus(201);

        $this->assertNotNull($result['client']->fresh()->last_used_at);
    }
}
