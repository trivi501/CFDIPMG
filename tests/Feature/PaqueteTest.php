<?php

namespace Tests\Feature;

use App\Models\ArticuloFacturaCompra;
use App\Models\FacturaCompra;
use App\Models\Paquete;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaqueteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(string $rol = 'Facturación'): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function factura(): FacturaCompra
    {
        return FacturaCompra::create([
            'xml_path' => 'facturas-compra/fake.xml',
            'emisor' => 'Proveedor',
            'fecha' => '2026-11-01',
            'total' => 100,
        ]);
    }

    private function articulo(FacturaCompra $factura, string $descripcion = 'SOPA'): ArticuloFacturaCompra
    {
        return $factura->articulos()->create([
            'descripcion' => $descripcion,
            'cantidad_total' => 10,
            'costo_unidad' => 10,
            'iva' => 0,
            'cantidad_disponible' => 10,
        ]);
    }

    public function test_consulta_puede_ver_pero_no_administrar_paquetes(): void
    {
        $consulta = $this->usuario('Consulta');

        $this->actingAs($consulta)->get('/paquetes')->assertOk();
        $this->actingAs($consulta)->get('/paquetes/create')->assertForbidden();
        $this->actingAs($consulta)->post('/paquetes', ['nombre' => 'X'])->assertForbidden();
    }

    public function test_crea_un_paquete_con_articulos_de_una_factura(): void
    {
        $factura = $this->factura();
        $sopa = $this->articulo($factura, 'SOPA');
        $aceite = $this->articulo($factura, 'ACEITE');

        $this->actingAs($this->usuario())->post('/paquetes', [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Despensa básica',
            'articulos' => [
                ['articulo_factura_compra_id' => $sopa->id, 'cantidad' => 2],
                ['articulo_factura_compra_id' => $aceite->id, 'cantidad' => 1],
            ],
        ])->assertRedirect('/paquetes');

        $paquete = Paquete::with('articulos')->firstOrFail();

        $this->assertSame($factura->id, $paquete->factura_compra_id);
        $this->assertSame(['SOPA', 'ACEITE'], $paquete->articulos->pluck('descripcion')->all());
        $this->assertEquals(2, (float) $paquete->articulos[0]->cantidad);
    }

    public function test_rechaza_articulos_de_otra_factura(): void
    {
        $factura = $this->factura();
        $otraFactura = $this->factura();
        $sopa = $this->articulo($factura, 'SOPA');
        $aceiteDeOtra = $this->articulo($otraFactura, 'ACEITE');

        $this->actingAs($this->usuario())->post('/paquetes', [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Mezclado',
            'articulos' => [
                ['articulo_factura_compra_id' => $sopa->id, 'cantidad' => 2],
                ['articulo_factura_compra_id' => $aceiteDeOtra->id, 'cantidad' => 1],
            ],
        ])->assertSessionHasErrors('articulos.1.articulo_factura_compra_id');

        $this->assertSame(0, Paquete::count());
    }

    public function test_actualizar_tampoco_permite_articulos_de_otra_factura(): void
    {
        $factura = $this->factura();
        $sopa = $this->articulo($factura);
        $aceiteDeOtra = $this->articulo($this->factura(), 'ACEITE');
        $paquete = Paquete::create(['factura_compra_id' => $factura->id, 'nombre' => 'Despensa']);
        $paquete->articulos()->create(['articulo_factura_compra_id' => $sopa->id, 'descripcion' => 'SOPA', 'cantidad' => 2]);

        $this->actingAs($this->usuario())->put("/paquetes/{$paquete->id}", [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Despensa',
            'articulos' => [['articulo_factura_compra_id' => $aceiteDeOtra->id, 'cantidad' => 3]],
        ])->assertSessionHasErrors('articulos.0.articulo_factura_compra_id');

        $this->assertSame(['SOPA'], $paquete->articulos()->pluck('descripcion')->all());
    }

    public function test_crear_un_paquete_no_aparta_inventario(): void
    {
        $factura = $this->factura();
        $sopa = $this->articulo($factura);

        $this->actingAs($this->usuario())->post('/paquetes', [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Despensa',
            'articulos' => [['articulo_factura_compra_id' => $sopa->id, 'cantidad' => 2]],
        ]);

        $this->assertEquals(10, (float) $sopa->refresh()->cantidad_disponible);
    }

    public function test_rechaza_paquete_sin_factura_sin_articulos_con_nombre_repetido_o_articulo_duplicado(): void
    {
        $factura = $this->factura();
        $sopa = $this->articulo($factura);
        $usuario = $this->usuario();
        Paquete::create(['factura_compra_id' => $factura->id, 'nombre' => 'Despensa']);

        $this->actingAs($usuario)->post('/paquetes', [
            'nombre' => 'Sin factura',
            'articulos' => [['articulo_factura_compra_id' => $sopa->id, 'cantidad' => 1]],
        ])->assertSessionHasErrors('factura_compra_id');

        $this->actingAs($usuario)->post('/paquetes', [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Sin artículos',
            'articulos' => [],
        ])->assertSessionHasErrors('articulos');

        $this->actingAs($usuario)->post('/paquetes', [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Despensa',
            'articulos' => [['articulo_factura_compra_id' => $sopa->id, 'cantidad' => 1]],
        ])->assertSessionHasErrors('nombre');

        $this->actingAs($usuario)->post('/paquetes', [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Duplicado',
            'articulos' => [
                ['articulo_factura_compra_id' => $sopa->id, 'cantidad' => 1],
                ['articulo_factura_compra_id' => $sopa->id, 'cantidad' => 2],
            ],
        ])->assertSessionHasErrors('articulos.0.articulo_factura_compra_id');

        $this->assertSame(1, Paquete::count());
    }

    public function test_actualizar_reemplaza_los_articulos_y_conserva_el_mismo_nombre(): void
    {
        $factura = $this->factura();
        $sopa = $this->articulo($factura, 'SOPA');
        $aceite = $this->articulo($factura, 'ACEITE');
        $paquete = Paquete::create(['factura_compra_id' => $factura->id, 'nombre' => 'Despensa']);
        $paquete->articulos()->create(['articulo_factura_compra_id' => $sopa->id, 'descripcion' => 'SOPA', 'cantidad' => 2]);

        $this->actingAs($this->usuario())->put("/paquetes/{$paquete->id}", [
            'factura_compra_id' => $factura->id,
            'nombre' => 'Despensa',
            'articulos' => [['articulo_factura_compra_id' => $aceite->id, 'cantidad' => 3]],
        ])->assertRedirect('/paquetes');

        $this->assertSame(['ACEITE'], $paquete->articulos()->pluck('descripcion')->all());
    }

    public function test_eliminar_borra_sus_articulos_pero_no_los_de_la_factura(): void
    {
        $factura = $this->factura();
        $sopa = $this->articulo($factura);
        $paquete = Paquete::create(['factura_compra_id' => $factura->id, 'nombre' => 'Despensa']);
        $paquete->articulos()->create(['articulo_factura_compra_id' => $sopa->id, 'descripcion' => 'SOPA', 'cantidad' => 2]);

        $this->actingAs($this->usuario())->delete("/paquetes/{$paquete->id}")->assertRedirect('/paquetes');

        $this->assertDatabaseCount('paquetes', 0);
        $this->assertDatabaseCount('paquete_articulos', 0);
        $this->assertDatabaseCount('articulos_factura_compra', 1);
    }

    public function test_los_formularios_de_apoyo_reciben_los_paquetes(): void
    {
        $factura = $this->factura();
        $sopa = $this->articulo($factura);
        $paquete = Paquete::create(['factura_compra_id' => $factura->id, 'nombre' => 'Despensa']);
        $paquete->articulos()->create(['articulo_factura_compra_id' => $sopa->id, 'descripcion' => 'SOPA', 'cantidad' => 2]);

        $this->actingAs($this->usuario())->get('/apoyos/create')->assertInertia(
            fn ($page) => $page->where('paquetes.0.nombre', 'Despensa')
                ->where('paquetes.0.factura_compra_id', $factura->id)
                ->where('paquetes.0.articulos.0.descripcion', 'SOPA')
        );
    }
}
