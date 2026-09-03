import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type RelatieTypeData = {
    id: number;
    naam: string;
    role_ids: number[];
};

type RoleData = {
    id: number;
    name: string;
};

export default function RelatieTypeRollen({
    relatieTypes,
    roles,
    neverManaged,
}: {
    relatieTypes: RelatieTypeData[];
    roles: RoleData[];
    neverManaged: string[];
}) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Relatie type roles'),
            href: '/admin/relatie-type-rollen',
        },
    ];

    function toggle(type: RelatieTypeData, roleId: number) {
        const roleIds = type.role_ids.includes(roleId)
            ? type.role_ids.filter((id) => id !== roleId)
            : [...type.role_ids, roleId];

        const mappings = relatieTypes.flatMap((current) =>
            (current.id === type.id ? roleIds : current.role_ids).map(
                (role_id) => ({ relatie_type_id: current.id, role_id }),
            ),
        );

        router.put(
            '/admin/relatie-type-rollen',
            { mappings },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Relatie type roles')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('Derived roles')}
                    description={t(
                        'Map a relatie type to an internal role. Anyone holding that type actively gets the role automatically.',
                    )}
                />

                <p className="text-xs text-muted-foreground">
                    {t(
                        'admin and member stay manual on purpose and cannot be mapped.',
                    )}{' '}
                    <span className="font-mono">{neverManaged.join(', ')}</span>
                </p>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="py-3 pr-4 text-left font-medium">
                                    {t('Relatie type')}
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
                            {relatieTypes.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={roles.length + 1}
                                        className="py-4 text-muted-foreground"
                                    >
                                        {t('No relatie types found.')}
                                    </td>
                                </tr>
                            )}
                            {relatieTypes.map((type) => (
                                <tr key={type.id} className="border-b">
                                    <td className="py-2 pr-4">{type.naam}</td>
                                    {roles.map((role) => (
                                        <td
                                            key={`${type.id}-${role.id}`}
                                            className="px-4 py-2 text-center"
                                        >
                                            <Checkbox
                                                checked={type.role_ids.includes(
                                                    role.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggle(type, role.id)
                                                }
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
