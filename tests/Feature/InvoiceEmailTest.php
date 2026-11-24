<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function usuario(string $rol = 'Facturación'): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    protected function factura(string $status = 'valid', ?string $correo = null): Invoice
    {
        $customer = Customer::create([
            'legal_name' => 'Cliente de Prueba',
            'rfc' => 'CPR010101AAA',
            'tax_system' => '601',
            'zip' => '01000',
            'email' => $correo,
        ]);

        return Invoice::create([
            'customer_id' => $customer->id,
            'facturapi_invoice_id' => 'fact_123',
            'uuid' => 'uuid-123',
            'series' => 'A',
            'folio' => '77',
            'status' => $status,
            'total' => 116,
            'issued_at' => now(),
        ]);
    }

    public function test_envia_la_factura_a_los_correos_y_lo_anota_en_la_bitacora(): void
    {
        Http::fake(['*/invoices/fact_123/email' => Http::response(['ok' => true])]);
        $invoice = $this->factura(correo: 'cliente@correo.com');

        $this->actingAs($this->usuario())
            ->post("/invoices/{$invoice->id}/email", ['emails' => 'cliente@correo.com, conta@correo.com; cliente@correo.com'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/invoices/fact_123/email')
            && $request['email'] === ['cliente@correo.com', 'conta@correo.com']);

        $registro = Auditoria::where('accion', 'envío')->firstOrFail();
        $this->assertSame($invoice->id, (int) $registro->auditable_id);
        $this->assertSame(['para' => 'cliente@correo.com, conta@correo.com'], $registro->despues);
    }

    public function test_guarda_el_correo_en_el_cliente_si_no_tenia(): void
    {
        Http::fake(['*/invoices/fact_123/email' => Http::response(['ok' => true])]);
        $invoice = $this->factura();

        $this->actingAs($this->usuario())->post("/invoices/{$invoice->id}/email", [
            'emails' => 'nuevo@correo.com',
            'guardar_en_cliente' => true,
        ]);

        $this->assertSame('nuevo@correo.com', $invoice->customer?->refresh()->email);
    }

    public function test_valida_los_correos_y_no_llama_a_facturapi(): void
    {
        Http::fake();
        $invoice = $this->factura();
        $user = $this->usuario();

        $this->actingAs($user)->post("/invoices/{$invoice->id}/email", ['emails' => ''])
            ->assertSessionHasErrors(['emails' => 'Escribe al menos un correo.']);

        $this->actingAs($user)->post("/invoices/{$invoice->id}/email", ['emails' => 'bien@correo.com, mal-correo'])
            ->assertSessionHasErrors(['emails.1' => '"mal-correo" no es un correo válido.']);

        Http::assertNothingSent();
        $this->assertSame(0, Auditoria::where('accion', 'envío')->count());
    }

    public function test_si_facturapi_falla_avisa_y_no_lo_anota_como_enviado(): void
    {
        Http::fake(['*/invoices/fact_123/email' => Http::response(['message' => 'Error de envío'], 500)]);
        $invoice = $this->factura();

        $this->actingAs($this->usuario())
            ->post("/invoices/{$invoice->id}/email", ['emails' => 'cliente@correo.com'])
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame(0, Auditoria::where('accion', 'envío')->count());
    }

    public function test_solo_facturas_vigentes_y_con_permiso_de_descarga(): void
    {
        Http::fake();
        $cancelada = $this->factura('canceled');

        $this->actingAs($this->usuario())
            ->post("/invoices/{$cancelada->id}/email", ['emails' => 'cliente@correo.com'])
            ->assertRedirect();

        $this->actingAs($this->usuario('Apoyos'))
            ->post("/invoices/{$cancelada->id}/email", ['emails' => 'cliente@correo.com'])
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
