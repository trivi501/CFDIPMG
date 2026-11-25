<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\ArticuloFacturaCompra;
use App\Models\Beneficiario;
use App\Models\FacturaCompra;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApoyoFiltrosYCatalogosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function usuario(string $rol = 'Apoyos'): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    protected function facturaCompra(User $dueno, string $emisor, string $uuid, float $cantidad = 10, float $costo = 100, float $iva = 160): ArticuloFacturaCompra
    {
        $factura = FacturaCompra::create([
            'user_id' => $dueno->id,
            'area_id' => $dueno->area_id,
            'xml_path' => 'facturas-compra/fake.xml',
            'uuid' => $uuid,
            'emisor' => $emisor,
            'fecha' => '2026-11-01',
            'total' => $cantidad * $costo + $iva,
        ]);

        return $factura->articulos()->create([
            'descripcion' => 'Despensa',
            'cantidad_total' => $cantidad,
            'costo_unidad' => $costo,
            'iva' => $iva,
            'cantidad_disponible' => $cantidad,
        ]);
    }

    /** @param array<string, mixed> $extra */
    protected function capturarApoyo(User $user, ArticuloFacturaCompra $articulo, float $cantidad, array $extra = []): Apoyo
    {
        $this->actingAs($user)->post('/apoyos', [
            'fecha' => '2026-11-10',
            'beneficiario_id' => $extra['beneficiario_id'] ?? Beneficiario::create(['nombre' => 'Ana Torres'])->id,
            'persona_apoya_id' => PersonaApoya::firstOrCreate(['nombre' => 'María López'])->id,
            'detalles' => [[
                'articulo_factura_compra_id' => $articulo->id,
                'cantidad' => $cantidad,
                'articulo' => $articulo->descripcion,
                'costo_unidad' => $articulo->costo_unidad,
                'iva' => 0,
            ]],
            ...$extra,
        ])->assertSessionHasNoErrors();

        return Apoyo::latest('id')->firstOrFail();
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

    public function test_busca_apoyos_por_proveedor_o_uuid_de_la_factura_de_compra(): void
    {
        $user = $this->usuario();
        $deAbarrotes = $this->capturarApoyo($user, $this->facturaCompra($user, 'Abarrotes del Norte', 'AAAA-1111'), 1);
        $deFarmacia = $this->capturarApoyo($user, $this->facturaCompra($user, 'Farmacia Central', 'BBBB-2222'), 1);

        $this->assertSame([$deAbarrotes->id], $this->idsListados($user, ['buscar' => 'abarrotes']));
        $this->assertSame([$deFarmacia->id], $this->idsListados($user, ['buscar' => 'bbbb-2222']));
    }

    public function test_filtra_los_apoyos_de_una_factura_de_compra(): void
    {
        $user = $this->usuario();
        $articulo = $this->facturaCompra($user, 'Abarrotes del Norte', 'AAAA-1111');
        $a = $this->capturarApoyo($user, $articulo, 1);
        $b = $this->capturarApoyo($user, $articulo, 2);
        $this->capturarApoyo($user, $this->facturaCompra($user, 'Farmacia Central', 'BBBB-2222'), 1);

        $this->assertSame([$a->id, $b->id], $this->idsListados($user, ['factura' => $articulo->factura_compra_id]));

        $this->actingAs($user)->get('/apoyos?factura='.$articulo->factura_compra_id)
            ->assertInertia(fn ($page) => $page->where('facturaFiltrada.emisor', 'Abarrotes del Norte'));
    }

    public function test_facturas_de_compra_muestra_cuanto_falta_para_liquidarla_y_en_cuantos_apoyos_se_uso(): void
    {
        $user = $this->usuario();
        // 10 × $100 + $160 de IVA = $1,160.
        $articulo = $this->facturaCompra($user, 'Abarrotes del Norte', 'AAAA-1111');
        $this->capturarApoyo($user, $articulo, 3);
        $this->capturarApoyo($user, $articulo, 1);

        $this->actingAs($user)->get('/facturas-compra')->assertInertia(fn ($page) => $page
            ->where('facturas.0.monto_total', 1160)
            // Quedan 6 de 10: $600 + 60% del IVA.
            ->where('facturas.0.monto_por_asignar', 696)
            ->where('facturas.0.monto_asignado', 464)
            ->where('facturas.0.apoyos_count', 2));
    }

    public function test_los_apoyos_viejos_sin_area_toman_la_de_quien_los_capturo(): void
    {
        $area = Area::create(['nombre' => 'Desarrollo Social', 'tipo' => 'secretaria']);
        $user = $this->usuario();
        $apoyo = $this->capturarApoyo($user, $this->facturaCompra($user, 'Abarrotes', 'AAAA-1111'), 1);
        $user->update(['area_id' => $area->id]);
        DB::table('apoyos')->update(['area_id' => null]);

        (require database_path('migrations/2026_11_25_090100_fill_missing_area_on_apoyos_table.php'))->up();

        $this->assertSame($area->id, $apoyo->refresh()->area_id);
        $this->assertSame([$apoyo->id], $this->idsListados($this->usuario('Admin'), ['area' => $area->id]));
    }

    public function test_editar_y_eliminar_una_persona_que_apoya(): void
    {
        $admin = $this->usuario('Admin');
        $persona = PersonaApoya::create(['nombre' => 'María Lopes']);

        $this->actingAs($admin)->get("/personas-apoya/{$persona->id}/edit")
            ->assertInertia(fn ($page) => $page->where('personaApoya.nombre', 'María Lopes'));

        $this->actingAs($admin)->put("/personas-apoya/{$persona->id}", ['nombre' => 'María López'])
            ->assertRedirect('/personas-apoya');
        $this->assertSame('María López', $persona->refresh()->nombre);

        $this->actingAs($admin)->delete("/personas-apoya/{$persona->id}")->assertRedirect('/personas-apoya');
        $this->assertModelMissing($persona);
    }

    public function test_no_elimina_una_persona_o_beneficiario_con_apoyos(): void
    {
        $user = $this->usuario();
        $apoyo = $this->capturarApoyo($user, $this->facturaCompra($user, 'Abarrotes', 'AAAA-1111'), 1);

        $this->actingAs($user)->delete("/personas-apoya/{$apoyo->persona_apoya_id}")
            ->assertInertiaFlash('toast.type', 'error');
        $this->actingAs($user)->delete("/beneficiarios/{$apoyo->beneficiario_id}")
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertModelExists($apoyo->personaApoya);
        $this->assertModelExists($apoyo->beneficiario);
    }

    public function test_el_rfc_y_curp_del_apoyo_se_guardan_en_el_beneficiario(): void
    {
        $user = $this->usuario();
        $articulo = $this->facturaCompra($user, 'Abarrotes', 'AAAA-1111');
        $beneficiario = Beneficiario::create(['nombre' => 'Ana Torres']);

        $this->capturarApoyo($user, $articulo, 1, [
            'beneficiario_id' => $beneficiario->id,
            'rfc' => 'tora800101ab1',
            'curp' => 'TORA800101MNLRRN09',
        ]);

        $beneficiario->refresh();
        $this->assertSame('TORA800101AB1', $beneficiario->rfc);
        $this->assertSame('TORA800101MNLRRN09', $beneficiario->curp);

        // Un apoyo sin RFC no borra el que ya se tenía.
        $this->capturarApoyo($user, $articulo, 1, ['beneficiario_id' => $beneficiario->id, 'curp' => 'TORA800101MNLRRN10']);
        $this->assertSame('TORA800101AB1', $beneficiario->refresh()->rfc);
        $this->assertSame('TORA800101MNLRRN10', $beneficiario->curp);

        // Y la captura de apoyos los manda para llenarlos solos.
        $this->actingAs($user)->get('/apoyos/create')->assertInertia(fn ($page) => $page
            ->where('beneficiarios.0.rfc', 'TORA800101AB1')
            ->where('beneficiarios.0.curp', 'TORA800101MNLRRN10'));
    }

    public function test_el_catalogo_de_beneficiarios_guarda_rfc_y_curp(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)->post('/beneficiarios', ['nombre' => 'Luis Gómez', 'rfc' => ' gomL900202xx1 ', 'curp' => ''])
            ->assertRedirect('/beneficiarios');

        $beneficiario = Beneficiario::where('nombre', 'Luis Gómez')->firstOrFail();
        $this->assertSame('GOML900202XX1', $beneficiario->rfc);
        $this->assertNull($beneficiario->curp);

        $this->actingAs($user)->get('/beneficiarios?search=GOML')
            ->assertInertia(fn ($page) => $page->where('beneficiarios.total', 1));
    }

    public function test_la_migracion_toma_rfc_y_curp_del_ultimo_apoyo(): void
    {
        $user = $this->usuario();
        $articulo = $this->facturaCompra($user, 'Abarrotes', 'AAAA-1111');
        $beneficiario = Beneficiario::create(['nombre' => 'Ana Torres']);
        $this->capturarApoyo($user, $articulo, 1, ['beneficiario_id' => $beneficiario->id, 'rfc' => 'VIEJ800101AA1', 'fecha' => '2026-01-01']);
        $this->capturarApoyo($user, $articulo, 1, ['beneficiario_id' => $beneficiario->id, 'rfc' => 'NUEV800101AA1', 'fecha' => '2026-06-01']);
        DB::table('beneficiarios')->update(['rfc' => null, 'curp' => null]);

        $migracion = require database_path('migrations/2026_11_25_090000_add_rfc_and_curp_to_beneficiarios_table.php');
        $migracion->down();
        $migracion->up();

        $this->assertSame('NUEV800101AA1', $beneficiario->refresh()->rfc);
    }
}
