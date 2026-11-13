<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Corre en producción (composer install --no-dev): no usa factories, que dependen de Faker y
     * ahí no está instalado. Se puede volver a correr: si el admin ya existe, no se toca.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $admin = User::where('email', 'trivijorge12@gmail.com')->first();

        if ($admin === null) {
            // Entra con ChangeMe123! y RequireCambioPassword lo obliga a cambiarla al primer inicio.
            $admin = User::create([
                'name' => 'Administrador',
                'email' => 'trivijorge12@gmail.com',
                'password' => Hash::make('ChangeMe123!'),
                'must_change_password' => true,
            ]);
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        $admin->assignRole('Admin');
    }
}
