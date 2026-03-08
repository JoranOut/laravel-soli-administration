import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DataTable, type Column } from '@/components/admin/data-table';
import { Pagination } from '@/components/admin/pagination';
import { SearchInput } from '@/components/admin/search-input';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { PaginatedResponse, Relatie, RelatieType } from '@/types/admin';

type Props = {
    relaties: PaginatedResponse<Relatie>;
    filters: {
        search?: string;
        type?: string;
        show_inactive?: string;
        sort?: string;
        direction?: string;
    };
    relatieTypes: RelatieType[];
};

export default function RelatiesIndex({ relaties, filters, relatieTypes }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    const columns: Column<Relatie>[] = [
        { key: 'relatie_nummer', label: '#', sortable: true },
        {
            key: 'achternaam',
            label: t('Name'),
            sortable: true,
            render: (relatie) => (
                <Link href={`/admin/relaties/${relatie.id}`} className="text-primary hover:underline font-medium">
                    {relatie.volledige_naam}
                </Link>
            ),
        },
        {
            key: 'types',
            label: t('Type'),
            render: (relatie) => (
                <div className="flex gap-1">
                    {relatie.types?.map((type) => (
                        <Badge key={type.id} variant="secondary">
                            {type.naam}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'actief',
            label: t('Status'),
            render: (relatie) => (
                <Badge variant={relatie.actief ? 'default' : 'outline'}>
                    {relatie.actief ? t('Active') : t('Inactive')}
                </Badge>
            ),
        },
    ];

    const handleSort = (key: string) => {
        const direction = filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(
            '/admin/relaties',
            { ...filters, sort: key, direction },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleTypeFilter = (type: string) => {
        const params: Record<string, string> = {};
        if (filters.search) params.search = filters.search;
        if (filters.show_inactive) params.show_inactive = filters.show_inactive;
        if (type !== 'all') params.type = type;

        router.get('/admin/relaties', params, { preserveState: true, preserveScroll: true });
    };

    const handleShowInactive = (checked: boolean) => {
        const params: Record<string, string> = {};
        if (filters.search) params.search = filters.search;
        if (filters.type) params.type = filters.type;
        if (checked) params.show_inactive = '1';

        router.get('/admin/relaties', params, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={t('Relations')} />
                <div className="space-y-4 p-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h2 className="text-lg font-semibold">{t('Relations')}</h2>
                        {can('relaties.create') && (
                            <Button asChild>
                                <Link href={filters.type ? `/admin/relaties/create?type=${filters.type}` : '/admin/relaties/create'}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {filters.type
                                        ? t('New :type', { type: filters.type })
                                        : t('New relation')}
                                </Link>
                            </Button>
                        )}
                    </div>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div className="w-full sm:w-64">
                            <SearchInput
                                value={filters.search}
                                placeholder={t('Search...')}
                                routeName="/admin/relaties"
                                queryParams={{
                                    type: filters.type,
                                    show_inactive: filters.show_inactive,
                                }}
                            />
                        </div>

                        <Select value={filters.type ?? 'all'} onValueChange={handleTypeFilter}>
                            <SelectTrigger className="w-full sm:w-40">
                                <SelectValue placeholder={t('All types')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('All types')}</SelectItem>
                                {relatieTypes.map((type) => (
                                    <SelectItem key={type.id} value={type.naam}>
                                        {type.naam.charAt(0).toUpperCase() + type.naam.slice(1)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="show-inactive"
                                checked={filters.show_inactive === '1'}
                                onCheckedChange={(checked) => handleShowInactive(checked === true)}
                            />
                            <label htmlFor="show-inactive" className="text-sm">
                                {t('Show inactive')}
                            </label>
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={relaties.data}
                        sortKey={filters.sort}
                        sortDirection={filters.direction}
                        onSort={handleSort}
                        emptyMessage={t('No relations found.')}
                    />

                    <Pagination pagination={relaties} />
                </div>
        </AppLayout>
    );
}
