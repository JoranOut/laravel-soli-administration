import { Head, router } from '@inertiajs/react';
import { SearchInput } from '@/components/admin/search-input';
import Heading from '@/components/heading';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import type { BreadcrumbItem } from '@/types';

type UserData = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};

export default function Users({
    users,
    roles,
    filters,
}: {
    users: UserData[];
    roles: string[];
    filters: { search?: string };
}) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('User roles'),
            href: '/admin/users',
        },
    ];

    function updateRole(user: UserData, role: string) {
        router.put(
            `/admin/users/${user.id}`,
            { roles: [role] },
            { preserveScroll: true },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('User roles')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('Assign user roles')}
                    description={t('Assign roles to users')}
                />

                    <div className="max-w-sm">
                        <SearchInput
                            value={filters.search}
                            placeholder={t('Search users...')}
                            routeName="/admin/users"
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="py-3 pr-4 text-left font-medium">
                                        {t('Name')}
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium">
                                        {t('E-mail')}
                                    </th>
                                    <th className="px-4 py-3 text-left font-medium">
                                        {t('Role')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.map((user) => (
                                    <tr key={user.id} className="border-b">
                                        <td className="py-2 pr-4">
                                            {user.name}
                                        </td>
                                        <td className="px-4 py-2 text-muted-foreground">
                                            {user.email}
                                        </td>
                                        <td className="px-4 py-2">
                                            <Select
                                                value={user.roles[0] ?? ''}
                                                onValueChange={(value) =>
                                                    updateRole(user, value)
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('Select role')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {roles.map((role) => (
                                                        <SelectItem
                                                            key={role}
                                                            value={role}
                                                        >
                                                            {role}
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
