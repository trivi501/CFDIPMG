<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceCancellationTest extends TestCase
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

    protected function validInvoice(): Invoice
    {
        $customer = Customer::create([
            'legal_name' => 'Cliente de Prueba',
            'rfc' => 'XAXX010101000',
            'tax_system' => '616',
            'zip' => '01000',
        ]);

        return Invoice::create([
            'customer_id' => $customer->id,
            'facturapi_invoice_id' => 'fact_inv_123',
            'uuid' => 'uuid-1234',
            'status' => 'valid',
            'total' => 580,
            'issued_at' => now(),
        ]);
    }

    public function test_it_cancels_a_valid_invoice_sending_the_motive_as_a_query_parameter(): void
    {
        Http::fake([
            'www.facturapi.io/v2/invoices/fact_inv_123*' => Http::response(['id' => 'fact_inv_123', 'status' => 'canceled'], 200),
        ]);

        $invoice = $this->validInvoice();

        $this->actingAs($this->billingUser())
            ->post("/invoices/{$invoice->id}/cancel")
            ->assertRedirect("/invoices/{$invoice->id}");

        $this->assertSame('canceled', $invoice->fresh()->status);

        // Facturapi rejects the DELETE if "motive" isn't sent as a query param on the URL
        // (a JSON body is silently ignored) — assert it's actually there, not just stubbed.
        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_starts_with($request->url(), 'https://www.facturapi.io/v2/invoices/fact_inv_123')
                && str_contains($request->url(), 'motive=02');
        });
    }

    public function test_it_will_not_cancel_an_invoice_that_is_not_valid(): void
    {
        $invoice = $this->validInvoice();
        $invoice->update(['status' => 'canceled']);

        $this->actingAs($this->billingUser())
            ->post("/invoices/{$invoice->id}/cancel")
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame('canceled', $invoice->fresh()->status);
    }

    public function test_a_user_without_invoices_cancel_permission_cannot_cancel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $invoice = $this->validInvoice();

        $this->actingAs($user)
            ->post("/invoices/{$invoice->id}/cancel")
            ->assertForbidden();
    }
}
