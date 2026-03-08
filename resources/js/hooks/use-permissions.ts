import { usePage } from '@inertiajs/react';
import type { Permission, Role } from '@/types/auth';

export function usePermissions() {
    const { auth } = usePage().props;
    const permissions = auth.permissions ?? [];
    const roles = auth.roles ?? [];

    const can = (permission: Permission): boolean => permissions.includes(permission);

    const canAny = (perms: Permission[]): boolean => perms.some((p) => permissions.includes(p));

    const canAll = (perms: Permission[]): boolean => perms.every((p) => permissions.includes(p));

    const hasRole = (role: Role): boolean => roles.includes(role);

    const hasAnyRole = (roleList: Role[]): boolean => roleList.some((r) => roles.includes(r));

    return { can, canAny, canAll, hasRole, hasAnyRole, permissions, roles };
}
