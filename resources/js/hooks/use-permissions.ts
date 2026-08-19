import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

export function usePermissions() {
    const { auth } = usePage().props;

    const can = useCallback(
        (permission: string) => auth.permissions.includes(permission),
        [auth.permissions],
    );

    const hasRole = useCallback(
        (role: string) => auth.roles.includes(role),
        [auth.roles],
    );

    return { can, hasRole, permissions: auth.permissions, roles: auth.roles };
}
