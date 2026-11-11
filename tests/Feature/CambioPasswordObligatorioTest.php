<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CambioPasswordObligatorioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['auth.password_inicial' => 'Generica2026']);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        return $user;
    }

    public function test_el_usuario_nuevo_se_crea_con_la_contrasena_generica_y_obligado_a_cambiarla(): void
    {
        $this->actingAs($this->admin())->post('/users', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'roles' => ['Apoyos'],
        ])->assertRedirect('/users');

        $ana = User::where('email', 'ana@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Generica2026', $ana->password));
        $this->assertTrue($ana->must_change_password);
        $this->assertTrue($ana->hasRole('Apoyos'));
    }

    public function test_al_iniciar_sesion_con_la_contrasena_generica_solo_puede_cambiarla(): void
    {
        $user = User::factory()->create([
            'password' => 'Generica2026',
            'must_change_password' => true,
        ]);
        $user->assignRole('Facturación');

        $this->post('/login', ['email' => $user->email, 'password' => 'Generica2026']);
        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')->assertRedirect('/cambiar-password');
        $this->get('/invoices')->assertRedirect('/cambiar-password');
        $this->get('/settings/profile')->assertRedirect('/cambiar-password');
        $this->get('/cambiar-password')->assertOk()->assertInertia(
            fn ($page) => $page->component('auth/cambiar-password')
        );
    }

    public function test_al_cambiarla_ya_puede_usar_el_sistema(): void
    {
        $user = User::factory()->create([
            'password' => 'Generica2026',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/cambiar-password', [
            'password' => 'MiNuevaClave.99',
            'password_confirmation' => 'MiNuevaClave.99',
        ])->assertRedirect('/dashboard');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('MiNuevaClave.99', $user->password));

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/cambiar-password')->assertRedirect('/dashboard');
    }

    public function test_no_acepta_la_misma_contrasena_ni_una_confirmacion_distinta(): void
    {
        $user = User::factory()->create([
            'password' => 'Generica2026',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/cambiar-password', [
            'password' => 'Generica2026',
            'password_confirmation' => 'Generica2026',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->put('/cambiar-password', [
            'password' => 'MiNuevaClave.99',
            'password_confirmation' => 'OtraCosa.99',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_puede_cerrar_sesion_sin_cambiarla(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);

        $this->actingAs($user)->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_si_el_admin_le_pone_una_contrasena_tambien_debe_cambiarla(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin())->put("/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'Temporal123',
            'roles' => [],
        ])->assertRedirect('/users');

        $this->assertTrue($user->refresh()->must_change_password);

        $this->actingAs($this->admin())->put("/users/{$user->id}", [
            'name' => 'Otro nombre',
            'email' => $user->email,
            'password' => '',
            'roles' => [],
        ]);

        $this->assertTrue($user->refresh()->must_change_password);
    }
}
