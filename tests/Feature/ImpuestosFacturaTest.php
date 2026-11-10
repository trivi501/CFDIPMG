<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentReceipt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImpuestosFacturaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['id' => 'fact_cus_1'], 201),
            'www.facturapi.io/v2/invoices' => Http::response(['id' => 'fact_inv_1', 'uuid' => 'uuid-1', 'total' => 100], 201),
        ]);
    }

    private function usuario(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Facturación');

        return $user;
    }

    private function cliente(): Customer
    {
        return Customer::create(['legal_name' => 'Empresa', 'rfc' => 'EMP010101AAA', 'tax_system' => '601', 'zip' => '98600']);
    }

    private function facturaAbierta(array $extra): void
    {
        $this->actingAs($this->usuario())->post('/invoices', [
            'customer_id' => $this->cliente()->id,
            'payment_form' => '03',
            'payment_method' => 'PUE',
            'use' => 'G03',
            'items' => [['description' => 'Servicio', 'sat_product_key' => '80141600', 'sat_unit_key' => 'E48', 'quantity' => 1, 'unit_price' => 100]],
            ...$extra,
        ])->assertRedirect();
    }

    /**
     * @return array<string, mixed>
     */
    private function productoEnviado(): array
    {
        $enviado = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/invoices'))->first();
        $this->assertNotNull($enviado);

        return $enviado[0]['items'][0]['product'];
    }

    public function test_sin_iva_ni_isr_va_como_no_objeto_de_impuesto(): void
    {
        $this->facturaAbierta(['iva' => false]);

        $producto = $this->productoEnviado();
        $this->assertSame('01', $producto['taxability']);
        $this->assertArrayNotHasKey('taxes', $producto);
    }

    public function test_con_iva_lleva_iva_al_16(): void
    {
        $this->facturaAbierta(['iva' => true]);

        $producto = $this->productoEnviado();
        $this->assertSame('02', $producto['taxability']);
        $this->assertSame([['type' => 'IVA', 'rate' => 0.16, 'factor' => 'Tasa']], $producto['taxes']);
    }

    public function test_la_factura_abierta_lleva_iva_si_no_se_indica(): void
    {
        $this->facturaAbierta([]);

        $this->assertSame('IVA', $this->productoEnviado()['taxes'][0]['type']);
    }

    public function test_retencion_de_isr_sin_iva(): void
    {
        $this->facturaAbierta(['iva' => false, 'isr_rate' => '1.25']);

        $producto = $this->productoEnviado();
        $this->assertSame('02', $producto['taxability']);
        $this->assertSame([['type' => 'ISR', 'rate' => 0.0125, 'factor' => 'Tasa', 'withholding' => true]], $producto['taxes']);
    }

    public function test_rechaza_una_tasa_de_isr_fuera_de_rango(): void
    {
        $this->actingAs($this->usuario())->post('/invoices', [
            'customer_id' => $this->cliente()->id,
            'payment_form' => '03',
            'payment_method' => 'PUE',
            'use' => 'G03',
            'isr_rate' => '50',
            'items' => [['description' => 'Servicio', 'sat_product_key' => '80141600', 'sat_unit_key' => 'E48', 'quantity' => 1, 'unit_price' => 100]],
        ])->assertSessionHasErrors('isr_rate');

        Http::assertNothingSent();
    }

    public function test_un_recibo_del_api_va_sin_iva_si_no_se_indica(): void
    {
        $recibo = PaymentReceipt::create([
            'external_id' => 'PAG-1',
            'source_system' => PaymentReceipt::SOURCE_PAGOS_MUNICIPALES,
            'customer_payload' => ['name' => 'Juan'],
            'amount' => 100,
            'currency' => 'MXN',
            'status' => 'pending',
            'received_at' => now(),
        ]);

        $this->actingAs($this->usuario())->post("/receipts/{$recibo->id}/invoice", [
            'customer_id' => $this->cliente()->id,
            'payment_form' => '01',
            'payment_method' => 'PUE',
            'use' => 'G03',
            'items' => [['description' => 'Predial', 'sat_product_key' => '93161700', 'sat_unit_key' => 'E48', 'quantity' => 1, 'unit_price' => 100]],
        ])->assertRedirect();

        $producto = $this->productoEnviado();
        $this->assertSame('01', $producto['taxability']);
        $this->assertFalse($producto['tax_included']);
    }
}
