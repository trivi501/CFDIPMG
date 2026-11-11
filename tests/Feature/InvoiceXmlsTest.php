<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class InvoiceXmlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function usuario(string $rol = 'Facturación'): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    protected function factura(string $folio, string $cliente, string $rfc, string $status = 'valid'): Invoice
    {
        $customer = Customer::firstOrCreate(['rfc' => $rfc], [
            'legal_name' => $cliente,
            'tax_system' => '616',
            'zip' => '01000',
        ]);

        return Invoice::create([
            'customer_id' => $customer->id,
            'facturapi_invoice_id' => $status === 'failed' ? null : "fact_{$folio}",
            'uuid' => $status === 'failed' ? null : "uuid-{$folio}",
            'series' => 'A',
            'folio' => $folio,
            'status' => $status,
            'total' => 100,
            'issued_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function contenidoDelZip(BinaryFileResponse $response): array
    {
        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $archivos = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);
            $archivos[$nombre] = (string) $zip->getFromName($nombre);
        }

        $zip->close();

        return $archivos;
    }

    public function test_busca_por_cliente_rfc_folio_o_uuid(): void
    {
        $this->factura('7311', 'Juan Pérez', 'PEPJ800101AAA');
        $this->factura('8422', 'Comercial del Norte', 'CNO010101BBB');

        $user = $this->usuario();

        foreach (['juan' => '7311', 'CNO010101BBB' => '8422', '7311' => '7311', 'uuid-8422' => '8422'] as $search => $folio) {
            $this->actingAs($user)->get('/invoices?search='.urlencode($search))->assertInertia(
                fn ($page) => $page->where('invoices.total', 1)->where('invoices.data.0.folio', $folio)
            );
        }
    }

    public function test_descarga_un_zip_con_los_xml_seleccionados(): void
    {
        Http::fake([
            '*/invoices/fact_101/xml' => Http::response('<cfdi>101</cfdi>'),
            '*/invoices/fact_202/xml' => Http::response('<cfdi>202</cfdi>'),
        ]);

        $a = $this->factura('101', 'Juan Pérez', 'PEPJ800101AAA');
        $b = $this->factura('202', 'Comercial del Norte', 'CNO010101BBB');
        $fallida = $this->factura('303', 'Juan Pérez', 'PEPJ800101AAA', 'failed');
        $this->factura('404', 'No seleccionada', 'NSE010101CCC');

        $response = $this->actingAs($this->usuario())
            ->get('/invoices/xmls?'.http_build_query(['ids' => [$a->id, $b->id, $fallida->id]]))
            ->assertOk();

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertSame([
            'A101-uuid-101.xml' => '<cfdi>101</cfdi>',
            'A202-uuid-202.xml' => '<cfdi>202</cfdi>',
        ], $this->contenidoDelZip($response->baseResponse));
        Http::assertSentCount(2);
    }

    public function test_si_facturapi_falla_en_una_la_anota_y_entrega_las_demas(): void
    {
        Http::fake([
            '*/invoices/fact_101/xml' => Http::response('<cfdi>101</cfdi>'),
            '*/invoices/fact_202/xml' => Http::response(['message' => 'No encontrada'], 404),
        ]);

        $a = $this->factura('101', 'Juan Pérez', 'PEPJ800101AAA');
        $b = $this->factura('202', 'Comercial del Norte', 'CNO010101BBB');

        $response = $this->actingAs($this->usuario())
            ->get('/invoices/xmls?'.http_build_query(['ids' => [$a->id, $b->id]]))
            ->assertOk();

        $archivos = $this->contenidoDelZip($response->baseResponse);

        $this->assertSame('<cfdi>101</cfdi>', $archivos['A101-uuid-101.xml']);
        $this->assertArrayNotHasKey('A202-uuid-202.xml', $archivos);
        $this->assertStringContainsString('A202-uuid-202.xml', $archivos['ERRORES.txt']);
    }

    public function test_sin_facturas_timbradas_o_sin_permiso_no_descarga(): void
    {
        $fallida = $this->factura('303', 'Juan Pérez', 'PEPJ800101AAA', 'failed');
        $valida = $this->factura('101', 'Juan Pérez', 'PEPJ800101AAA');

        $this->actingAs($this->usuario())
            ->get('/invoices/xmls?'.http_build_query(['ids' => [$fallida->id]]))
            ->assertNotFound();

        $this->actingAs($this->usuario('Apoyos'))
            ->get('/invoices/xmls?'.http_build_query(['ids' => [$valida->id]]))
            ->assertForbidden();

        $this->actingAs($this->usuario())->get('/invoices/xmls')->assertSessionHasErrors('ids');
    }
}
