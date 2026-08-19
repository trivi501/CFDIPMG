<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenInvoiceTest extends TestCase
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

    protected function customer(): Customer
    {
        return Customer::create([
            'legal_name' => 'Cliente de Prueba',
            'rfc' => 'XAXX010101000',
            'tax_system' => '616',
            'zip' => '01000',
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
                'description' => 'Servicio sin recibo registrado',
                'sat_product_key' => '80141600',
                'sat_unit_key' => 'E48',
                'quantity' => 1,
                'unit_price' => 500,
            ]],
        ];
    }

    public function test_a_user_without_invoices_create_permission_cannot_access_the_open_invoice_form(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $this->actingAs($user)->get('/invoices/create')->assertForbidden();
        $this->actingAs($user)
            ->post('/invoices', $this->invoiceFormPayload($this->customer()))
            ->assertForbidden();
    }

    public function test_it_generates_an_open_invoice_with_no_underlying_receipt(): void
    {
        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['id' => 'fact_cus_123'], 201),
            'www.facturapi.io/v2/invoices' => Http::response([
                'id' => 'fact_inv_123',
                'uuid' => 'uuid-open-1',
                'series' => 'F',
                'folio_number' => 1,
                'total' => 580,
            ], 201),
        ]);

        $customer = $this->customer();

        $response = $this->actingAs($this->billingUser())
            ->post('/invoices', $this->invoiceFormPayload($customer));

        $response->assertRedirect();

        $this->assertDatabaseHas('invoices', [
            'payment_receipt_id' => null,
            'customer_id' => $customer->id,
            'status' => 'valid',
            'uuid' => 'uuid-open-1',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://www.facturapi.io/v2/invoices'
            && $request['items'][0]['product']['tax_included'] === false
            && $request['currency'] === 'MXN');
    }

    public function test_it_records_a_failed_open_invoice_when_facturapi_rejects_the_request(): void
    {
        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['message' => 'RFC inválido'], 400),
        ]);

        $customer = $this->customer();

        $this->actingAs($this->billingUser())
            ->post('/invoices', $this->invoiceFormPayload($customer));

        $this->assertDatabaseHas('invoices', [
            'payment_receipt_id' => null,
            'customer_id' => $customer->id,
            'status' => 'failed',
        ]);
    }
}
