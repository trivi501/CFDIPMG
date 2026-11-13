<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\ArticuloFacturaCompra;
use App\Models\Beneficiario;
use App\Models\Customer;
use App\Models\FacturaCompra;
use App\Models\Invoice;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApoyoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function apoyosUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Apoyos');

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
            'use' => 'S01',
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
        $user = $this->apoyosUser();

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
        Storage::fake('local');

        $file = UploadedFile::fake()->create('solicitud.pdf', 100, 'application/pdf');

        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload(['solicitud_recibo' => $file]));

        $apoyo = Apoyo::firstOrFail();

        $this->assertNotNull($apoyo->solicitud_recibo_path);
        Storage::disk('local')->assertExists($apoyo->solicitud_recibo_path);
    }

    public function test_updating_an_apoyo_replaces_its_detalles(): void
    {
        $user = $this->apoyosUser();
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
        $user = $this->apoyosUser();
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

        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload([
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

        $response = $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload([
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
        $user = $this->apoyosUser();
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
        $user = $this->apoyosUser();
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
        $owner = $this->apoyosUser();
        $other = $this->apoyosUser();
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

    /** Apoyo del usuario con beneficiario, fecha y área dados. */
    protected function apoyoCon(User $user, string $beneficiario, string $fecha, ?int $areaId = null): Apoyo
    {
        $this->actingAs($user)->post('/apoyos', $this->payload([
            'beneficiario_id' => Beneficiario::create(['nombre' => $beneficiario])->id,
            'fecha' => $fecha,
        ]))->assertSessionHasNoErrors();

        $apoyo = Apoyo::latest('id')->firstOrFail();
        Apoyo::whereKey($apoyo->id)->update(['area_id' => $areaId]);

        return $apoyo;
    }

    /** @return list<int> */
    protected function idsListados(User $user, array $filtros): array
    {
        $ids = [];
        $this->actingAs($user)->get('/apoyos?'.http_build_query($filtros))
            ->assertOk()
            ->assertInertia(function ($page) use (&$ids) {
                $ids = collect($page->toArray()['props']['apoyos']['data'])->pluck('id')->sort()->values()->all();
            });

        return $ids;
    }

    public function test_filtra_apoyos_por_busqueda_fechas_y_estatus_combinados(): void
    {
        $user = $this->apoyosUser();
        $ana = $this->apoyoCon($user, 'Ana Torres', '2026-08-01');
        $anaSep = $this->apoyoCon($user, 'Ana Ruiz', '2026-09-15');
        $luis = $this->apoyoCon($user, 'Luis Gómez', '2026-09-20');
        Apoyo::whereKey($anaSep->id)->update(['estatus' => Apoyo::ESTATUS_APROBADO]);

        $this->assertSame([$ana->id, $anaSep->id], $this->idsListados($user, ['buscar' => 'ana']));
        // La persona que apoya (María López) es la misma en los tres.
        $this->assertCount(3, $this->idsListados($user, ['buscar' => 'maría lópez']));
        $this->assertSame([$anaSep->id, $luis->id], $this->idsListados($user, ['desde' => '2026-09-01']));
        $this->assertSame([$ana->id], $this->idsListados($user, ['hasta' => '2026-08-31']));
        $this->assertSame([$anaSep->id], $this->idsListados($user, [
            'buscar' => 'ana', 'desde' => '2026-09-01', 'hasta' => '2026-09-30', 'estatus' => 'aprobado',
        ]));
        $this->assertSame([], $this->idsListados($user, ['buscar' => 'nadie']));
    }

    public function test_el_filtro_de_area_solo_aplica_para_quien_ve_todas_las_areas(): void
    {
        $admin = $this->adminUser();
        $areaA = Area::create(['nombre' => 'Desarrollo Social', 'tipo' => 'secretaria']);
        $areaB = Area::create(['nombre' => 'Regidor Juan', 'tipo' => 'regiduria']);
        $user = $this->apoyosUser();
        $enA = $this->apoyoCon($user, 'Ana', '2026-09-01', $areaA->id);
        $enB = $this->apoyoCon($user, 'Luis', '2026-09-02', $areaB->id);

        $this->assertSame([$enA->id], $this->idsListados($admin, ['area' => $areaA->id]));
        // Quien solo ve lo suyo no puede usar el filtro para ver otras áreas, ni se le reduce lo que ve.
        $this->assertSame([$enA->id, $enB->id], $this->idsListados($user, ['area' => $areaA->id]));

        $this->actingAs($user)->get('/apoyos')->assertInertia(fn ($page) => $page->where('areas', []));
        $this->actingAs($admin)->get('/apoyos')->assertInertia(fn ($page) => $page->has('areas', 2));
    }

    public function test_un_rango_de_fechas_invertido_no_se_acepta(): void
    {
        $this->actingAs($this->apoyosUser())
            ->get('/apoyos?desde=2026-09-30&hasta=2026-09-01')
            ->assertSessionHasErrors('hasta');
    }

    public function test_facturar_is_only_allowed_once_an_apoyo_is_aprobado(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload());
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
        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload());
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
        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload());
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

        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload([
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
        $capturista = $this->apoyosUser();
        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($capturista)->patch("/apoyos/{$apoyo->id}/aprobar")->assertForbidden();
    }

    public function test_el_rol_apoyos_factura_sus_apoyos_solo_cuando_ya_estan_aprobados(): void
    {
        $capturista = $this->apoyosUser();
        $otro = $this->apoyosUser();
        $admin = $this->adminUser();

        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();
        $this->fakeFacturapi();

        // Preparado: todavía no se puede facturar, y aprobar sigue siendo del Admin.
        $this->actingAs($capturista)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload());
        $this->assertSame('preparado', $apoyo->refresh()->estatus);
        $this->actingAs($capturista)->patch("/apoyos/{$apoyo->id}/aprobar")->assertForbidden();

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/aprobar");

        // Aprobado: otro usuario de Apoyos no puede facturar un apoyo ajeno; el dueño sí.
        $this->actingAs($otro)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload())->assertForbidden();
        $this->actingAs($capturista)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('facturado', $apoyo->refresh()->estatus);
        $this->assertNotNull($apoyo->invoice_id);
    }

    public function test_facturar_todo_del_rol_apoyos_solo_toma_sus_apoyos_aprobados(): void
    {
        $capturista = $this->apoyosUser();
        $otro = $this->apoyosUser();
        $admin = $this->adminUser();

        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $this->actingAs($otro)->post('/apoyos', $this->payload());
        [$mio, $ajeno] = Apoyo::orderBy('id')->get()->all();
        $this->actingAs($admin)->post('/apoyos/aprobar-todo');
        $this->fakeFacturapi();

        $this->actingAs($capturista)->post('/apoyos/facturar-todo', ['payment_form' => '01'])->assertRedirect();

        $this->assertSame('facturado', $mio->refresh()->estatus);
        $this->assertSame('aprobado', $ajeno->refresh()->estatus);
    }

    public function test_la_migracion_da_facturar_al_rol_apoyos_de_un_servidor_en_uso(): void
    {
        $apoyos = Role::findByName('Apoyos');
        $apoyos->revokePermissionTo('apoyos.facturar'); // como estaba antes de este cambio

        (include database_path('migrations/2026_11_13_120000_grant_apoyos_facturar_to_apoyos_role.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($apoyos->fresh()->hasPermissionTo('apoyos.facturar'));
        $this->assertSame(['apoyos.facturar', 'apoyos.manage', 'apoyos.view'], $apoyos->fresh()->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_aprobar_todo_aprueba_solo_los_preparados(): void
    {
        $capturista = $this->apoyosUser();
        $admin = $this->adminUser();

        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        [$primero, $segundo, $tercero] = Apoyo::orderBy('id')->get()->all();
        $this->actingAs($admin)->patch("/apoyos/{$tercero->id}/rechazar");

        $this->actingAs($admin)->get('/apoyos')->assertInertia(
            fn ($page) => $page->where('pendientes', ['preparados' => 2, 'aprobados' => 0])
        );

        $this->actingAs($admin)->post('/apoyos/aprobar-todo')->assertRedirect();

        $this->assertSame('aprobado', $primero->refresh()->estatus);
        $this->assertSame('aprobado', $segundo->refresh()->estatus);
        $this->assertSame('rechazado', $tercero->refresh()->estatus);

        $this->actingAs($admin)->get('/apoyos')->assertInertia(
            fn ($page) => $page->where('pendientes', ['preparados' => 0, 'aprobados' => 2])
        );
    }

    public function test_aprobar_y_facturar_solo_los_seleccionados(): void
    {
        $capturista = $this->apoyosUser();
        $admin = $this->adminUser();

        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        [$primero, $segundo, $tercero] = Apoyo::orderBy('id')->get()->all();

        $this->actingAs($admin)->post('/apoyos/aprobar-todo', ['ids' => [$primero->id, $segundo->id]]);

        $this->assertSame('aprobado', $primero->refresh()->estatus);
        $this->assertSame('aprobado', $segundo->refresh()->estatus);
        $this->assertSame('preparado', $tercero->refresh()->estatus);

        $this->fakeFacturapi();

        $this->actingAs($admin)->post('/apoyos/facturar-todo', [
            'payment_form' => '01',
            'ids' => [$segundo->id, $tercero->id],
        ])->assertRedirect();

        // El tercero no estaba aprobado y el primero no se marcó: solo se factura el segundo.
        $this->assertSame('aprobado', $primero->refresh()->estatus);
        $this->assertSame('facturado', $segundo->refresh()->estatus);
        $this->assertSame('preparado', $tercero->refresh()->estatus);
    }

    public function test_aprobar_seleccionados_ignora_apoyos_que_el_usuario_no_ve(): void
    {
        $dueno = $this->apoyosUser();
        $this->actingAs($dueno)->post('/apoyos', $this->payload());
        $ajeno = Apoyo::firstOrFail();

        // Usuario sin "ver todo" pero con permiso de aprobar.
        $aprobador = User::factory()->create();
        $aprobador->givePermissionTo('apoyos.view', 'apoyos.approve');

        $this->actingAs($aprobador)->post('/apoyos/aprobar-todo', ['ids' => [$ajeno->id]]);

        $this->assertSame('preparado', $ajeno->refresh()->estatus);
    }

    public function test_solo_quien_puede_aprobar_usa_aprobar_todo(): void
    {
        $capturista = $this->apoyosUser();
        $facturacion = User::factory()->create();
        $facturacion->assignRole('Facturación');

        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($capturista)->post('/apoyos/aprobar-todo')->assertForbidden();
        $this->actingAs($facturacion)->post('/apoyos/aprobar-todo')->assertForbidden();
        $this->assertSame('preparado', $apoyo->refresh()->estatus);
    }

    public function test_los_roles_de_apoyos_y_facturacion_estan_separados(): void
    {
        $capturista = $this->apoyosUser();
        $facturacion = User::factory()->create();
        $facturacion->assignRole('Facturación');

        $this->actingAs($capturista)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($capturista)->get('/invoices')->assertForbidden();
        $this->actingAs($capturista)->get('/receipts')->assertForbidden();

        $this->actingAs($facturacion)->get('/apoyos/create')->assertForbidden();
        $this->actingAs($facturacion)->post('/apoyos', $this->payload())->assertForbidden();
        $this->actingAs($facturacion)->get("/apoyos/{$apoyo->id}/edit")->assertForbidden();
        $this->actingAs($facturacion)->patch("/apoyos/{$apoyo->id}/aprobar")->assertForbidden();
        $this->actingAs($facturacion)->get("/apoyos/{$apoyo->id}")->assertOk();

        $this->actingAs($this->adminUser())->patch("/apoyos/{$apoyo->id}/aprobar");
        $this->fakeFacturapi();

        $this->actingAs($facturacion)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload())
            ->assertRedirect();
        $this->assertSame('facturado', $apoyo->refresh()->estatus);
    }

    /** Un apoyo capturado por $owner y facturado por el admin. */
    protected function apoyoFacturado(User $owner, string $uuid = 'uuid-apoyo-1'): Apoyo
    {
        $this->actingAs($owner)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::latest('id')->firstOrFail();

        $customer = Customer::firstOrCreate(
            ['rfc' => 'XAXX010101000'],
            ['legal_name' => 'PUBLICO EN GENERAL', 'tax_system' => '616', 'zip' => '01000'],
        );

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'facturapi_invoice_id' => 'fact_'.$uuid,
            'uuid' => $uuid,
            'series' => 'F',
            'folio' => (string) $apoyo->id,
            'total' => 600,
            'status' => 'valid',
        ]);
        $apoyo->update(['estatus' => Apoyo::ESTATUS_FACTURADO, 'invoice_id' => $invoice->id]);

        return $apoyo;
    }

    public function test_el_rol_apoyos_descarga_el_pdf_y_xml_de_sus_apoyos_facturados(): void
    {
        $owner = $this->apoyosUser();
        $other = $this->apoyosUser();
        $apoyo = $this->apoyoFacturado($owner);

        Http::fake([
            'www.facturapi.io/v2/invoices/*/pdf' => Http::response('%PDF-1.4 apoyo', 200),
            'www.facturapi.io/v2/invoices/*/xml' => Http::response('<cfdi:Comprobante/>', 200),
        ]);

        $this->actingAs($owner)->get("/apoyos/{$apoyo->id}/factura/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('<cfdi:Comprobante/>', $this->actingAs($owner)->get("/apoyos/{$apoyo->id}/factura/xml")->assertOk()->getContent());

        // No tiene invoices.download: la ruta general de facturas le sigue cerrada.
        $this->actingAs($owner)->get("/invoices/{$apoyo->invoice_id}/pdf")->assertForbidden();
        // Ni puede bajar la factura de un apoyo ajeno.
        $this->actingAs($other)->get("/apoyos/{$apoyo->id}/factura/xml")->assertForbidden();
        $this->actingAs($owner)->get("/apoyos/{$apoyo->id}/factura/zip")->assertNotFound();
    }

    public function test_un_apoyo_sin_factura_timbrada_no_tiene_pdf_ni_xml(): void
    {
        $owner = $this->apoyosUser();
        $this->actingAs($owner)->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();

        $this->actingAs($owner)->get("/apoyos/{$apoyo->id}/factura/pdf")->assertNotFound();
    }

    public function test_descarga_masiva_de_xml_solo_incluye_los_apoyos_seleccionados_que_el_usuario_ve(): void
    {
        $owner = $this->apoyosUser();
        $other = $this->apoyosUser();
        $mio1 = $this->apoyoFacturado($owner, 'uuid-mio-1');
        $mio2 = $this->apoyoFacturado($owner, 'uuid-mio-2');
        $mio3 = $this->apoyoFacturado($owner, 'uuid-mio-3');
        $ajeno = $this->apoyoFacturado($other, 'uuid-ajeno');

        Http::fake(['www.facturapi.io/v2/invoices/*/xml' => Http::response('<cfdi:Comprobante/>', 200)]);

        $response = $this->actingAs($owner)
            ->get('/apoyos/facturas-xml?'.http_build_query(['ids' => [$mio1->id, $mio2->id, $ajeno->id]]))
            ->assertOk();

        $zip = new \ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $nombres = collect(range(0, $zip->numFiles - 1))->map(fn (int $i) => $zip->getNameIndex($i))->sort()->values()->all();
        $zip->close();

        // El tercero no se marcó y el ajeno no lo ve: ninguno entra al ZIP.
        $this->assertSame(["F{$mio1->id}-uuid-mio-1.xml", "F{$mio2->id}-uuid-mio-2.xml"], $nombres);
        $this->assertNotNull($mio3->invoice_id);

        $this->actingAs($owner)->get('/apoyos/facturas-xml?'.http_build_query(['ids' => [$ajeno->id]]))->assertNotFound();
    }

    public function test_a_user_can_view_their_own_apoyo_but_not_someone_elses(): void
    {
        $owner = $this->apoyosUser();
        $other = $this->apoyosUser();
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
        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();
        $apoyo->update(['estatus' => 'aprobado']);

        $this->fakeFacturapi();

        $this->actingAs($admin)->post('/apoyos/facturar-todo', [
            'payment_form' => '01',
            'payment_method' => 'PUE',
            'use' => 'S01',
        ])->assertRedirect();

        $apoyo->refresh();
        $this->assertSame('facturado', $apoyo->estatus);
        $this->assertNotNull($apoyo->invoice_id);
        $this->assertDatabaseHas('customers', ['rfc' => 'XAXX010101000']);
    }

    /** @return list<array<string, mixed>> */
    protected function facturasEnviadas(): array
    {
        return Http::recorded(fn (HttpRequest $request) => str_ends_with($request->url(), '/v2/invoices'))
            ->map(fn (array $par) => $par[0]->data())
            ->values()
            ->all();
    }

    public function test_un_apoyo_se_factura_como_egreso_pue_con_clave_93161700_y_unidad_c62(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload());
        $apoyo = Apoyo::firstOrFail();
        $apoyo->update(['estatus' => 'aprobado']);

        $this->fakeFacturapi();

        $this->actingAs($admin)->patch("/apoyos/{$apoyo->id}/facturar", $this->facturarPayload([
            'rfc' => 'PEJU800101AB1',
            'legal_name' => 'Juan Pérez',
            'tax_system' => '612',
            'use' => 'G03',
            'payment_method' => 'PPD',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        [$factura] = $this->facturasEnviadas();
        $this->assertSame('E', $factura['type']);
        $this->assertSame('PUE', $factura['payment_method']);
        $this->assertSame('G03', $factura['use']);
        foreach ($factura['items'] as $item) {
            $this->assertSame('93161700', $item['product']['product_key']);
            $this->assertSame('C62', $item['product']['unit_key']);
        }
    }

    public function test_facturar_todo_usa_g03_si_el_regimen_lo_permite_y_s01_si_no(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload());
        $this->actingAs($this->apoyosUser())->post('/apoyos', $this->payload());
        [$conRfc, $sinRfc] = Apoyo::orderBy('id')->get()->all();
        $conRfc->update(['estatus' => 'aprobado', 'rfc' => 'PEJU800101AB1']);
        $sinRfc->update(['estatus' => 'aprobado']);
        Customer::create(['rfc' => 'PEJU800101AB1', 'legal_name' => 'Juan Pérez', 'tax_system' => '612', 'zip' => '98600']);

        $this->fakeFacturapi();

        $this->actingAs($admin)->post('/apoyos/facturar-todo', ['payment_form' => '03'])
            ->assertSessionHasNoErrors()->assertRedirect();

        $usos = collect($this->facturasEnviadas())->pluck('use')->sort()->values()->all();
        $this->assertSame(['G03', 'S01'], $usos);
        $this->assertSame(['E'], collect($this->facturasEnviadas())->pluck('type')->unique()->values()->all());
        $this->assertSame(2, Apoyo::where('estatus', 'facturado')->count());
    }
}
