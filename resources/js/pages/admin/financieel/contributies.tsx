import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import FinancieelLayout from '@/layouts/admin/financieel-layout';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Contributie, SoortContributie, Tariefgroep } from '@/types/admin';

type Props = {
    contributies: Contributie[];
    tariefgroepen: Tariefgroep[];
    soortContributies: SoortContributie[];
    jaar: number;
    beschikbareJaren: number[];
};

const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(amount));
};

function AddDialog({ tariefgroepen, soortContributies, jaar }: { tariefgroepen: Tariefgroep[]; soortContributies: SoortContributie[]; jaar: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        tariefgroep_id: '',
        soort_contributie_id: '',
        jaar: jaar,
        bedrag: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/financieel/contributies', { onSuccess: () => { setOpen(false); reset(); } });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t("Add rate")}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t("Contribution rate")}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t("Rate group")}</Label>
                        <Select value={data.tariefgroep_id} onValueChange={(v) => setData('tariefgroep_id', v)}>
                            <SelectTrigger><SelectValue placeholder={t("Select...")} /></SelectTrigger>
                            <SelectContent>
                                {tariefgroepen.map((tg) => (<SelectItem key={tg.id} value={tg.id.toString()}>{tg.naam}</SelectItem>))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>{t("Sort")}</Label>
                        <Select value={data.soort_contributie_id} onValueChange={(v) => setData('soort_contributie_id', v)}>
                            <SelectTrigger><SelectValue placeholder={t("Select...")} /></SelectTrigger>
                            <SelectContent>
                                {soortContributies.map((s) => (<SelectItem key={s.id} value={s.id.toString()}>{s.naam}</SelectItem>))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t("Year")}</Label>
                            <Input type="number" value={data.jaar} onChange={(e) => setData('jaar', parseInt(e.target.value))} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t("Amount")}</Label>
                            <Input type="number" step="0.01" value={data.bedrag} onChange={(e) => setData('bedrag', e.target.value)} required />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>{t("Save")}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Contributies({ contributies, tariefgroepen, soortContributies, jaar, beschikbareJaren }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t("Contributions")} />
            <div className="p-4">
                <FinancieelLayout>
                    <div className="space-y-4">
                        <div className="flex items-center gap-4">
                            <Select value={jaar.toString()} onValueChange={(v) => router.get('/admin/financieel/contributies', { jaar: v }, { preserveState: true })}>
                                <SelectTrigger className="w-32"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {beschikbareJaren.map((j) => (<SelectItem key={j} value={j.toString()}>{j}</SelectItem>))}
                                </SelectContent>
                            </Select>

                            {can('financieel.edit') && (
                                <AddDialog tariefgroepen={tariefgroepen} soortContributies={soortContributies} jaar={jaar} />
                            )}
                        </div>

                        {contributies.length > 0 ? (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="bg-muted/50 border-b">
                                            <th className="px-4 py-3 text-left font-medium">{t("Rate group")}</th>
                                            <th className="px-4 py-3 text-left font-medium">{t("Sort")}</th>
                                            <th className="px-4 py-3 text-right font-medium">{t("Amount")}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {contributies.map((c) => (
                                            <tr key={c.id} className="border-b last:border-0">
                                                <td className="px-4 py-3">{c.tariefgroep?.naam}</td>
                                                <td className="px-4 py-3">{c.soort_contributie?.naam}</td>
                                                <td className="px-4 py-3 text-right font-semibold">{formatCurrency(c.bedrag)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="text-muted-foreground text-sm">{t('No rates for :year.', { year: jaar })}</p>
                        )}
                    </div>
                </FinancieelLayout>
            </div>
        </AppLayout>
    );
}
