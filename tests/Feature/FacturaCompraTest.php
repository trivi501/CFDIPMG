<?php

namespace Tests\Feature;

use App\Models\FacturaCompra;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacturaCompraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('public');
    }

    protected function billingUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Facturación');

        return $user;
    }

    protected function cfdiXml(string $uuid = '11111111-1111-1111-1111-111111111111'): string
    {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-08-01T12:00:00" Total="616.00">
            <cfdi:Emisor Rfc="XAXX010101000" Nombre="Proveedor de prueba"/>
            <cfdi:Conceptos>
                <cfdi:Concepto ClaveProdServ="50202306" Cantidad="10" ClaveUnidad="H87" Descripcion="Despensa" ValorUnitario="250.00" Importe="2500.00"/>
                <cfdi:Concepto ClaveProdServ="51101700" Cantidad="1" ClaveUnidad="H87" Descripcion="Medicamento" ValorUnitario="100.00" Importe="100.00">
                    <cfdi:Impuestos>
                        <cfdi:Traslados>
                            <cfdi:Traslado Base="100.00" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="16.00"/>
                        </cfdi:Traslados>
                    </cfdi:Impuestos>
                </cfdi:Concepto>
            </cfdi:Conceptos>
            <cfdi:Complemento>
                <tfd:TimbreFiscalDigital UUID="{$uuid}" FechaTimbrado="2026-08-01T12:00:01"/>
            </cfdi:Complemento>
        </cfdi:Comprobante>
        XML;
    }

    public function test_it_registers_a_factura_compra_with_its_articulos_from_a_cfdi_xml(): void
    {
        $file = UploadedFile::fake()->createWithContent('factura.xml', $this->cfdiXml());

        $response = $this->actingAs($this->billingUser())
            ->postJson('/facturas-compra', ['xml' => $file]);

        $response->assertOk();

        $factura = FacturaCompra::firstOrFail();

        $this->assertSame('Proveedor de prueba', $factura->emisor);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $factura->uuid);
        $this->assertSame(2, $factura->articulos()->count());

        $despensa = $factura->articulos()->where('descripcion', 'Despensa')->firstOrFail();
        $this->assertEquals(10, (float) $despensa->cantidad_total);
        $this->assertEquals(10, (float) $despensa->cantidad_disponible);
        $this->assertEquals(250, (float) $despensa->costo_unidad);

        Storage::disk('public')->assertExists($factura->xml_path);
    }

    public function test_parsing_a_non_cfdi_xml_returns_a_422_with_a_message(): void
    {
        $file = UploadedFile::fake()->createWithContent('factura.xml', '<?xml version="1.0"?><root><foo>bar</foo></root>');

        $this->actingAs($this->billingUser())
            ->postJson('/facturas-compra', ['xml' => $file])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_it_rejects_registering_the_exact_same_xml_twice(): void
    {
        $xml = $this->cfdiXml();
        $user = $this->billingUser();

        $this->actingAs($user)->postJson('/facturas-compra', [
            'xml' => UploadedFile::fake()->createWithContent('factura.xml', $xml),
        ])->assertOk();

        $response = $this->actingAs($user)->postJson('/facturas-compra', [
            'xml' => UploadedFile::fake()->createWithContent('factura-copia.xml', $xml),
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message']);
        $this->assertSame(1, FacturaCompra::count());
    }

    public function test_it_rejects_a_different_file_carrying_the_same_cfdi_uuid(): void
    {
        $uuid = '22222222-2222-2222-2222-222222222222';
        $user = $this->billingUser();

        $this->actingAs($user)->postJson('/facturas-compra', [
            'xml' => UploadedFile::fake()->createWithContent('factura.xml', $this->cfdiXml($uuid)),
        ])->assertOk();

        $modified = $this->cfdiXml($uuid)."\n<!-- copia re-exportada -->";

        $response = $this->actingAs($user)->postJson('/facturas-compra', [
            'xml' => UploadedFile::fake()->createWithContent('factura-reexportada.xml', $modified),
        ]);

        $response->assertStatus(422)->assertJsonStructure(['message']);
        $this->assertSame(1, FacturaCompra::count());
    }

    public function test_registering_a_factura_requires_apoyos_manage_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $file = UploadedFile::fake()->createWithContent('factura.xml', $this->cfdiXml());

        $this->actingAs($user)
            ->postJson('/facturas-compra', ['xml' => $file])
            ->assertForbidden();
    }

    public function test_viewing_the_facturas_compra_list_requires_apoyos_view_permission(): void
    {
        $noRole = User::factory()->create();

        $this->actingAs($noRole)->get('/facturas-compra')->assertForbidden();
    }
}
