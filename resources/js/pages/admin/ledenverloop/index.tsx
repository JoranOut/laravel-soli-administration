import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type Column } from '@/components/admin/data-table';
import { Pagination } from '@/components/admin/pagination';
import { useTranslation } from '@/hooks/use-translation';
import type { PaginatedResponse, Relatie } from '@/types/admin';

type LedenverloopRelatie = Relatie & {
    lid_datum: string;
};

type Props = {
    joined: PaginatedResponse<LedenverloopRelatie>;
    left: PaginatedResponse<LedenverloopRelatie>;
    tab: string;
};

export default function LedenverloopIndex({ joined, left, tab }: Props) {
    const { t } = useTranslation();

    const columns: Column<LedenverloopRelatie>[] = [
        {
            key: 'achternaam',
            label: t('Name'),
            render: (relatie) => (
                <Link href={`/admin/relaties/${relatie.id}`} className="text-primary hover:underline font-medium">
                    {relatie.volledige_naam}
                </Link>
            ),
        },
        {
            key: 'onderdelen',
            label: t('Sections'),
            render: (relatie) => (
                <div className="flex gap-1 flex-wrap">
                    {relatie.onderdelen?.map((onderdeel) => (
                        <Badge key={onderdeel.id} variant="secondary">
                            {onderdeel.naam}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'lid_datum',
            label: t('Date'),
        },
    ];

    const handleTabChange = (newTab: string) => {
        router.get('/admin/ledenverloop', { tab: newTab }, { preserveState: true, preserveScroll: true });
    };

    const isJoined = tab === 'joined';
    const data = isJoined ? joined : left;

    return (
        <AppLayout>
            <Head title={t('Member changes')} />
            <div className="space-y-4 p-4">
                <h2 className="text-lg font-semibold">{t('Member changes')}</h2>

                <div className="flex gap-2">
                    <Button
                        variant={isJoined ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleTabChange('joined')}
                    >
                        {t('Joined')} ({joined.total})
                    </Button>
                    <Button
                        variant={!isJoined ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleTabChange('left')}
                    >
                        {t('Left')} ({left.total})
                    </Button>
                </div>

                <DataTable
                    columns={columns}
                    data={data.data}
                    emptyMessage={isJoined ? t('No members joined.') : t('No members left.')}
                />

                <Pagination pagination={data} />
            </div>
        </AppLayout>
    );
}
