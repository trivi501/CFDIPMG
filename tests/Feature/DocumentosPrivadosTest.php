<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\Beneficiario;
use App\Models\FacturaCompra;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentosPrivadosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(string $rol = 'Apoyos'): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    private function apoyoCon(User $dueno): Apoyo
    {
        Storage::disk('local')->put('apoyos/ine.pdf', '%PDF-prueba');

        return Apoyo::create([
            'fecha' => '2026-11-01',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'Juan'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'María'])->id,
            'user_id' => $dueno->id,
            'ine_path' => 'apoyos/ine.pdf',
        ]);
    }

    public function test_los_archivos_nuevos_se_guardan_en_el_disco_privado(): void
    {
        $this->actingAs($this->usuario())->post('/apoyos', [
            'fecha' => '2026-11-01',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'Juan'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'María'])->id,
            'solicitud_recibo' => UploadedFile::fake()->create('solicitud.pdf', 10, 'application/pdf'),
            'ine' => UploadedFile::fake()->create('ine.pdf', 10, 'application/pdf'),
            'detalles' => [['cantidad' => 1, 'articulo' => 'Despensa', 'costo_unidad' => 100, 'iva' => 0]],
        ])->assertRedirect('/apoyos');

        $apoyo = Apoyo::firstOrFail();

        Storage::disk('local')->assertExists($apoyo->ine_path);
        Storage::disk('local')->assertExists($apoyo->solicitud_recibo_path);
        Storage::disk('public')->assertMissing($apoyo->ine_path);
        Storage::disk('public')->assertMissing($apoyo->solicitud_recibo_path);
    }

    public function test_solo_el_dueno_o_el_admin_abren_un_documento(): void
    {
        $dueno = $this->usuario();
        $apoyo = $this->apoyoCon($dueno);
        $url = "/apoyos/{$apoyo->id}/documentos/ine";

        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->usuario())->get($url)->assertForbidden();
        $this->actingAs($this->usuario('Consulta'))->get($url)->assertForbidden();
        $this->actingAs($this->usuario('Admin'))->get($url)->assertOk();

        $this->actingAs($dueno)->get($url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderContains('Content-Disposition', 'inline');
    }

    public function test_un_tipo_invalido_o_un_documento_faltante_da_404(): void
    {
        $dueno = $this->usuario();
        $apoyo = $this->apoyoCon($dueno);

        $this->actingAs($dueno)->get("/apoyos/{$apoyo->id}/documentos/password")->assertNotFound();
        $this->actingAs($dueno)->get("/apoyos/{$apoyo->id}/documentos/curp")->assertNotFound();
    }

    public function test_el_xml_de_compra_requiere_permiso_y_se_descarga(): void
    {
        Storage::disk('local')->put('facturas-compra/a.xml', '<xml/>');
        $factura = FacturaCompra::create([
            'xml_path' => 'facturas-compra/a.xml',
            'emisor' => 'Proveedor',
            'fecha' => '2026-11-01',
            'total' => 100,
        ]);
        $url = "/facturas-compra/{$factura->id}/xml";

        $this->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();

        $this->actingAs($this->usuario())->get($url)
            ->assertOk()
            ->assertHeaderContains('Content-Disposition', 'attachment');
    }

    public function test_el_comando_mueve_los_documentos_existentes_y_se_puede_repetir(): void
    {
        Storage::disk('public')->put('apoyos/viejo.pdf', 'documento');
        Storage::disk('public')->put('facturas-compra/viejo.xml', '<xml/>');
        Storage::disk('public')->put('otra-carpeta/no-tocar.txt', 'x');

        $this->artisan('app:privatizar-documentos')->assertSuccessful();

        Storage::disk('local')->assertExists(['apoyos/viejo.pdf', 'facturas-compra/viejo.xml']);
        Storage::disk('public')->assertMissing(['apoyos/viejo.pdf', 'facturas-compra/viejo.xml']);
        Storage::disk('public')->assertExists('otra-carpeta/no-tocar.txt');
        $this->assertSame('documento', Storage::disk('local')->get('apoyos/viejo.pdf'));

        $this->artisan('app:privatizar-documentos')->assertSuccessful();
    }

    public function test_el_comando_no_borra_si_hay_un_conflicto(): void
    {
        Storage::disk('public')->put('apoyos/a.pdf', 'publico');
        Storage::disk('local')->put('apoyos/a.pdf', 'privado distinto');

        $this->artisan('app:privatizar-documentos')->assertFailed();

        $this->assertSame('publico', Storage::disk('public')->get('apoyos/a.pdf'));
        $this->assertSame('privado distinto', Storage::disk('local')->get('apoyos/a.pdf'));
    }
}
