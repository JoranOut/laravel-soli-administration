import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DataTable, type Column } from '@/components/admin/data-table';
import { Pagination } from '@/components/admin/pagination';
import { SearchInput } from '@/components/admin/search-input';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Instrument, PaginatedResponse } from '@/types/admin';

type Props = {
    instrumenten: PaginatedResponse<Instrument>;
    filters: {
        search?: string;
        status?: string;
        sort?: string;
        direction?: string;
    };
};

const statusVariant = (status: string) => {
    switch (status) {
        case 'beschikbaar': return 'default' as const;
        case 'in_gebruik': return 'secondary' as const;
        case 'in_reparatie': return 'destructive' as const;
        case 'afgeschreven': return 'outline' as const;
        default: return 'secondary' as const;
    }
};

const statusOptions = ['beschikbaar', 'in_gebruik', 'in_reparatie', 'afgeschreven'];
const eigendomOptions = ['soli', 'bruikleen', 'eigen'];

export default function InstrumentenIndex({ instrumenten, filters }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const columns: Column<Instrument>[] = [
        {
            key: 'nummer',
            label: t('Number'),
            sortable: true,
            render: (i) => (
                <Link href={`/admin/instrumenten/${i.id}`} className="text-primary hover:underline font-medium">
                    {i.nummer}
                </Link>
            ),
        },
        { key: 'soort', label: t('Sort'), sortable: true },
        {
            key: 'merk',
            label: t('Brand / Model'),
            render: (i) => [i.merk, i.model].filter(Boolean).join(' ') || '—',
        },
        {
            key: 'status',
            label: t('Status'),
            render: (i) => <Badge variant={statusVariant(i.status)}>{i.status.replace('_', ' ')}</Badge>,
        },
        {
            key: 'eigendom',
            label: t('Ownership'),
            render: (i) => <Badge variant="outline">{i.eigendom}</Badge>,
        },
        {
            key: 'huidige_bespeler',
            label: t('Player'),
            render: (i) =>
                i.huidige_bespeler?.relatie ? (
                    <Link href={`/admin/relaties/${i.huidige_bespeler.relatie.id}`} className="text-primary hover:underline">
                        {i.huidige_bespeler.relatie.volledige_naam}
                    </Link>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
    ];

    const { data, setData, post, processing, reset } = useForm({
        nummer: '',
        soort: '',
        merk: '',
        model: '',
        serienummer: '',
        status: 'beschikbaar',
        eigendom: 'soli',
        aanschafjaar: '',
        prijs: '',
        locatie: '',
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/instrumenten', {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    const handleSort = (key: string) => {
        const direction = filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get('/admin/instrumenten', { ...filters, sort: key, direction }, { preserveState: true, preserveScroll: true });
    };

    const handleStatusFilter = (status: string) => {
        const params: Record<string, string> = {};
        if (filters.search) params.search = filters.search;
        if (status !== 'all') params.status = status;
        router.get('/admin/instrumenten', params, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title={t("Instruments")} />
            <div className="space-y-4 p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">{t("Instruments")}</h2>
                        {can('instrumenten.create') && (
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button><Plus className="mr-2 h-4 w-4" />{t("New instrument")}</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader><DialogTitle>{t("New instrument")}</DialogTitle></DialogHeader>
                                    <form onSubmit={handleCreate} className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label>{t("Number")}</Label>
                                                <Input value={data.nummer} onChange={(e) => setData('nummer', e.target.value)} required />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t("Sort")}</Label>
                                                <Input value={data.soort} onChange={(e) => setData('soort', e.target.value)} required />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label>{t("Brand")}</Label>
                                                <Input value={data.merk} onChange={(e) => setData('merk', e.target.value)} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t("Model")}</Label>
                                                <Input value={data.model} onChange={(e) => setData('model', e.target.value)} />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label>{t("Status")}</Label>
                                                <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {statusOptions.map((s) => (
                                                            <SelectItem key={s} value={s}>{s.replace('_', ' ')}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t("Ownership")}</Label>
                                                <Select value={data.eigendom} onValueChange={(v) => setData('eigendom', v)}>
                                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {eigendomOptions.map((e) => (
                                                            <SelectItem key={e} value={e}>{e}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label>{t("Purchase year")}</Label>
                                                <Input type="number" value={data.aanschafjaar} onChange={(e) => setData('aanschafjaar', e.target.value)} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t("Price")}</Label>
                                                <Input type="number" step="0.01" value={data.prijs} onChange={(e) => setData('prijs', e.target.value)} />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t("Location")}</Label>
                                            <Input value={data.locatie} onChange={(e) => setData('locatie', e.target.value)} />
                                        </div>
                                        <Button type="submit" disabled={processing}>{t("Save")}</Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                        <div className="w-full sm:w-64">
                            <SearchInput
                                value={filters.search}
                                placeholder={t("Search by number, sort, brand...")}
                                routeName="/admin/instrumenten"
                                queryParams={{ status: filters.status }}
                            />
                        </div>

                        <Select value={filters.status ?? 'all'} onValueChange={handleStatusFilter}>
                            <SelectTrigger className="w-full sm:w-40">
                                <SelectValue placeholder={t("All statuses")} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t("All statuses")}</SelectItem>
                                <SelectItem value="beschikbaar">{t("Available")}</SelectItem>
                                <SelectItem value="in_gebruik">{t("In use")}</SelectItem>
                                <SelectItem value="in_reparatie">{t("In repair")}</SelectItem>
                                <SelectItem value="afgeschreven">{t("Written off")}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <DataTable
                        columns={columns}
                        data={instrumenten.data}
                        sortKey={filters.sort}
                        sortDirection={filters.direction}
                        onSort={handleSort}
                        emptyMessage={t("No instruments found.")}
                    />

                    <Pagination pagination={instrumenten} />
            </div>
        </AppLayout>
    );
}
