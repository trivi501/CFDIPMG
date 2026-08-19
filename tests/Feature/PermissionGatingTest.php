<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_user_without_the_customers_permission_is_forbidden(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Consulta');

        $this->actingAs($user)->get('/customers')->assertOk();
        $this->actingAs($user)->get('/customers/create')->assertForbidden();
    }

    public function test_an_admin_can_manage_customers(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $this->actingAs($user)->get('/customers/create')->assertOk();
    }

    public function test_a_user_with_no_role_is_forbidden_from_every_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/customers')->assertForbidden();
        $this->actingAs($user)->get('/roles')->assertForbidden();
    }
}
