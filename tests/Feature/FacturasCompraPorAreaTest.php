<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\ArticuloFacturaCompra;
use App\Models\Beneficiario;
use App\Models\FacturaCompra;
use App\Models\Paquete;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cada regidor (o secretaría) ve solo las facturas de compra de su área, y solo puede usar su
 * inventario. Admin y Facturación (apoyos.view-all) ven todas y asignan el área de las que no tienen.
 */
class FacturasCompraPorAreaTest extends TestCase
{
    use RefreshDatabase;

    private Area $regidorA;

    private Area $regidorB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->regidorA = Area::create(['nombre' => 'Regidor Francisco Solís', 'tipo' => Area::TIPO_REGIDURIA]);
        $this->regidorB = Area::create(['nombre' => 'Regidora Ana Ruiz', 'tipo' => Area::TIPO_REGIDURIA]);
    }

    private function usuario(string $rol, ?Area $area = null): User
    {
        $user = User::factory()->create(['area_id' => $area?->id]);
        $user->assignRole($rol);

        return $user;
    }

    private function factura(?Area $area, string $emisor = 'Proveedor'): FacturaCompra
    {
        Storage::disk('local')->put("facturas-compra/{$emisor}.xml", '<xml/>');

        return FacturaCompra::create([
            'area_id' => $area?->id,
            'xml_path' => "facturas-compra/{$emisor}.xml",
            'emisor' => $emisor,
            'fecha' => '2026-11-01',
            'total' => 100,
        ]);
    }

    private function articulo(FacturaCompra $factura): ArticuloFacturaCompra
    {
        return $factura->articulos()->create([
            'descripcion' => 'DESPENSA',
            'cantidad_total' => 10,
            'costo_unidad' => 10,
            'iva' => 0,
            'cantidad_disponible' => 10,
        ]);
    }

    /** @return list<string> */
    private function emisoresQueVe(User $user, string $url = '/facturas-compra', string $prop = 'facturas'): array
    {
        $emisores = [];
        $this->actingAs($user)->get($url)->assertOk()->assertInertia(function ($page) use (&$emisores, $prop) {
            $emisores = collect($page->toArray()['props'][$prop])->pluck('emisor')->sort()->values()->all();
        });

        return $emisores;
    }

    public function test_cada_regidor_ve_solo_las_facturas_de_su_area(): void
    {
        $this->factura($this->regidorA, 'De A');
        $this->factura($this->regidorB, 'De B');
        $this->factura(null, 'Sin area');

        $this->assertSame(['De A'], $this->emisoresQueVe($this->usuario('Apoyos', $this->regidorA)));
        $this->assertSame(['De B'], $this->emisoresQueVe($this->usuario('Apoyos', $this->regidorB)));
        // Las que no tienen área solo las ven Admin y Facturación.
        $this->assertSame([], $this->emisoresQueVe($this->usuario('Apoyos')));
        $this->assertSame(['De A', 'De B', 'Sin area'], $this->emisoresQueVe($this->usuario('Admin')));
        $this->assertSame(['De A', 'De B', 'Sin area'], $this->emisoresQueVe($this->usuario('Facturación')));

        // Lo mismo al capturar un apoyo: solo se ofrecen las facturas de su área.
        $this->assertSame(['De A'], $this->emisoresQueVe($this->usuario('Apoyos', $this->regidorA), '/apoyos/create', 'facturasDisponibles'));
    }

    public function test_en_un_area_privada_como_cabildo_cada_quien_ve_solo_lo_suyo(): void
    {
        $cabildo = Area::create(['nombre' => 'Cabildo', 'tipo' => Area::TIPO_REGIDURIA, 'privada' => true]);
        $regidor = $this->usuario('Apoyos', $cabildo);
        $asistenteDeOtro = $this->usuario('Apoyos', $cabildo);

        $suya = $this->factura($cabildo, 'Del regidor');
        $suya->update(['user_id' => $regidor->id]);
        $ajena = $this->factura($cabildo, 'Del otro');
        $ajena->update(['user_id' => $asistenteDeOtro->id]);
        $paquete = Paquete::create(['factura_compra_id' => $ajena->id, 'nombre' => 'Paquete del otro']);

        // Facturas de compra y su XML.
        $this->assertSame(['Del regidor'], $this->emisoresQueVe($regidor));
        $this->assertSame(['Del otro'], $this->emisoresQueVe($asistenteDeOtro));
        // El buscador "Factura" al capturar un apoyo y al armar un paquete.
        $this->assertSame(['Del regidor'], $this->emisoresQueVe($regidor, '/apoyos/create', 'facturasDisponibles'));
        $this->assertSame(['Del otro'], $this->emisoresQueVe($asistenteDeOtro, '/apoyos/create', 'facturasDisponibles'));
        $this->assertSame(['Del regidor'], $this->emisoresQueVe($regidor, '/paquetes/create'));
        $this->actingAs($regidor)->get("/facturas-compra/{$ajena->id}/xml")->assertForbidden();
        // Paquetes.
        $this->actingAs($regidor)->get("/paquetes/{$paquete->id}/edit")->assertForbidden();

        // Apoyos: el del otro integrante de Cabildo no se lista ni se abre.
        $this->actingAs($asistenteDeOtro)->post('/apoyos', [
            'fecha' => '2026-11-20',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'Juan'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'María'])->id,
            'detalles' => [['cantidad' => 1, 'articulo' => 'Despensa', 'costo_unidad' => 10, 'iva' => 0]],
        ])->assertSessionHasNoErrors();
        $apoyo = Apoyo::sole();
        $this->assertSame($cabildo->id, $apoyo->area_id);

        $this->actingAs($regidor)->get('/apoyos')->assertInertia(fn ($page) => $page->where('apoyos.total', 0));
        $this->actingAs($regidor)->get("/apoyos/{$apoyo->id}")->assertForbidden();
        $this->actingAs($asistenteDeOtro)->get('/apoyos')->assertInertia(fn ($page) => $page->where('apoyos.total', 1));
    }

    public function test_un_area_no_privada_sigue_compartiendo_entre_sus_integrantes(): void
    {
        $secretaria = Area::create(['nombre' => 'Desarrollo Social', 'tipo' => Area::TIPO_SECRETARIA]);
        $uno = $this->usuario('Apoyos', $secretaria);
        $otro = $this->usuario('Apoyos', $secretaria);
        $factura = $this->factura($secretaria, 'De la secretaría');
        $factura->update(['user_id' => $uno->id]);

        $this->assertSame(['De la secretaría'], $this->emisoresQueVe($otro));
    }

    public function test_la_migracion_marca_cabildo_como_privada_y_la_pantalla_de_areas_la_cambia(): void
    {
        $cabildo = Area::create(['nombre' => ' CABILDO ', 'tipo' => Area::TIPO_REGIDURIA]);
        $otra = Area::create(['nombre' => 'Cabildo Abierto 2025', 'tipo' => Area::TIPO_SECRETARIA]);

        DB::table('areas')->whereRaw('LOWER(TRIM(nombre)) = ?', ['cabildo'])->update(['privada' => true]);
        $codigo = (string) file_get_contents(database_path('migrations/2026_11_23_090000_add_privada_to_areas_table.php'));
        $this->assertStringContainsString("whereRaw('LOWER(TRIM(nombre)) = ?', ['cabildo'])", $codigo);

        $this->assertTrue($cabildo->fresh()->privada);
        $this->assertFalse($otra->fresh()->privada);

        // Desde la pantalla de Áreas se apaga y se prende con la casilla.
        $admin = $this->usuario('Admin');
        $this->actingAs($admin)->put("/areas/{$cabildo->id}", ['nombre' => 'Cabildo', 'tipo' => Area::TIPO_REGIDURIA])->assertRedirect();
        $this->assertFalse($cabildo->fresh()->privada);
        $this->actingAs($admin)->put("/areas/{$cabildo->id}", ['nombre' => 'Cabildo', 'tipo' => Area::TIPO_REGIDURIA, 'privada' => 'on'])->assertRedirect();
        $this->assertTrue($cabildo->fresh()->privada);
    }

    public function test_al_subir_una_factura_queda_del_area_de_quien_la_sube(): void
    {
        $user = $this->usuario('Apoyos', $this->regidorA);
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-08-01T12:00:00" Total="100.00">
            <cfdi:Emisor Rfc="XAXX010101000" Nombre="Proveedor"/>
            <cfdi:Conceptos>
                <cfdi:Concepto ClaveProdServ="50202306" Cantidad="10" ClaveUnidad="H87" Descripcion="Despensa" ValorUnitario="10.00" Importe="100.00"/>
            </cfdi:Conceptos>
            <cfdi:Complemento><tfd:TimbreFiscalDigital UUID="22222222-2222-2222-2222-222222222222" FechaTimbrado="2026-08-01T12:00:01"/></cfdi:Complemento>
        </cfdi:Comprobante>
        XML;

        $this->actingAs($user)->postJson('/facturas-compra', ['xml' => UploadedFile::fake()->createWithContent('f.xml', $xml)])->assertOk();

        $factura = FacturaCompra::sole();
        $this->assertSame($user->id, $factura->user_id);
        $this->assertSame($this->regidorA->id, $factura->area_id);
    }

    public function test_no_se_descarga_el_xml_ni_se_usa_el_inventario_de_otra_area(): void
    {
        $deB = $this->factura($this->regidorB, 'De B');
        $articulo = $this->articulo($deB);
        $regidorA = $this->usuario('Apoyos', $this->regidorA);

        $this->actingAs($regidorA)->get("/facturas-compra/{$deB->id}/xml")->assertForbidden();

        $this->actingAs($regidorA)->post('/apoyos', [
            'fecha' => '2026-11-20',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'Juan'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'María'])->id,
            'detalles' => [[
                'articulo_factura_compra_id' => $articulo->id,
                'cantidad' => 2,
                'articulo' => 'DESPENSA',
                'costo_unidad' => 10,
                'iva' => 0,
            ]],
        ])->assertSessionHasErrors('detalles');

        $this->assertEquals(10, (float) $articulo->fresh()->cantidad_disponible);
    }

    public function test_los_paquetes_de_otra_area_no_se_ven_ni_se_editan(): void
    {
        $deB = $this->factura($this->regidorB, 'De B');
        $paquete = Paquete::create(['factura_compra_id' => $deB->id, 'nombre' => 'Paquete de B']);
        $regidorA = $this->usuario('Apoyos', $this->regidorA);

        $this->actingAs($regidorA)->get('/paquetes')->assertInertia(fn ($page) => $page->where('paquetes', []));
        $this->actingAs($regidorA)->get("/paquetes/{$paquete->id}/edit")->assertForbidden();
        $this->actingAs($regidorA)->delete("/paquetes/{$paquete->id}")->assertForbidden();
        $this->actingAs($regidorA)->post('/paquetes', [
            'factura_compra_id' => $deB->id,
            'nombre' => 'Intento',
            'articulos' => [['articulo_factura_compra_id' => $this->articulo($deB)->id, 'cantidad' => 1]],
        ])->assertSessionHasErrors('factura_compra_id');

        $this->assertModelExists($paquete);
    }

    public function test_el_admin_asigna_el_area_de_una_factura_sin_dueno(): void
    {
        $factura = $this->factura(null, 'Sin area');
        $regidorA = $this->usuario('Apoyos', $this->regidorA);

        $this->actingAs($regidorA)->patch("/facturas-compra/{$factura->id}/area", ['area_id' => $this->regidorA->id])->assertForbidden();
        $this->assertNull($factura->fresh()->area_id);

        $this->actingAs($this->usuario('Admin'))->patch("/facturas-compra/{$factura->id}/area", ['area_id' => $this->regidorA->id])->assertRedirect();

        $this->assertSame(['Sin area'], $this->emisoresQueVe($regidorA));
    }

    public function test_la_migracion_recupera_de_la_bitacora_quien_subio_cada_factura(): void
    {
        $quienSubio = $this->usuario('Apoyos', $this->regidorA);
        $conRastro = $this->factura(null, 'Con rastro');
        $sinRastro = $this->factura(null, 'Sin rastro');
        DB::table('auditoria')->insert([
            'user_id' => $quienSubio->id,
            'accion' => 'alta',
            'tabla' => 'facturas_compra',
            'auditable_type' => FacturaCompra::class,
            'auditable_id' => (string) $conRastro->id,
            'lote' => 'x',
            'created_at' => now(),
        ]);

        $yaConDueno = $this->factura($this->regidorB, 'Ya con dueño');
        $otro = $this->usuario('Apoyos', $this->regidorB);
        $yaConDueno->update(['user_id' => $otro->id]);
        DB::table('auditoria')->insert([
            'user_id' => $quienSubio->id, 'accion' => 'alta', 'tabla' => 'facturas_compra',
            'auditable_id' => (string) $yaConDueno->id, 'lote' => 'x', 'created_at' => now(),
        ]);

        // Las columnas ya existen en la base de pruebas: se corre solo el relleno de la migración.
        (include database_path('migrations/2026_11_20_100000_add_user_and_area_to_facturas_compra_table.php'))->rellenarDesdeLaBitacora();

        $this->assertSame($quienSubio->id, $conRastro->fresh()->user_id);
        $this->assertSame($this->regidorA->id, $conRastro->fresh()->area_id);
        $this->assertNull($sinRastro->fresh()->user_id);
        $this->assertNull($sinRastro->fresh()->area_id);
        // No pisa facturas que ya tienen dueño.
        $this->assertSame($otro->id, $yaConDueno->fresh()->user_id);
        $this->assertSame($this->regidorB->id, $yaConDueno->fresh()->area_id);
    }
}
