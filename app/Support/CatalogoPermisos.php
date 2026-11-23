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
            // Factura sus apoyos (o los de su área) una vez que un Admin los aprobó.
            'apoyos.facturar',
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
     * Cómo se muestra cada permiso en la pantalla de Roles. Un permiso nuevo necesita su texto aquí.
     *
     * @var array<string, array{grupo: string, etiqueta: string}>
     */
    public const ETIQUETAS = [
        'receipts.view' => ['grupo' => 'Recibos', 'etiqueta' => 'Ver recibos de pago'],
        'receipts.import' => ['grupo' => 'Recibos', 'etiqueta' => 'Buscar e importar folios'],
        'invoices.view' => ['grupo' => 'Facturas', 'etiqueta' => 'Ver facturas'],
        'invoices.create' => ['grupo' => 'Facturas', 'etiqueta' => 'Emitir facturas'],
        'invoices.cancel' => ['grupo' => 'Facturas', 'etiqueta' => 'Cancelar facturas'],
        'invoices.download' => ['grupo' => 'Facturas', 'etiqueta' => 'Descargar PDF y XML de facturas'],
        'customers.view' => ['grupo' => 'Clientes y productos', 'etiqueta' => 'Ver clientes'],
        'customers.manage' => ['grupo' => 'Clientes y productos', 'etiqueta' => 'Crear y editar clientes'],
        'products.view' => ['grupo' => 'Clientes y productos', 'etiqueta' => 'Ver productos'],
        'products.manage' => ['grupo' => 'Clientes y productos', 'etiqueta' => 'Crear y editar productos'],
        'apoyos.view' => ['grupo' => 'Apoyos', 'etiqueta' => 'Ver apoyos (los suyos y de su área)'],
        'apoyos.manage' => ['grupo' => 'Apoyos', 'etiqueta' => 'Capturar y editar apoyos'],
        'apoyos.approve' => ['grupo' => 'Apoyos', 'etiqueta' => 'Aprobar y rechazar apoyos'],
        'apoyos.facturar' => ['grupo' => 'Apoyos', 'etiqueta' => 'Facturar apoyos aprobados'],
        'apoyos.view-all' => ['grupo' => 'Apoyos', 'etiqueta' => 'Ver apoyos y facturas de compra de todas las áreas'],
        'apoyos.download-all' => ['grupo' => 'Apoyos', 'etiqueta' => 'Descargar los XML de compras de apoyos facturados'],
        'users.manage' => ['grupo' => 'Administración', 'etiqueta' => 'Administrar usuarios y áreas'],
        'roles.manage' => ['grupo' => 'Administración', 'etiqueta' => 'Administrar roles y permisos'],
        'settings.manage' => ['grupo' => 'Administración', 'etiqueta' => 'Configuración del sistema'],
        'api-clients.manage' => ['grupo' => 'Administración', 'etiqueta' => 'Administrar API clients (sistemas externos)'],
    ];

    public const GUARD = 'web';

    /**
     * Permisos para la pantalla de Roles: nombre interno, grupo y texto en español. Un permiso sin
     * texto (p. ej. creado a mano) se muestra con su nombre interno en el grupo "Otros".
     *
     * @param  iterable<string>  $nombres
     * @return list<array{name: string, grupo: string, etiqueta: string}>
     */
    public static function paraPantalla(iterable $nombres): array
    {
        $lista = [];
        foreach ($nombres as $nombre) {
            $lista[] = ['name' => $nombre, ...(self::ETIQUETAS[$nombre] ?? ['grupo' => 'Otros', 'etiqueta' => $nombre])];
        }

        return $lista;
    }

    /** @return list<string> */
    public static function permisosDe(string $rol): array
    {
        $permisos = self::ROLES[$rol] ?? [];

        return $permisos === ['*'] ? self::PERMISOS : $permisos;
    }
}
