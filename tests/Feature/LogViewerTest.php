<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Opcodes\LogViewer\Facades\LogViewer;
use Tests\TestCase;

class LogViewerTest extends TestCase
{
    use RefreshDatabase;

    private string $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->log = storage_path('logs/prueba-log-viewer.log');
        File::put($this->log, '['.now()->format('Y-m-d H:i:s')."] testing.ERROR: Algo falló {\"exception\":\"x\"}\n");
        LogViewer::clearFileCache();
    }

    protected function tearDown(): void
    {
        File::delete($this->log);

        parent::tearDown();
    }

    protected function usuarioCon(string $rol): User
    {
        $user = User::factory()->create();
        $user->assignRole($rol);

        return $user;
    }

    /** Las llamadas a la API del visor vienen de su propia pantalla. */
    protected function api(): static
    {
        return $this->withHeaders(['Referer' => config('app.url').'/log-viewer']);
    }

    protected function identificador(): string
    {
        return (string) LogViewer::getFiles()->first(fn ($file) => $file->name === 'prueba-log-viewer.log')?->identifier;
    }

    public function test_solo_el_rol_admin_entra_al_visor_de_logs(): void
    {
        $this->get('/log-viewer')->assertRedirect('/login');

        foreach (['Facturación', 'Apoyos', 'Consulta'] as $rol) {
            $this->actingAs($this->usuarioCon($rol))->get('/log-viewer')->assertForbidden();
        }

        $this->actingAs($this->usuarioCon('Admin'))->get('/log-viewer')->assertOk();
    }

    public function test_un_permiso_no_abre_el_visor_solo_el_rol(): void
    {
        $user = $this->usuarioCon('Consulta');
        $user->givePermissionTo(['users.manage', 'roles.manage', 'settings.manage']);

        $this->actingAs($user)->get('/log-viewer')->assertForbidden();
        $this->actingAs($user)->api()->getJson('/log-viewer/api/files')->assertForbidden();
    }

    public function test_el_admin_ve_los_logs_pero_no_los_puede_borrar(): void
    {
        $admin = $this->usuarioCon('Admin');
        $id = $this->identificador();
        $this->assertNotSame('', $id);

        $this->actingAs($admin)->api()->getJson('/log-viewer/api/files')
            ->assertOk()
            ->assertJsonFragment(['name' => 'prueba-log-viewer.log', 'can_delete' => false]);

        $this->actingAs($admin)->api()->deleteJson("/log-viewer/api/files/{$id}")->assertForbidden();
        $this->actingAs($admin)->api()->postJson('/log-viewer/api/delete-multiple-files', ['files' => [$id]]);

        $this->assertFileExists($this->log);
    }

    public function test_la_descarga_de_un_log_queda_en_la_bitacora(): void
    {
        $admin = $this->usuarioCon('Admin');
        $id = $this->identificador();
        Auditoria::query()->delete();

        $url = $this->actingAs($admin)->api()->getJson("/log-viewer/api/files/{$id}/download/request")
            ->assertOk()
            ->json('url');

        $this->actingAs($admin)->api()->get($url)->assertOk();

        $registro = Auditoria::where('accion', 'descarga')->sole();
        $this->assertSame('Log del sistema prueba-log-viewer.log', $registro->descripcion);
        $this->assertSame($admin->id, $registro->user_id);
        $this->assertArrayNotHasKey('signature', $registro->despues ?? []);
    }
}
