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
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Onderdeel } from '@/types/admin';
import { Link } from '@inertiajs/react';

type Props = {
    onderdelen: Onderdeel[];
    filters: {
        type?: string;
        show_inactive?: string;
    };
};

const typeOptions = ['orkest', 'opleidingsgroep', 'ensemble', 'commissie', 'bestuur', 'staff', 'overig'];

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
            key: 'type',
            label: t('Type'),
            render: (o) => <Badge variant="outline">{o.type}</Badge>,
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

    const { data, setData, post, processing, reset } = useForm({
        naam: '',
        type: 'orkest',
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
                                            <Label>{t("Name")}</Label>
                                            <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t("Type")}</Label>
                                            <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                                                <SelectTrigger><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    {typeOptions.map((tp) => (
                                                        <SelectItem key={tp} value={tp}>{tp}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t("Description")}</Label>
                                            <Input value={data.beschrijving} onChange={(e) => setData('beschrijving', e.target.value)} />
                                        </div>
                                        <Button type="submit" disabled={processing}>{t("Save")}</Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>

                    <div className="flex items-center gap-4">
                        <Select value={filters.type ?? 'all'} onValueChange={handleTypeFilter}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder={t("All types")} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t("All types")}</SelectItem>
                                {typeOptions.map((tp) => (
                                    <SelectItem key={tp} value={tp}>{tp}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="show-inactive"
                                checked={filters.show_inactive === '1'}
                                onCheckedChange={(checked) => {
                                    const params: Record<string, string> = {};
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
