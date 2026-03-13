import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import type { BreadcrumbItem } from '@/types';

type RoleData = {
    id: number;
    name: string;
    permissions: string[];
};

export default function Roles({
    roles,
    permissions,
}: {
    roles: RoleData[];
    permissions: string[];
}) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Roles & permissions'),
            href: '/admin/roles',
        },
    ];

    const groupedPermissions = permissions.reduce<Record<string, string[]>>(
        (groups, permission) => {
            const [resource] = permission.split('.');
            if (!groups[resource]) {
                groups[resource] = [];
            }
            groups[resource].push(permission);
            return groups;
        },
        {},
    );

    function togglePermission(role: RoleData, permission: string) {
        const hasPermission = role.permissions.includes(permission);
        const updatedPermissions = hasPermission
            ? role.permissions.filter((p) => p !== permission)
            : [...role.permissions, permission];

        router.put(
            `/admin/roles/${role.id}`,
            { permissions: updatedPermissions },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Roles & permissions')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('Permission matrix')}
                    description={t('Toggle permissions per role')}
                />

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="py-3 pr-4 text-left font-medium">
                                        {t('Permission')}
                                    </th>
                                    {roles.map((role) => (
                                        <th
                                            key={role.id}
                                            className="px-4 py-3 text-center font-medium capitalize"
                                        >
                                            {role.name}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {Object.entries(groupedPermissions).map(
                                    ([resource, perms]) => (
                                        <>
                                            <tr key={`group-${resource}`}>
                                                <td
                                                    colSpan={roles.length + 1}
                                                    className="pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground"
                                                >
                                                    {resource}
                                                </td>
                                            </tr>
                                            {perms.map((permission) => (
                                                <tr
                                                    key={permission}
                                                    className="border-b"
                                                >
                                                    <td className="py-2 pr-4 font-mono text-xs">
                                                        {permission}
                                                    </td>
                                                    {roles.map((role) => (
                                                        <td
                                                            key={`${role.id}-${permission}`}
                                                            className="px-4 py-2 text-center"
                                                        >
                                                            <Checkbox
                                                                checked={role.permissions.includes(
                                                                    permission,
                                                                )}
                                                                onCheckedChange={() =>
                                                                    togglePermission(
                                                                        role,
                                                                        permission,
                                                                    )
                                                                }
                                                            />
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
            </div>
        </AppLayout>
    );
}
