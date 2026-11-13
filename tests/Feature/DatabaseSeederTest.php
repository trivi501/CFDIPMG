<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_admin_sin_factories_y_se_puede_volver_a_correr(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $admin = User::sole();
        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertTrue($admin->must_change_password);
        $this->assertTrue(Hash::check('ChangeMe123!', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
    }

    /** En producción no está Faker (composer install --no-dev): el seeder no puede usar factories. */
    public function test_el_seeder_no_usa_factories(): void
    {
        $codigo = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('factory(', $codigo);
        $this->assertStringNotContainsString('fake(', $codigo);
    }
}
