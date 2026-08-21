<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    protected array $roles = [
        'Admin' => ['*'],
        'Facturación' => [
            'receipts.view',
            'receipts.import',
            'invoices.view',
            'invoices.create',
            'invoices.cancel',
            'invoices.download',
            'customers.view',
            'customers.manage',
            'products.view',
            'products.manage',
            'apoyos.view',
            'apoyos.manage',
        ],
        'Consulta' => [
            'receipts.view',
            'invoices.view',
            'invoices.download',
            'customers.view',
            'products.view',
            'apoyos.view',
        ],
    ];

    /**
     * @var list<string>
     */
    protected array $permissions = [
        'receipts.view',
        'receipts.import',
        'invoices.view',
        'invoices.create',
        'invoices.cancel',
        'invoices.download',
        'customers.view',
        'customers.manage',
        'products.view',
        'products.manage',
        'apoyos.view',
        'apoyos.manage',
        'users.manage',
        'roles.manage',
        'settings.manage',
        'api-clients.manage',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->roles as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            $role->syncPermissions($permissions === ['*'] ? $this->permissions : $permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
