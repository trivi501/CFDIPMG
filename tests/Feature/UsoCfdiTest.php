<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Support\UsoCfdi;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UsoCfdiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_cada_regimen_solo_acepta_sus_usos(): void
    {
        $this->assertTrue(UsoCfdi::permitido('G03', '601'));
        $this->assertTrue(UsoCfdi::permitido('I04', '626'));
        $this->assertTrue(UsoCfdi::permitido('D01', '605'));
        $this->assertTrue(UsoCfdi::permitido('D10', '612'));
        $this->assertTrue(UsoCfdi::permitido('S01', '616'));
        $this->assertTrue(UsoCfdi::permitido('S01', '610'));

        $this->assertFalse(UsoCfdi::permitido('G03', '605'));
        $this->assertFalse(UsoCfdi::permitido('G03', '616'));
        $this->assertFalse(UsoCfdi::permitido('D01', '601'));
        $this->assertFalse(UsoCfdi::permitido('D01', '626'));
        $this->assertFalse(UsoCfdi::permitido('P01', '601'));
        $this->assertFalse(UsoCfdi::permitido('S01', '999'));
    }

    public function test_el_servidor_rechaza_un_uso_no_permitido_sin_llamar_a_facturapi(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $user->assignRole('Facturación');

        $customer = Customer::create([
            'legal_name' => 'Persona de Sueldos',
            'rfc' => 'PEPJ800101AAA',
            'tax_system' => '605',
            'zip' => '98600',
        ]);

        $this->actingAs($user)->post('/invoices', [
            'customer_id' => $customer->id,
            'payment_form' => '03',
            'payment_method' => 'PUE',
            'use' => 'G03',
            'items' => [[
                'description' => 'Servicio',
                'sat_product_key' => '80141600',
                'sat_unit_key' => 'E48',
                'quantity' => 1,
                'unit_price' => 100,
            ]],
        ])->assertSessionHasErrors('use');

        Http::assertNothingSent();
        $this->assertDatabaseCount('invoices', 0);
    }
}
