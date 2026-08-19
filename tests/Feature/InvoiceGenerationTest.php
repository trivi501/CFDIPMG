<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentReceipt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function billingUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Facturación');

        return $user;
    }

    protected function pendingReceipt(): PaymentReceipt
    {
        return PaymentReceipt::create([
            'external_id' => 'REC-1',
            'source_system' => 'sistema-cobranza',
            'customer_payload' => ['name' => 'Cliente de prueba'],
            'amount' => 500,
            'currency' => 'MXN',
            'status' => 'pending',
            'received_at' => now(),
        ]);
    }

    protected function invoiceFormPayload(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'payment_form' => '03',
            'payment_method' => 'PUE',
            'use' => 'G03',
            'items' => [[
                'description' => 'Servicio de prueba',
                'sat_product_key' => '80141600',
                'sat_unit_key' => 'E48',
                'quantity' => 1,
                'unit_price' => 500,
            ]],
        ];
    }

    public function test_it_generates_an_invoice_and_marks_the_receipt_as_invoiced(): void
    {
        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['id' => 'fact_cus_123'], 201),
            'www.facturapi.io/v2/invoices' => Http::response([
                'id' => 'fact_inv_123',
                'uuid' => 'uuid-1234',
                'series' => 'F',
                'folio_number' => 1,
                'total' => 580,
            ], 201),
        ]);

        $customer = Customer::create([
            'legal_name' => 'Cliente de Prueba',
            'rfc' => 'XAXX010101000',
            'tax_system' => '616',
            'zip' => '01000',
        ]);

        $receipt = $this->pendingReceipt();

        $response = $this->actingAs($this->billingUser())
            ->post("/receipts/{$receipt->id}/invoice", $this->invoiceFormPayload($customer));

        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'payment_receipt_id' => $receipt->id,
            'status' => 'valid',
            'uuid' => 'uuid-1234',
        ]);

        $this->assertSame('invoiced', $receipt->fresh()->status);
        $this->assertSame('fact_cus_123', $customer->fresh()->facturapi_customer_id);
    }

    public function test_it_records_a_failed_invoice_when_facturapi_rejects_the_request(): void
    {
        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['message' => 'RFC inválido'], 400),
        ]);

        $customer = Customer::create([
            'legal_name' => 'Cliente de Prueba',
            'rfc' => 'XAXX010101000',
            'tax_system' => '616',
            'zip' => '01000',
        ]);

        $receipt = $this->pendingReceipt();

        $this->actingAs($this->billingUser())
            ->post("/receipts/{$receipt->id}/invoice", $this->invoiceFormPayload($customer));

        $this->assertDatabaseHas('invoices', [
            'payment_receipt_id' => $receipt->id,
            'status' => 'failed',
        ]);

        $this->assertSame('failed', $receipt->fresh()->status);
    }

    public function test_a_user_without_invoices_create_permission_cannot_generate_invoices(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $customer = Customer::create([
            'legal_name' => 'Cliente de Prueba',
            'rfc' => 'XAXX010101000',
            'tax_system' => '616',
            'zip' => '01000',
        ]);

        $receipt = $this->pendingReceipt();

        $this->actingAs($user)
            ->post("/receipts/{$receipt->id}/invoice", $this->invoiceFormPayload($customer))
            ->assertForbidden();
    }
}
