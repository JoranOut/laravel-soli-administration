import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DataTable, type Column } from '@/components/admin/data-table';
import { SearchInput } from '@/components/admin/search-input';
import InputError from '@/components/input-error';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { ONDERDEEL_TYPES } from '@/constants/onderdeel';
import type { Onderdeel } from '@/types/admin';
import { Link } from '@inertiajs/react';

type Props = {
    onderdelen: Onderdeel[];
    filters: {
        search?: string;
        type?: string;
        show_inactive?: string;
    };
};

export default function OnderdelenIndex({ onderdelen, filters }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const columns: Column<Onderdeel>[] = [
        {
            key: 'naam',
            label: t('Name'),
            render: (o) => (
                <Link href={`/admin/onderdelen/${o.id}`} className="text-primary hover:underline font-medium">
                    {o.naam}
                </Link>
            ),
        },
        {
            key: 'afkorting',
            label: t('Abbreviation'),
            render: (o) => o.afkorting ?? '—',
        },
        {
            key: 'type',
            label: t('Type'),
            render: (o) => <Badge variant="outline">{t(o.type)}</Badge>,
        },
        {
            key: 'actieve_relaties_count',
            label: t('Members'),
            render: (o) => o.actieve_relaties_count ?? 0,
        },
        {
            key: 'actief',
            label: t('Status'),
            render: (o) => <Badge variant={o.actief ? 'default' : 'outline'}>{o.actief ? t('Active') : t('Inactive')}</Badge>,
        },
    ];

    const { data, setData, post, processing, reset, errors } = useForm({
        naam: '',
        afkorting: '',
        type: 'muziekgroep',
        beschrijving: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/onderdelen', {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    const handleTypeFilter = (type: string) => {
        const params: Record<string, string> = {};
        if (filters.search) params.search = filters.search;
        if (filters.show_inactive) params.show_inactive = filters.show_inactive;
        if (type !== 'all') params.type = type;
        router.get('/admin/onderdelen', params, { preserveState: true });
    };

    return (
        <AppLayout>
            <Head title={t("Sections")} />
            <div className="space-y-4 p-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">{t("Sections")}</h2>
                        {can('onderdelen.create') && (
                            <Dialog open={open} onOpenChange={setOpen}>
                                <DialogTrigger asChild>
                                    <Button><Plus className="mr-2 h-4 w-4" />{t("New section")}</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader><DialogTitle>{t("New section")}</DialogTitle></DialogHeader>
                                    <form onSubmit={handleSubmit} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="create-naam">{t("Name")}</Label>
                                            <Input id="create-naam" value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                                            <InputError message={errors.naam} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="create-afkorting">{t("Abbreviation")}</Label>
                                            <Input id="create-afkorting" value={data.afkorting} onChange={(e) => setData('afkorting', e.target.value)} maxLength={10} />
                                            <InputError message={errors.afkorting} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="create-type">{t("Type")}</Label>
                                            <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                                                <SelectTrigger id="create-type"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    {ONDERDEEL_TYPES.map((tp) => (
                                                        <SelectItem key={tp} value={tp}>{t(tp)}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.type} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="create-beschrijving">{t("Description")}</Label>
                                            <Input id="create-beschrijving" value={data.beschrijving} onChange={(e) => setData('beschrijving', e.target.value)} />
                                            <InputError message={errors.beschrijving} />
                                        </div>
                                        <Button type="submit" disabled={processing}>{t("Save")}</Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>

                    <div className="flex items-center gap-4">
                        <div className="w-full sm:w-64">
                            <SearchInput
                                value={filters.search}
                                placeholder={t('Search...')}
                                routeName="/admin/onderdelen"
                                queryParams={{
                                    type: filters.type,
                                    show_inactive: filters.show_inactive,
                                }}
                            />
                        </div>

                        <Select value={filters.type ?? 'all'} onValueChange={handleTypeFilter}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder={t("All types")} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t("All types")}</SelectItem>
                                {ONDERDEEL_TYPES.map((tp) => (
                                    <SelectItem key={tp} value={tp}>{t(tp)}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="show-inactive"
                                checked={filters.show_inactive === '1'}
                                onCheckedChange={(checked) => {
                                    const params: Record<string, string> = {};
                                    if (filters.search) params.search = filters.search;
                                    if (filters.type) params.type = filters.type;
                                    if (checked) params.show_inactive = '1';
                                    router.get('/admin/onderdelen', params, { preserveState: true });
                                }}
                            />
                            <label htmlFor="show-inactive" className="text-sm">{t("Show inactive")}</label>
                        </div>
                    </div>

                    <DataTable columns={columns} data={onderdelen} />
            </div>
        </AppLayout>
    );
}
