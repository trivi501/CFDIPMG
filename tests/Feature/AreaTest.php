<?php

namespace Tests\Feature;

use App\Models\Apoyo;
use App\Models\Area;
use App\Models\Beneficiario;
use App\Models\PersonaApoya;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function usuario(?Area $area = null, string $rol = 'Facturación'): User
    {
        $user = User::factory()->create(['area_id' => $area?->id]);
        $user->assignRole($rol);

        return $user;
    }

    private function apoyoDe(User $user): Apoyo
    {
        $this->actingAs($user)->post('/apoyos', [
            'fecha' => '2026-11-01',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'Juan'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'María'])->id,
            'detalles' => [['cantidad' => 1, 'articulo' => 'Despensa', 'costo_unidad' => 100, 'iva' => 0]],
        ])->assertRedirect('/apoyos');

        return Apoyo::latest('id')->firstOrFail();
    }

    public function test_solo_quien_administra_usuarios_gestiona_areas(): void
    {
        $this->actingAs($this->usuario())->get('/areas')->assertForbidden();

        $admin = $this->usuario(rol: 'Admin');
        $this->actingAs($admin)->post('/areas', ['nombre' => 'Regidor Juan Pérez', 'tipo' => 'regiduria'])
            ->assertRedirect('/areas');
        $this->actingAs($admin)->post('/areas', ['nombre' => 'Otra', 'tipo' => 'dependencia'])
            ->assertSessionHasErrors('tipo');

        $this->assertDatabaseHas('areas', ['nombre' => 'Regidor Juan Pérez', 'tipo' => 'regiduria']);
    }

    public function test_el_apoyo_toma_el_area_de_quien_lo_registra(): void
    {
        $area = Area::create(['nombre' => 'Desarrollo Social', 'tipo' => 'secretaria']);

        $apoyo = $this->apoyoDe($this->usuario($area));

        $this->assertSame($area->id, $apoyo->area_id);
    }

    public function test_los_usuarios_de_la_misma_area_ven_y_editan_sus_apoyos_y_otra_area_no(): void
    {
        $regiduria = Area::create(['nombre' => 'Regidor Juan Pérez', 'tipo' => 'regiduria']);
        $secretaria = Area::create(['nombre' => 'Desarrollo Social', 'tipo' => 'secretaria']);
        $enlace = $this->usuario($regiduria);
        $companero = $this->usuario($regiduria);
        $deOtraArea = $this->usuario($secretaria);
        $sinArea = $this->usuario();

        $apoyo = $this->apoyoDe($enlace);

        $this->actingAs($companero)->get('/apoyos')->assertInertia(fn ($page) => $page->where('apoyos.total', 1));
        $this->actingAs($companero)->get("/apoyos/{$apoyo->id}")->assertOk();
        $this->actingAs($companero)->get("/apoyos/{$apoyo->id}/edit")->assertOk();

        $this->actingAs($deOtraArea)->get('/apoyos')->assertInertia(fn ($page) => $page->where('apoyos.total', 0));
        $this->actingAs($deOtraArea)->get("/apoyos/{$apoyo->id}")->assertForbidden();
        $this->actingAs($sinArea)->get("/apoyos/{$apoyo->id}")->assertForbidden();

        $this->actingAs($this->usuario(rol: 'Admin'))->get("/apoyos/{$apoyo->id}")->assertOk();
    }

    public function test_un_usuario_sin_area_solo_ve_lo_que_registra(): void
    {
        $sinArea = $this->usuario();
        $otro = $this->usuario();
        $this->apoyoDe($sinArea);

        $this->actingAs($sinArea)->get('/apoyos')->assertInertia(fn ($page) => $page->where('apoyos.total', 1));
        $this->actingAs($otro)->get('/apoyos')->assertInertia(fn ($page) => $page->where('apoyos.total', 0));
    }

    public function test_al_asignar_area_sus_apoyos_sin_area_pasan_a_ella(): void
    {
        $area = Area::create(['nombre' => 'Desarrollo Social', 'tipo' => 'secretaria']);
        $otraArea = Area::create(['nombre' => 'Obras', 'tipo' => 'secretaria']);
        $usuario = $this->usuario();
        $sinArea = $this->apoyoDe($usuario);
        $conArea = Apoyo::create([
            'fecha' => '2026-11-01',
            'beneficiario_id' => Beneficiario::create(['nombre' => 'X'])->id,
            'persona_apoya_id' => PersonaApoya::create(['nombre' => 'Y'])->id,
            'user_id' => $usuario->id,
            'area_id' => $otraArea->id,
        ]);

        $this->actingAs($this->usuario(rol: 'Admin'))->put("/users/{$usuario->id}", [
            'name' => $usuario->name,
            'email' => $usuario->email,
            'area_id' => $area->id,
            'titulo' => 'Enlace',
            'roles' => ['Facturación'],
        ])->assertRedirect('/users');

        $this->assertSame('Enlace', $usuario->refresh()->titulo);
        $this->assertSame($area->id, $sinArea->refresh()->area_id);
        $this->assertSame($otraArea->id, $conArea->refresh()->area_id);
    }
}
