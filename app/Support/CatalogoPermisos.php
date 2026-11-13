<?php

namespace App\Support;

/**
 * Permisos que existen en el sistema y los que trae cada rol de fábrica. Es la única fuente de
 * verdad: la usan RolesAndPermissionsSeeder (instalación nueva) y `permisos:sincronizar`
 * (servidores ya en uso). Un permiso nuevo se agrega aquí, y a los roles que deban tenerlo.
 */
final class CatalogoPermisos
{
    /** @var list<string> */
    public const PERMISOS = [
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
        'apoyos.approve',
        'apoyos.facturar',
        'apoyos.view-all',
        'apoyos.download-all',
        'users.manage',
        'roles.manage',
        'settings.manage',
        'api-clients.manage',
    ];

    /**
     * Roles de fábrica y sus permisos. ['*'] = todos los permisos.
     *
     * @var array<string, list<string>>
     */
    public const ROLES = [
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
            'apoyos.view-all',
            'apoyos.facturar',
            'apoyos.download-all',
        ],
        'Apoyos' => [
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

    public const GUARD = 'web';

    /** @return list<string> */
    public static function permisosDe(string $rol): array
    {
        $permisos = self::ROLES[$rol] ?? [];

        return $permisos === ['*'] ? self::PERMISOS : $permisos;
    }
}
