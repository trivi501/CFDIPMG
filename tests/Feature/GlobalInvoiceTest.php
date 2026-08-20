<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentReceipt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GlobalInvoiceTest extends TestCase
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

    protected function receipt(array $overrides = []): PaymentReceipt
    {
        return PaymentReceipt::create(array_merge([
            'external_id' => 'REC-'.random_int(1000, 999999),
            'source_system' => 'sistema-cobranza',
            'customer_payload' => [],
            'amount' => 100,
            'currency' => 'MXN',
            'status' => 'pending',
            'payment_date' => '2026-08-15',
            'received_at' => now(),
        ], $overrides));
    }

    public function test_a_user_without_invoices_create_permission_cannot_access_the_global_invoice_flow(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $this->actingAs($user)->get('/invoices/global')->assertForbidden();
        $this->actingAs($user)->post('/invoices/global', ['month' => 8, 'year' => 2026, 'payment_form' => '01', 'payment_method' => 'PUE'])
            ->assertForbidden();
    }

    public function test_the_preview_only_includes_unbilled_receipts_from_the_selected_month(): void
    {
        $inPeriodPending = $this->receipt(['external_id' => 'A', 'payment_date' => '2026-08-01', 'amount' => 100]);
        $inPeriodFailed = $this->receipt(['external_id' => 'B', 'payment_date' => '2026-08-20', 'amount' => 50, 'status' => 'failed']);
        $this->receipt(['external_id' => 'C', 'payment_date' => '2026-07-31', 'amount' => 999]); // outside the month
        $this->receipt(['external_id' => 'D', 'payment_date' => '2026-08-10', 'amount' => 999, 'status' => 'invoiced']); // already billed

        $response = $this->actingAs($this->billingUser())->get('/invoices/global?month=8&year=2026');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('invoices/global')
            ->has('receipts', 2)
            ->where('total', 150)
            ->where('receipts.0.external_id', $inPeriodPending->external_id)
            ->where('receipts.1.external_id', $inPeriodFailed->external_id));
    }

    public function test_it_generates_one_invoice_covering_every_eligible_receipt_and_marks_them_invoiced(): void
    {
        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['id' => 'fact_cus_public'], 201),
            'www.facturapi.io/v2/invoices' => Http::response([
                'id' => 'fact_inv_global',
                'uuid' => 'uuid-global-1',
                'series' => 'G',
                'folio_number' => 1,
                'total' => 150,
            ], 201),
        ]);

        $a = $this->receipt(['external_id' => 'A', 'payment_date' => '2026-08-01', 'amount' => 100]);
        $b = $this->receipt(['external_id' => 'B', 'payment_date' => '2026-08-20', 'amount' => 50, 'status' => 'failed']);

        $response = $this->actingAs($this->billingUser())->post('/invoices/global', [
            'month' => 8,
            'year' => 2026,
            'payment_form' => '01',
            'payment_method' => 'PUE',
        ]);

        $invoice = Invoice::where('is_global', true)->firstOrFail();

        $response->assertRedirect("/invoices/{$invoice->id}");
        $this->assertSame('valid', $invoice->status);
        $this->assertSame(8, $invoice->period_month);
        $this->assertSame(2026, $invoice->period_year);
        $this->assertNull($invoice->payment_receipt_id);

        $this->assertSame('invoiced', $a->fresh()->status);
        $this->assertSame($invoice->id, $a->fresh()->invoice_id);
        $this->assertSame('invoiced', $b->fresh()->status);
        $this->assertSame($invoice->id, $b->fresh()->invoice_id);

        $this->assertDatabaseHas('customers', [
            'rfc' => Customer::RFC_PUBLICO_GENERAL,
            'facturapi_customer_id' => 'fact_cus_public',
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://www.facturapi.io/v2/invoices'
            && $request['use'] === 'S01'
            && $request['global']['periodicity'] === 'month'
            && $request['global']['months'] === '08'
            && $request['global']['year'] === 2026
            && $request['items'][0]['product']['price'] === 150.0
            && $request['items'][0]['product']['tax_included'] === true);
    }

    public function test_it_refuses_to_generate_an_empty_global_invoice(): void
    {
        $response = $this->actingAs($this->billingUser())->post('/invoices/global', [
            'month' => 8,
            'year' => 2026,
            'payment_form' => '01',
            'payment_method' => 'PUE',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, Invoice::count());
        Http::assertNothingSent();
    }

    public function test_cancelling_a_global_invoice_reverts_every_receipt_it_covered(): void
    {
        Http::fake([
            'www.facturapi.io/v2/invoices/*' => Http::response(['id' => 'fact_inv_global', 'status' => 'canceled'], 200),
        ]);

        $customer = Customer::create([
            'legal_name' => 'PUBLICO EN GENERAL',
            'rfc' => Customer::RFC_PUBLICO_GENERAL,
            'tax_system' => '616',
            'zip' => '01000',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'facturapi_invoice_id' => 'fact_inv_global',
            'status' => 'valid',
            'total' => 150,
            'issued_at' => now(),
            'is_global' => true,
            'period_month' => 8,
            'period_year' => 2026,
        ]);

        $a = $this->receipt(['status' => 'invoiced', 'invoice_id' => $invoice->id]);
        $b = $this->receipt(['status' => 'invoiced', 'invoice_id' => $invoice->id]);

        $this->actingAs($this->billingUser())->post("/invoices/{$invoice->id}/cancel")->assertRedirect();

        $this->assertSame('canceled', $invoice->fresh()->status);
        $this->assertSame('canceled', $a->fresh()->status);
        $this->assertSame('canceled', $b->fresh()->status);
    }
}
