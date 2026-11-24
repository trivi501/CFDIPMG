<?php

namespace Tests\Feature;

use App\Models\ClaveSat;
use App\Models\PaymentReceipt;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClaveSatTest extends TestCase
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

    public function test_crea_edita_busca_y_elimina_claves(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)->post('/claves-sat', [
            'clave' => ' 84111506 ',
            'unidad' => 'e48',
            'concepto' => 'Servicios de facturación',
        ])->assertRedirect('/claves-sat');

        $clave = ClaveSat::firstOrFail();
        $this->assertSame('84111506', $clave->clave);
        $this->assertSame('E48', $clave->unidad);

        ClaveSat::create(['clave' => '93161700', 'unidad' => 'C62', 'concepto' => 'Apoyo social']);

        $this->actingAs($user)->get('/claves-sat?search=facturaci')->assertInertia(
            fn ($page) => $page->component('claves-sat/index')
                ->where('claves.total', 1)
                ->where('claves.data.0.clave', '84111506')
        );

        $this->actingAs($user)->put("/claves-sat/{$clave->id}", [
            'clave' => '84111506',
            'unidad' => 'ACT',
            'concepto' => 'Servicio de cobranza',
        ])->assertRedirect('/claves-sat');

        $this->assertSame('ACT', $clave->refresh()->unidad);
        $this->assertSame('Servicio de cobranza', $clave->concepto);

        $this->actingAs($user)->delete("/claves-sat/{$clave->id}")->assertRedirect('/claves-sat');
        $this->assertModelMissing($clave);
    }

    public function test_la_clave_tiene_8_digitos_y_no_se_repite(): void
    {
        $user = $this->usuario();
        ClaveSat::create(['clave' => '84111506', 'unidad' => 'E48', 'concepto' => 'Servicios']);

        $this->actingAs($user)->post('/claves-sat', [
            'clave' => '8411',
            'unidad' => 'E48',
            'concepto' => 'Corta',
        ])->assertSessionHasErrors(['clave' => 'La clave de producto o servicio del SAT tiene 8 dígitos.']);

        $this->actingAs($user)->post('/claves-sat', [
            'clave' => '84111506',
            'unidad' => 'E48',
            'concepto' => 'Repetida',
        ])->assertSessionHasErrors(['clave' => 'El valor de clave ya está registrado.']);

        $this->assertSame(1, ClaveSat::count());
    }

    public function test_quien_solo_consulta_ve_el_catalogo_pero_no_lo_modifica(): void
    {
        $clave = ClaveSat::create(['clave' => '84111506', 'unidad' => 'E48', 'concepto' => 'Servicios']);
        $consulta = $this->usuario('Consulta');

        $this->actingAs($consulta)->get('/claves-sat')->assertOk();
        $this->actingAs($consulta)->get('/claves-sat/create')->assertForbidden();
        $this->actingAs($consulta)->put("/claves-sat/{$clave->id}", [
            'clave' => '84111506',
            'unidad' => 'H87',
            'concepto' => 'Otro',
        ])->assertForbidden();
        $this->actingAs($consulta)->delete("/claves-sat/{$clave->id}")->assertForbidden();

        $this->actingAs($this->usuario('Apoyos'))->get('/claves-sat')->assertForbidden();
    }

    public function test_los_formularios_de_factura_reciben_el_catalogo(): void
    {
        ClaveSat::create(['clave' => '84111506', 'unidad' => 'E48', 'concepto' => 'Servicios de facturación']);
        $user = $this->usuario();

        $this->actingAs($user)->get('/invoices/create')->assertInertia(
            fn ($page) => $page->where('clavesSat.0.clave', '84111506')
                ->where('clavesSat.0.unidad', 'E48')
                ->where('clavesSat.0.concepto', 'Servicios de facturación')
        );

        $receipt = PaymentReceipt::create([
            'external_id' => 'REC-1',
            'source_system' => 'sistema-cobranza',
            'customer_payload' => ['name' => 'Cliente de prueba'],
            'amount' => 100,
            'currency' => 'MXN',
            'status' => 'pending',
            'received_at' => now(),
        ]);

        $this->actingAs($user)->get("/receipts/{$receipt->id}/invoice")->assertInertia(
            fn ($page) => $page->where('clavesSat.0.clave', '84111506')
        );
    }
}
