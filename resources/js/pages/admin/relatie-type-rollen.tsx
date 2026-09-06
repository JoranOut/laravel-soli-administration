import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const NO_ROLE = 'none';

type RelatieTypeData = {
    id: number;
    naam: string;
    role_id: number | null;
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

    // One type per request, so editing this row cannot overwrite a row
    // somebody else changed while this page was open.
    function updateRole(type: RelatieTypeData, value: string) {
        router.put(
            `/admin/relatie-type-rollen/${type.id}`,
            { role_id: value === NO_ROLE ? null : Number(value) },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Relatie type roles')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={t('Derived roles')}
                        description={t(
                            'Map a relatie type to an internal role. Anyone holding that type actively gets the role automatically.',
                        )}
                    />

                    <Button variant="outline" size="sm" asChild>
                        <Link href="/admin/roles">
                            {t('Roles & permissions')}
                        </Link>
                    </Button>
                </div>

                <p className="text-xs text-muted-foreground">
                    {t(
                        'One role per relatie type. Several types may point to the same role.',
                    )}{' '}
                    {t('These roles stay manual and cannot be mapped:')}{' '}
                    <span className="font-mono">{neverManaged.join(', ')}</span>
                </p>

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="py-3 pr-4 text-left font-medium">
                                    {t('Relatie type')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('Role')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {relatieTypes.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={2}
                                        className="py-4 text-muted-foreground"
                                    >
                                        {t('No relatie types found.')}
                                    </td>
                                </tr>
                            )}
                            {relatieTypes.map((type) => (
                                <tr key={type.id} className="border-b">
                                    <td className="py-2 pr-4">{type.naam}</td>
                                    <td className="px-4 py-2">
                                        <Select
                                            value={
                                                type.role_id === null
                                                    ? NO_ROLE
                                                    : String(type.role_id)
                                            }
                                            onValueChange={(value) =>
                                                updateRole(type, value)
                                            }
                                        >
                                            <SelectTrigger className="w-[240px]">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NO_ROLE}>
                                                    {t('No role')}
                                                </SelectItem>
                                                {roles.map((role) => (
                                                    <SelectItem
                                                        key={role.id}
                                                        value={String(role.id)}
                                                    >
                                                        {role.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
