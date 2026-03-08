import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import Heading from '@/components/heading';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import AdminLayout from '@/layouts/admin/layout';
import { useTranslation } from '@/hooks/use-translation';
import type { BreadcrumbItem } from '@/types';

type UnlinkedUser = {
    id: number;
    name: string;
    email: string;
};

type UnlinkedRelatie = {
    id: number;
    relatie_nummer: number;
    voornaam: string;
    tussenvoegsel: string | null;
    achternaam: string;
    volledige_naam: string;
};

type Props = {
    unlinkedUsers: UnlinkedUser[];
    unlinkedRelaties: UnlinkedRelatie[];
};

function UserRow({
    user,
    relaties,
    t,
}: {
    user: UnlinkedUser;
    relaties: UnlinkedRelatie[];
    t: (key: string) => string;
}) {
    const [selectedRelatieId, setSelectedRelatieId] = useState<string>('');

    function link() {
        if (!selectedRelatieId) return;
        router.post(
            '/admin/koppelingen',
            { user_id: user.id, relatie_id: Number(selectedRelatieId) },
            { preserveScroll: true },
        );
    }

    return (
        <tr className="border-b">
            <td className="py-2 pr-4">{user.name}</td>
            <td className="px-4 py-2 text-muted-foreground">{user.email}</td>
            <td className="px-4 py-2">
                <div className="flex items-center gap-2">
                    <Select value={selectedRelatieId} onValueChange={setSelectedRelatieId}>
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder={t('Select relation')} />
                        </SelectTrigger>
                        <SelectContent>
                            {relaties.map((r) => (
                                <SelectItem key={r.id} value={String(r.id)}>
                                    {r.volledige_naam} (#{r.relatie_nummer})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button size="sm" onClick={link} disabled={!selectedRelatieId}>
                        {t('Link')}
                    </Button>
                </div>
            </td>
        </tr>
    );
}

function RelatieRow({
    relatie,
    users,
    t,
}: {
    relatie: UnlinkedRelatie;
    users: UnlinkedUser[];
    t: (key: string) => string;
}) {
    const [selectedUserId, setSelectedUserId] = useState<string>('');

    function link() {
        if (!selectedUserId) return;
        router.post(
            '/admin/koppelingen',
            { user_id: Number(selectedUserId), relatie_id: relatie.id },
            { preserveScroll: true },
        );
    }

    return (
        <tr className="border-b">
            <td className="py-2 pr-4">{relatie.relatie_nummer}</td>
            <td className="px-4 py-2">{relatie.volledige_naam}</td>
            <td className="px-4 py-2">
                <div className="flex items-center gap-2">
                    <Select value={selectedUserId} onValueChange={setSelectedUserId}>
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder={t('Select user')} />
                        </SelectTrigger>
                        <SelectContent>
                            {users.map((u) => (
                                <SelectItem key={u.id} value={String(u.id)}>
                                    {u.name} ({u.email})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button size="sm" onClick={link} disabled={!selectedUserId}>
                        {t('Link')}
                    </Button>
                </div>
            </td>
        </tr>
    );
}

export default function Koppelingen({ unlinkedUsers, unlinkedRelaties }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('User-relation links'),
            href: '/admin/koppelingen',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('User-relation links')} />

            <AdminLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={t('Users without a relation')}
                        description={t('These users do not have a linked relation record')}
                    />

                    <div className="overflow-x-auto">
                        {unlinkedUsers.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('No unlinked users found.')}</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="py-3 pr-4 text-left font-medium">{t('Name')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('E-mail')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('Link to relation')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {unlinkedUsers.map((user) => (
                                        <UserRow key={user.id} user={user} relaties={unlinkedRelaties} t={t} />
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>

                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={t('Relations without a user')}
                        description={t('These active relations do not have a linked user account')}
                    />

                    <div className="overflow-x-auto">
                        {unlinkedRelaties.length === 0 ? (
                            <p className="text-muted-foreground text-sm">{t('No unlinked relations found.')}</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b">
                                        <th className="py-3 pr-4 text-left font-medium">{t('No.')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('Name')}</th>
                                        <th className="px-4 py-3 text-left font-medium">{t('Link to user')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {unlinkedRelaties.map((relatie) => (
                                        <RelatieRow key={relatie.id} relatie={relatie} users={unlinkedUsers} t={t} />
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </div>
            </AdminLayout>
        </AppLayout>
    );
}
