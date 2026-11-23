<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\Beneficiario;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardGraficasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-11-23 12:00:00');
    }

    protected function usuario(string $rol, ?Area $area = null, string $nombre = 'Usuario'): User
    {
        $user = User::factory()->create(['name' => $nombre, 'area_id' => $area?->id]);
        $user->assignRole($rol);

        return $user;
    }

    protected function facturado(User $user, ?Area $area, float $total, string $fecha = '2026-11-10 18:00:00', string $status = 'valid'): void
    {
        $customer = Customer::firstOrCreate(
            ['rfc' => 'XAXX010101000'],
            ['legal_name' => 'PUBLICO EN GENERAL', 'tax_system' => '616', 'zip' => '01000'],
        );

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'total' => $total,
            'status' => $status,
            'issued_at' => $fecha,
        ]);

        Apoyo::create([
            'fecha' => '2026-11-01',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'B'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'P'])->id,
            'user_id' => $user->id,
            'area_id' => $area?->id,
            'estatus' => Apoyo::ESTATUS_FACTURADO,
            'invoice_id' => $invoice->id,
        ]);
    }

    /** @return array<string, mixed>|null */
    protected function graficas(User $user, string $query = ''): ?array
    {
        $graficas = null;
        $this->actingAs($user)->get('/dashboard'.$query)->assertOk()->assertInertia(function ($page) use (&$graficas) {
            $graficas = $page->toArray()['props']['graficas'];
        });

        return $graficas;
    }

    public function test_admin_ve_facturas_por_departamento_y_por_usuario_en_cabildo(): void
    {
        $cabildo = Area::create(['nombre' => 'Cabildo', 'tipo' => 'regiduria', 'privada' => true]);
        $social = Area::create(['nombre' => 'Desarrollo Social', 'tipo' => 'secretaria']);
        $ana = $this->usuario('Apoyos', $cabildo, 'Ana');
        $luis = $this->usuario('Apoyos', $cabildo, 'Luis');
        $pepe = $this->usuario('Apoyos', $social, 'Pepe');

        $this->facturado($ana, $cabildo, 100);
        $this->facturado($ana, $cabildo, 200);
        $this->facturado($luis, $cabildo, 50);
        $this->facturado($pepe, $social, 1000);
        $this->facturado($pepe, $social, 999, status: 'failed');
        $this->facturado($pepe, $social, 400, fecha: '2025-12-15 18:00:00');

        $graficas = $this->graficas($this->usuario('Admin'));

        $this->assertSame('anio', $graficas['periodo']);
        $this->assertEquals(['facturas' => 4, 'monto' => 1350], $graficas['totales']);
        $this->assertEquals([
            ['id' => $social->id, 'nombre' => 'Desarrollo Social', 'facturas' => 1, 'monto' => 1000],
            ['id' => $cabildo->id, 'nombre' => 'Cabildo', 'facturas' => 3, 'monto' => 350],
        ], $graficas['porArea']);

        $this->assertCount(1, $graficas['porUsuario']);
        $this->assertEquals([
            ['id' => $ana->id, 'nombre' => 'Ana', 'facturas' => 2, 'monto' => 300],
            ['id' => $luis->id, 'nombre' => 'Luis', 'facturas' => 1, 'monto' => 50],
        ], $graficas['porUsuario'][0]['usuarios']);

        $this->assertCount(12, $graficas['porMes']);
        $this->assertEquals(['mes' => '2025-12', 'facturas' => 1, 'monto' => 400], $graficas['porMes'][0]);
        $this->assertEquals(['mes' => '2026-11', 'facturas' => 4, 'monto' => 1350], $graficas['porMes'][11]);

        $this->assertEquals(['facturas' => 5, 'monto' => 1750], $this->graficas($this->usuario('Admin'), '?periodo=todo')['totales']);
    }

    public function test_en_cabildo_cada_quien_ve_solo_sus_facturas(): void
    {
        $cabildo = Area::create(['nombre' => 'Cabildo', 'tipo' => 'regiduria', 'privada' => true]);
        $ana = $this->usuario('Apoyos', $cabildo, 'Ana');
        $luis = $this->usuario('Apoyos', $cabildo, 'Luis');

        $this->facturado($ana, $cabildo, 100);
        $this->facturado($luis, $cabildo, 50);

        $graficas = $this->graficas($ana);

        $this->assertEquals(['facturas' => 1, 'monto' => 100], $graficas['totales']);
        $this->assertSame(['Ana'], array_column($graficas['porUsuario'][0]['usuarios'], 'nombre'));
    }

    public function test_quien_no_ve_apoyos_no_recibe_graficas(): void
    {
        $this->assertNull($this->graficas(User::factory()->create()));
    }
}
