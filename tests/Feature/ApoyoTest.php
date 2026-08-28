<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\ArticuloFacturaCompra;
use App\Models\Beneficiario;
use App\Models\FacturaCompra;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApoyoTest extends TestCase
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

    protected function payload(array $overrides = []): array
    {
        $beneficiario = Beneficiario::create(['nombre' => 'Juan Pérez']);
        $personaApoya = PersonaApoya::create(['nombre' => 'María López']);

        return array_merge([
            'fecha' => '2026-08-01',
            'beneficiario_id' => $beneficiario->id,
            'persona_apoya_id' => $personaApoya->id,
            'preparado' => false,
            'facturado' => false,
            'detalles' => [
                ['cantidad' => 2, 'articulo' => 'Despensa', 'costo_unidad' => 250, 'iva' => 0],
                ['cantidad' => 1, 'articulo' => 'Medicamento', 'costo_unidad' => 100, 'iva' => 16],
            ],
        ], $overrides);
    }

    public function test_apoyos_view_and_manage_are_gated_separately(): void
    {
        $noRole = User::factory()->create();
        $consulta = User::factory()->create();
        $consulta->assignRole('Consulta');

        $this->actingAs($noRole)->get('/apoyos')->assertForbidden();
        $this->actingAs($consulta)->get('/apoyos')->assertOk();
        $this->actingAs($consulta)->get('/apoyos/create')->assertForbidden();
    }

    public function test_it_creates_an_apoyo_with_detalles_and_computes_totals(): void
    {
        $response = $this->actingAs($this->billingUser())->post('/apoyos', $this->payload());

        $response->assertRedirect('/apoyos');

        $apoyo = Apoyo::firstOrFail();

        // 2*250=500 (iva 0) + 1*100=100 (iva 16) => sub_total 600, iva 16, total 616
        $this->assertEquals(600, (float) $apoyo->sub_total);
        $this->assertEquals(16, (float) $apoyo->iva);
        $this->assertEquals(616, (float) $apoyo->monto_total);
        $this->assertSame(2, $apoyo->detalles()->count());
    }

    public function test_it_stores_the_uploaded_solicitud_recibo_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('solicitud.pdf', 100, 'application/pdf');

        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload(['solicitud_recibo' => $file]));

        $apoyo = Apoyo::firstOrFail();

        $this->assertNotNull($apoyo->solicitud_recibo_path);
        Storage::disk('public')->assertExists($apoyo->solicitud_recibo_path);
    }

    public function test_updating_an_apoyo_replaces_its_detalles(): void
    {
        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $newPayload = $this->payload([
            'beneficiario_id' => $apoyo->beneficiario_id,
            'persona_apoya_id' => $apoyo->persona_apoya_id,
            'detalles' => [
                ['cantidad' => 1, 'articulo' => 'Solo un artículo', 'costo_unidad' => 300, 'iva' => 0],
            ],
        ]);

        $this->actingAs($this->billingUser())->put("/apoyos/{$apoyo->id}", $newPayload)->assertRedirect('/apoyos');

        $apoyo->refresh();
        $this->assertSame(1, $apoyo->detalles()->count());
        $this->assertEquals(300, (float) $apoyo->sub_total);
        $this->assertEquals(300, (float) $apoyo->monto_total);
    }

    public function test_a_user_without_apoyos_manage_cannot_create_or_delete(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $this->actingAs($user)->post('/apoyos', $this->payload())->assertForbidden();

        $apoyo = Apoyo::create([
            'fecha' => '2026-08-01',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'X'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'Y'])->id,
        ]);

        $this->actingAs($user)->delete("/apoyos/{$apoyo->id}")->assertForbidden();
    }

    public function test_it_deletes_an_apoyo_and_its_detalles(): void
    {
        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($this->billingUser())->delete("/apoyos/{$apoyo->id}")->assertRedirect('/apoyos');

        $this->assertDatabaseMissing('apoyos', ['id' => $apoyo->id]);
        $this->assertDatabaseCount('detalle_apoyos', 0);
    }

    protected function articuloFacturaCompra(float $cantidadTotal = 10, float $costoUnidad = 50): ArticuloFacturaCompra
    {
        $factura = FacturaCompra::create([
            'xml_path' => 'facturas-compra/fake.xml',
            'emisor' => 'Proveedor de prueba',
            'fecha' => '2026-08-01',
            'total' => $cantidadTotal * $costoUnidad,
        ]);

        return $factura->articulos()->create([
            'descripcion' => 'Despensa',
            'cantidad_total' => $cantidadTotal,
            'costo_unidad' => $costoUnidad,
            'iva' => 0,
            'cantidad_disponible' => $cantidadTotal,
        ]);
    }

    public function test_creating_an_apoyo_from_an_articulo_factura_compra_deducts_available_stock(): void
    {
        $articulo = $this->articuloFacturaCompra(cantidadTotal: 10);

        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload([
            'detalles' => [
                [
                    'articulo_factura_compra_id' => $articulo->id,
                    'cantidad' => 2,
                    'articulo' => $articulo->descripcion,
                    'costo_unidad' => $articulo->costo_unidad,
                    'iva' => 0,
                ],
            ],
        ]))->assertRedirect('/apoyos');

        $this->assertEquals(8, (float) $articulo->refresh()->cantidad_disponible);
    }

    public function test_it_rejects_a_detalle_that_requests_more_than_the_available_stock(): void
    {
        $articulo = $this->articuloFacturaCompra(cantidadTotal: 10);

        $response = $this->actingAs($this->billingUser())->post('/apoyos', $this->payload([
            'detalles' => [
                [
                    'articulo_factura_compra_id' => $articulo->id,
                    'cantidad' => 20,
                    'articulo' => $articulo->descripcion,
                    'costo_unidad' => $articulo->costo_unidad,
                    'iva' => 0,
                ],
            ],
        ]));

        $response->assertSessionHasErrors('detalles');
        $this->assertEquals(10, (float) $articulo->refresh()->cantidad_disponible);
        $this->assertSame(0, Apoyo::count());
    }

    public function test_updating_an_apoyo_adjusts_stock_between_the_old_and_new_quantity(): void
    {
        $articulo = $this->articuloFacturaCompra(cantidadTotal: 10);

        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload([
            'detalles' => [
                [
                    'articulo_factura_compra_id' => $articulo->id,
                    'cantidad' => 2,
                    'articulo' => $articulo->descripcion,
                    'costo_unidad' => $articulo->costo_unidad,
                    'iva' => 0,
                ],
            ],
        ]));

        $apoyo = Apoyo::firstOrFail();
        $this->assertEquals(8, (float) $articulo->refresh()->cantidad_disponible);

        $this->actingAs($this->billingUser())->put("/apoyos/{$apoyo->id}", $this->payload([
            'beneficiario_id' => $apoyo->beneficiario_id,
            'persona_apoya_id' => $apoyo->persona_apoya_id,
            'detalles' => [
                [
                    'articulo_factura_compra_id' => $articulo->id,
                    'cantidad' => 5,
                    'articulo' => $articulo->descripcion,
                    'costo_unidad' => $articulo->costo_unidad,
                    'iva' => 0,
                ],
            ],
        ]))->assertRedirect('/apoyos');

        $this->assertEquals(5, (float) $articulo->refresh()->cantidad_disponible);
    }

    public function test_deleting_an_apoyo_restores_the_stock_it_had_reserved(): void
    {
        $articulo = $this->articuloFacturaCompra(cantidadTotal: 10);

        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload([
            'detalles' => [
                [
                    'articulo_factura_compra_id' => $articulo->id,
                    'cantidad' => 2,
                    'articulo' => $articulo->descripcion,
                    'costo_unidad' => $articulo->costo_unidad,
                    'iva' => 0,
                ],
            ],
        ]));

        $apoyo = Apoyo::firstOrFail();
        $this->assertEquals(8, (float) $articulo->refresh()->cantidad_disponible);

        $this->actingAs($this->billingUser())->delete("/apoyos/{$apoyo->id}")->assertRedirect('/apoyos');

        $this->assertEquals(10, (float) $articulo->refresh()->cantidad_disponible);
    }
}
