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
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const NO_ROLE = 'none';

type UserData = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    derived_roles: string[];
};

export default function Users({
    users,
    roles,
    managedRoles,
    filters,
}: {
    users: UserData[];
    roles: string[];
    managedRoles: string[];
    filters: { search?: string };
}) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('User roles'),
            href: '/admin/users',
        },
    ];

    // Roles that follow from a relatie type are shown read-only; assigning one
    // by hand is refused server-side and would be undone by the nightly sync.
    const assignableRoles = roles.filter(
        (role) => !managedRoles.includes(role),
    );

    function manualRole(user: UserData) {
        return (
            user.roles.find((role) => !managedRoles.includes(role)) ?? NO_ROLE
        );
    }

    function updateRole(user: UserData, role: string) {
        router.put(
            `/admin/users/${user.id}`,
            // NO_ROLE clears the manual role; derived roles are re-added
            // server-side, so this only drops what was granted by hand.
            { roles: role === NO_ROLE ? [] : [role] },
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
                                    {t('Manual role')}
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    {t('From relatie type')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id} className="border-b">
                                    <td className="py-2 pr-4">{user.name}</td>
                                    <td className="px-4 py-2 text-muted-foreground">
                                        {user.email}
                                    </td>
                                    <td className="px-4 py-2">
                                        <Select
                                            value={manualRole(user)}
                                            onValueChange={(value) =>
                                                updateRole(user, value)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder={t(
                                                        'Select role',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value={NO_ROLE}>
                                                    {t('No role')}
                                                </SelectItem>
                                                {assignableRoles.map((role) => (
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
                                    <td className="px-4 py-2">
                                        {user.derived_roles.length === 0 ? (
                                            <span className="text-muted-foreground">
                                                &mdash;
                                            </span>
                                        ) : (
                                            <span
                                                className="capitalize"
                                                title={t(
                                                    'This role follows from a relatie type',
                                                )}
                                            >
                                                {user.derived_roles.join(', ')}
                                            </span>
                                        )}
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
