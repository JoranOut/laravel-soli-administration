import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import FinancieelLayout from '@/layouts/admin/financieel-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Pagination } from '@/components/admin/pagination';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { PaginatedResponse, TeBetakenContributie } from '@/types/admin';

type Props = {
    openstaand: PaginatedResponse<TeBetakenContributie & { relatie?: { id: number; volledige_naam: string } }>;
    filters: { status: string };
};

const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(amount));
};

const statusVariant = (status: string) => {
    switch (status) {
        case 'betaald': return 'default' as const;
        case 'open': return 'destructive' as const;
        case 'kwijtgescholden': return 'outline' as const;
        default: return 'secondary' as const;
    }
};

function PayDialog({ teBetakenContributieId }: { teBetakenContributieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        bedrag: '',
        datum: new Date().toISOString().split('T')[0],
        methode: '',
        opmerking: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/financieel/betalingen/${teBetakenContributieId}`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">{t("Payment")}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t("Register payment")}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t("Amount")}</Label>
                            <Input type="number" step="0.01" value={data.bedrag} onChange={(e) => setData('bedrag', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t("Date")}</Label>
                            <Input type="date" value={data.datum} onChange={(e) => setData('datum', e.target.value)} required />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>{t("Method")}</Label>
                        <Input value={data.methode} onChange={(e) => setData('methode', e.target.value)} placeholder={t("e.g. iDEAL, cash, transfer")} />
                    </div>
                    <div className="space-y-2">
                        <Label>{t("Remark")}</Label>
                        <Input value={data.opmerking} onChange={(e) => setData('opmerking', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>{t("Register")}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Betalingen({ openstaand, filters }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t("Payments")} />
            <div className="p-4">
                <FinancieelLayout>
                    <div className="space-y-4">
                        <Select value={filters.status} onValueChange={(v) => router.get('/admin/financieel/betalingen', { status: v }, { preserveState: true })}>
                            <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t("All")}</SelectItem>
                                <SelectItem value="open">{t("Open")}</SelectItem>
                                <SelectItem value="betaald">{t("Paid")}</SelectItem>
                                <SelectItem value="kwijtgescholden">{t("Waived")}</SelectItem>
                            </SelectContent>
                        </Select>

                        {openstaand.data.length > 0 ? (
                            <div className="space-y-3">
                                {openstaand.data.map((item) => (
                                    <div key={item.id} className="flex items-center justify-between rounded-md border p-4">
                                        <div>
                                            {item.relatie && (
                                                <Link href={`/admin/relaties/${item.relatie.id}`} className="text-primary hover:underline font-medium">
                                                    {item.relatie.volledige_naam}
                                                </Link>
                                            )}
                                            <p className="text-muted-foreground text-sm">
                                                {item.contributie?.soort_contributie?.naam} — {item.jaar}
                                                {item.contributie?.tariefgroep && ` (${item.contributie.tariefgroep.naam})`}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="font-semibold">{formatCurrency(item.bedrag)}</span>
                                            <Badge variant={statusVariant(item.status)}>{item.status}</Badge>
                                            {can('financieel.edit') && item.status === 'open' && (
                                                <PayDialog teBetakenContributieId={item.id} />
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">{t("No items found.")}</p>
                        )}

                        <Pagination pagination={openstaand} />
                    </div>
                </FinancieelLayout>
            </div>
        </AppLayout>
    );
}
