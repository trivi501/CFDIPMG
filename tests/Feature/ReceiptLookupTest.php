<?php

namespace Tests\Feature;

use App\Models\PaymentReceipt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReceiptLookupTest extends TestCase
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

    protected function pagoPayload(array $overrides = []): array
    {
        return array_merge([
            'folio' => 'PAG-000098',
            'fecha' => '2026-07-18 00:57:38',
            'estatus' => 'pagado',
            'tipo_pago' => 'predial_urbano',
            'descripcion' => 'Pago predial urbano',
            'monto' => 5280.86,
            'descuento' => 3893.63,
            'anio_pago' => '2026',
            'contribuyente' => ['nombre' => 'SIN DATO.', 'rfc' => '—'],
            'datos_facturacion' => null,
            'predio' => ['clave_catastral' => '0000000000000'],
            'conceptos' => [
                ['cuenta_codigo' => '4112-01', 'concepto' => 'Predial 2026', 'cantidad' => 1, 'monto' => 234.62],
            ],
            'formas_pago' => [
                ['forma_pago' => 'Efectivo', 'clave_sat' => '1', 'monto' => 5300],
            ],
            'caja' => ['nombre' => 'Tesoreria 1', 'cajero' => 'Cajero'],
        ], $overrides);
    }

    public function test_a_user_without_receipts_import_permission_cannot_look_up_a_folio(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $this->actingAs($user)->get('/receipts/lookup')->assertForbidden();
    }

    public function test_it_creates_a_pending_receipt_from_a_valid_folio_and_redirects_to_invoicing(): void
    {
        Http::fake([
            'localhost:8080/api/pagos/PAG-000098' => Http::response($this->pagoPayload(), 200),
        ]);

        $response = $this->actingAs($this->billingUser())
            ->post('/receipts/lookup', ['folio' => 'PAG-000098']);

        $receipt = PaymentReceipt::where('external_id', 'PAG-000098')->firstOrFail();

        $response->assertRedirect("/receipts/{$receipt->id}/invoice");
        $this->assertSame('pending', $receipt->status);
        $this->assertSame('pagos_municipales', $receipt->source_system);
        $this->assertEquals(5280.86, (float) $receipt->amount);
    }

    public function test_it_shows_an_error_when_the_folio_does_not_exist(): void
    {
        Http::fake([
            'localhost:8080/api/pagos/*' => Http::response(['error' => 'No se encontró un pago con ese folio.'], 404),
        ]);

        $this->actingAs($this->billingUser())
            ->post('/receipts/lookup', ['folio' => 'PAG-000000'])
            ->assertRedirect()
            ->assertSessionHasErrors('folio');

        $this->assertSame(0, PaymentReceipt::count());
    }

    public function test_it_refuses_to_ingest_a_payment_that_is_not_marked_as_paid(): void
    {
        Http::fake([
            'localhost:8080/api/pagos/PAG-000098' => Http::response($this->pagoPayload(['estatus' => 'pendiente']), 200),
        ]);

        $this->actingAs($this->billingUser())
            ->post('/receipts/lookup', ['folio' => 'PAG-000098'])
            ->assertRedirect()
            ->assertSessionHasErrors('folio');

        $this->assertSame(0, PaymentReceipt::count());
    }

    public function test_looking_up_the_same_folio_twice_does_not_create_duplicate_receipts(): void
    {
        Http::fake([
            'localhost:8080/api/pagos/PAG-000098' => Http::response($this->pagoPayload(), 200),
        ]);

        $user = $this->billingUser();

        $this->actingAs($user)->post('/receipts/lookup', ['folio' => 'PAG-000098']);
        $this->actingAs($user)->post('/receipts/lookup', ['folio' => 'PAG-000098']);

        $this->assertSame(1, PaymentReceipt::count());
    }
}
