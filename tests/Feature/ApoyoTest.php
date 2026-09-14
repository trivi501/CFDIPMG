<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\ArticuloFacturaCompra;
use App\Models\Beneficiario;
use App\Models\FacturaCompra;
use App\Models\Invoice;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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

    protected function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

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
            'detalles' => [
                ['cantidad' => 2, 'articulo' => 'Despensa', 'costo_unidad' => 250, 'iva' => 0],
                ['cantidad' => 1, 'articulo' => 'Medicamento', 'costo_unidad' => 100, 'iva' => 16],
            ],
        ], $overrides);
    }

    protected function facturarPayload(array $overrides = []): array
    {
        return array_merge([
            'rfc' => 'XAXX010101000',
            'legal_name' => 'PUBLICO EN GENERAL',
            'tax_system' => '616',
            'zip' => '01000',
            'payment_form' => '01',
            'payment_method' => 'PUE',
            'use' => 'G03',
        ], $overrides);
    }

    protected function fakeFacturapi(): void
    {
        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['id' => 'fact_cus_apoyo'], 201),
            'www.facturapi.io/v2/invoices' => Http::response([
                'id' => 'fact_inv_apoyo',
                'uuid' => 'uuid-apoyo-1',
                'series' => 'F',
                'folio_number' => 1,
                'total' => 600,
            ], 201),
        ]);
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
        $user = $this->billingUser();

        $response = $this->actingAs($user)->post('/apoyos', $this->payload());

        $response->assertRedirect('/apoyos');

        $apoyo = Apoyo::firstOrFail();

        // 2*250=500 (iva 0) + 1*100=100 (iva 16) => sub_total 600, iva 16, total 616
        $this->assertEquals(600, (float) $apoyo->sub_total);
        $this->assertEquals(16, (float) $apoyo->iva);
        $this->assertEquals(616, (float) $apoyo->monto_total);
        $this->assertSame(2, $apoyo->detalles()->count());
        $this->assertSame($user->id, $apoyo->user_id);
        $this->assertSame('preparado', $apoyo->estatus);
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
        $user = $this->billingUser();
        $this->actingAs($user)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $newPayload = $this->payload([
            'beneficiario_id' => $apoyo->beneficiario_id,
            'persona_apoya_id' => $apoyo->persona_apoya_id,
            'detalles' => [
                ['cantidad' => 1, 'articulo' => 'Solo un artículo', 'costo_unidad' => 300, 'iva' => 0],
            ],
        ]);

        $this->actingAs($user)->put("/apoyos/{$apoyo->id}", $newPayload)->assertRedirect('/apoyos');

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
        $user = $this->billingUser();
        $this->actingAs($user)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($user)->delete("/apoyos/{$apoyo->id}")->assertRedirect('/apoyos');

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
        $user = $this->billingUser();
        $articulo = $this->articuloFacturaCompra(cantidadTotal: 10);

        $this->actingAs($user)->post('/apoyos', $this->payload([
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

        $this->actingAs($user)->put("/apoyos/{$apoyo->id}", $this->payload([
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
        $user = $this->billingUser();
        $articulo = $this->articuloFacturaCompra(cantidadTotal: 10);

        $this->actingAs($user)->post('/apoyos', $this->payload([
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

        $this->actingAs($user)->delete("/apoyos/{$apoyo->id}")->assertRedirect('/apoyos');

        $this->assertEquals(10, (float) $articulo->refresh()->cantidad_disponible);
    }

    public function test_a_user_only_sees_their_own_apoyos_but_admin_sees_all(): void
    {
        $owner = $this->billingUser();
        $other = $this->billingUser();
        $admin = $this->adminUser();

        $this->actingAs($owner)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($other)->get('/apoyos')->assertInertia(
            fn ($page) => $page->where('apoyos.data', [])
        );

        $this->actingAs($owner)->get('/apoyos')->assertInertia(
            fn ($page) => $page->where('apoyos.total', 1)
        );

        $this->actingAs($admin)->get('/apoyos')->assertInertia(
            fn ($page) => $page->where('apoyos.total', 1)
        );

        $this->actingAs($other)->get("/apoyos/{$apoyo->id}/edit")->assertForbidden();
        $this->actingAs($other)->put("/apoyos/{$apoyo->id}", $this->payload([
            'beneficiario_id' => $apoyo->beneficiario_id,
            'persona_apoya_id' => $apoyo->persona_apoya_id,
        ]))->assertForbidden();
        $this->actingAs($other)->delete("/apoyos/{$apoyo->id}")->assertForbidden();

        $this->actingAs($admin)->get("/apoyos/{$apoyo->id}/edit")->assertOk();
    }

    public function test_facturar_is_only_allowed_once_an_apoyo_is_aprobado(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload());
        $this->assertSame('preparado', $apoyo->refresh()->estatus);

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/aprobar");
        $this->assertSame('aprobado', $apoyo->refresh()->estatus);

        $this->fakeFacturapi();

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload())
            ->assertRedirect();

        $apoyo->refresh();
        $this->assertSame('facturado', $apoyo->estatus);
        $this->assertNotNull($apoyo->invoice_id);

        $invoice = Invoice::firstOrFail();
        $this->assertSame('valid', $invoice->status);
        $this->assertSame('uuid-apoyo-1', $invoice->uuid);
        $this->assertSame($apoyo->invoice_id, $invoice->id);
    }

    public function test_facturar_uses_the_rfc_captured_on_the_apoyo_when_present(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();
        $apoyo->update(['estatus' => 'aprobado']);

        $this->fakeFacturapi();

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload([
            'rfc' => 'XEXX010101000',
            'legal_name' => 'Juan Pérez',
        ]))->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'rfc' => 'XEXX010101000',
            'legal_name' => 'Juan Pérez',
        ]);
    }

    public function test_facturar_leaves_the_apoyo_aprobado_when_facturapi_fails(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();
        $apoyo->update(['estatus' => 'aprobado']);

        Http::fake([
            'www.facturapi.io/v2/customers' => Http::response(['message' => 'RFC inválido'], 400),
        ]);

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload())
            ->assertRedirect();

        $this->assertSame('aprobado', $apoyo->refresh()->estatus);
        $this->assertNull($apoyo->invoice_id);
    }

    public function test_rechazar_only_works_from_preparado_and_releases_stock(): void
    {
        $admin = $this->adminUser();
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

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/rechazar");

        $this->assertSame('rechazado', $apoyo->refresh()->estatus);
        $this->assertEquals(10, (float) $articulo->refresh()->cantidad_disponible);

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/aprobar");
        $this->assertSame('rechazado', $apoyo->refresh()->estatus);
    }

    public function test_only_admin_can_approve_or_facturar_an_apoyo(): void
    {
        $billing = $this->billingUser();
        $this->actingAs($billing)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($billing)->patch("/apoyos/{$apoyo->id}/aprobar")->assertForbidden();
    }

    public function test_a_user_can_view_their_own_apoyo_but_not_someone_elses(): void
    {
        $owner = $this->billingUser();
        $other = $this->billingUser();
        $admin = $this->adminUser();

        $this->actingAs($owner)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($owner)->get("/apoyos/{$apoyo->id}")->assertOk();
        $this->actingAs($admin)->get("/apoyos/{$apoyo->id}")->assertOk();
        $this->actingAs($other)->get("/apoyos/{$apoyo->id}")->assertForbidden();
    }

    public function test_admin_can_bulk_facturar_all_aprobado_apoyos(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($this->billingUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();
        $apoyo->update(['estatus' => 'aprobado']);

        $this->fakeFacturapi();

        $this->actingAs($admin)->post('/apoyos/facturar-todo', [
            'payment_form' => '01',
            'payment_method' => 'PUE',
            'use' => 'G03',
        ])->assertRedirect();

        $apoyo->refresh();
        $this->assertSame('facturado', $apoyo->estatus);
        $this->assertNotNull($apoyo->invoice_id);
        $this->assertDatabaseHas('customers', ['rfc' => 'XAXX010101000']);
    }
}
