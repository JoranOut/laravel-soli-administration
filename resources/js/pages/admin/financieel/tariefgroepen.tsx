import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import FinancieelLayout from '@/layouts/admin/financieel-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Tariefgroep } from '@/types/admin';

type Props = {
    tariefgroepen: Tariefgroep[];
};

function AddDialog() {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({ naam: '', beschrijving: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/financieel/tariefgroepen', { onSuccess: () => { setOpen(false); reset(); } });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t("Add")}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t("Add rate group")}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t("Name")}</Label>
                        <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                    </div>
                    <div className="space-y-2">
                        <Label>{t("Description")}</Label>
                        <Input value={data.beschrijving} onChange={(e) => setData('beschrijving', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>{t("Save")}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Tariefgroepen({ tariefgroepen }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t("Rate groups")} />
            <div className="p-4">
                <FinancieelLayout>
                    <div className="space-y-4">
                        {can('financieel.create') && <AddDialog />}

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {tariefgroepen.map((groep) => (
                                <Card key={groep.id}>
                                    <CardContent className="pt-6">
                                        <h3 className="font-semibold">{groep.naam}</h3>
                                        <p className="text-muted-foreground text-sm">{groep.beschrijving ?? '—'}</p>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {tariefgroepen.length === 0 && (
                            <p className="text-muted-foreground text-sm">{t("No rate groups.")}</p>
                        )}
                    </div>
                </FinancieelLayout>
            </div>
        </AppLayout>
    );
}
