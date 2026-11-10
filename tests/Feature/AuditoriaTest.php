<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Beneficiario;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function usuarioCon(string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole($rol);

        return $usuario;
    }

    public function test_registra_alta_modificacion_y_eliminacion_con_valores_antes_y_despues(): void
    {
        $this->actingAs($usuario = User::factory()->create());
        Auditoria::query()->delete();

        $beneficiario = Beneficiario::create(['nombre' => 'Juan Perez']);
        $beneficiario->save(); // sin cambios: no registra nada
        $beneficiario->update(['nombre' => 'Juan Pérez López']);
        $beneficiario->delete();

        $movimientos = Auditoria::where('tabla', 'beneficiarios')->orderBy('id')->get();

        $this->assertSame(['alta', 'modificación', 'eliminación'], $movimientos->pluck('accion')->all());
        $this->assertSame(['nombre' => 'Juan Perez'], $movimientos[1]->antes);
        $this->assertSame(['nombre' => 'Juan Pérez López'], $movimientos[1]->despues);
        $this->assertSame($usuario->id, $movimientos[1]->user_id);
        $this->assertSame((string) $beneficiario->id, $movimientos[1]->auditable_id);
        $this->assertSame(1, $movimientos->pluck('lote')->unique()->count());
    }

    public function test_registra_cambios_masivos_una_sola_vez_y_oculta_contrasenas(): void
    {
        $usuario = User::factory()->create();
        $beneficiario = Beneficiario::create(['nombre' => 'María']);
        Auditoria::query()->delete();

        DB::table('beneficiarios')->where('id', $beneficiario->id)->update(['nombre' => 'María López']);
        $usuario->forceFill(['password' => bcrypt('nueva')])->save();

        $masivo = Auditoria::where('accion', 'cambio masivo')->sole();
        $this->assertSame('beneficiarios', $masivo->tabla);
        $this->assertStringContainsString("'María López'", $masivo->despues['instrucción']);

        // El UPDATE del modelo no se duplica como cambio masivo.
        $contrasena = Auditoria::where('tabla', 'users')->sole();
        $this->assertSame('modificación', $contrasena->accion);
        $this->assertSame(['password' => '[oculto]'], $contrasena->despues);
    }

    public function test_registra_la_descarga_del_pdf_de_una_factura(): void
    {
        $customer = Customer::create(['legal_name' => 'Cliente', 'rfc' => 'XAXX010101000', 'tax_system' => '616', 'zip' => '01000']);
        $factura = Invoice::create([
            'customer_id' => $customer->id, 'facturapi_invoice_id' => 'fact_inv_1', 'uuid' => 'uuid-1',
            'series' => 'F', 'folio' => '25', 'status' => 'valid', 'total' => 100, 'issued_at' => now(),
        ]);
        $usuario = $this->usuarioCon('Facturación');
        Auditoria::query()->delete();
        Http::fake(['*' => Http::response('%PDF-1.4', 200)]);

        $this->actingAs($usuario)->get("/invoices/{$factura->id}/pdf")->assertOk();

        $descarga = Auditoria::where('accion', 'descarga')->sole();
        $this->assertSame('invoices', $descarga->tabla);
        $this->assertSame((string) $factura->id, $descarga->auditable_id);
        $this->assertSame('PDF de factura F 25', $descarga->descripcion);
        $this->assertSame($usuario->id, $descarga->user_id);
    }

    public function test_el_filtro_de_fechas_usa_la_hora_de_mexico(): void
    {
        $this->withoutVite();
        $admin = $this->usuarioCon('Admin');
        Auditoria::query()->delete();
        // 03:00 UTC del 10 de noviembre son las 21:00 del 9 de noviembre en Ciudad de México.
        DB::table('auditoria')->insert(['accion' => 'alta', 'tabla' => 'products', 'created_at' => '2026-11-10 03:00:00']);

        $this->actingAs($admin)->get('/auditoria?desde=2026-11-09&hasta=2026-11-09')
            ->assertInertia(fn ($page) => $page->where('registros.total', 1));
        $this->actingAs($admin)->get('/auditoria?desde=2026-11-10')
            ->assertInertia(fn ($page) => $page->where('registros.total', 0));
    }

    public function test_solo_el_rol_admin_ve_la_bitacora(): void
    {
        $this->actingAs($this->usuarioCon('Facturación'))->get('/auditoria')->assertForbidden();
        $this->actingAs($this->usuarioCon('Consulta'))->get('/auditoria')->assertForbidden();

        $this->withoutVite();
        $this->actingAs($this->usuarioCon('Admin'))->get('/auditoria')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auditoria/index'));
    }
}
