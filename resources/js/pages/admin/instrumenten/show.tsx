import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Wrench, Pencil } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DateRangeDisplay } from '@/components/admin/date-range-display';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Instrument, InstrumentBespeler, Relatie } from '@/types/admin';

type Props = {
    instrument: Instrument;
    relaties: Pick<Relatie, 'id' | 'voornaam' | 'tussenvoegsel' | 'achternaam' | 'volledige_naam'>[];
};

function AssignDialog({ instrumentId, relaties }: { instrumentId: number; relaties: Props['relaties'] }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        relatie_id: '',
        van: new Date().toISOString().split('T')[0],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/instrumenten/${instrumentId}/bespelers`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t("Assign")}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t("Assign instrument")}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t("Relation")}</Label>
                        <Select value={data.relatie_id} onValueChange={(v) => setData('relatie_id', v)}>
                            <SelectTrigger><SelectValue placeholder={t("Select...")} /></SelectTrigger>
                            <SelectContent>
                                {relaties.map((r) => (
                                    <SelectItem key={r.id} value={r.id.toString()}>{r.volledige_naam}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        <Label>{t("From")}</Label>
                        <Input type="date" value={data.van} onChange={(e) => setData('van', e.target.value)} required />
                    </div>
                    <Button type="submit" disabled={processing}>{t("Assign")}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddReparatieDialog({ instrumentId }: { instrumentId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        beschrijving: '',
        reparateur: '',
        kosten: '',
        datum_in: new Date().toISOString().split('T')[0],
        datum_uit: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/instrumenten/${instrumentId}/reparaties`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline"><Wrench className="mr-2 h-4 w-4" />{t("Repair")}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t("Add repair")}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t("Description")}</Label>
                        <Input value={data.beschrijving} onChange={(e) => setData('beschrijving', e.target.value)} required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t("Repairer")}</Label>
                            <Input value={data.reparateur} onChange={(e) => setData('reparateur', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t("Costs")}</Label>
                            <Input type="number" step="0.01" value={data.kosten} onChange={(e) => setData('kosten', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t("Date in")}</Label>
                            <Input type="date" value={data.datum_in} onChange={(e) => setData('datum_in', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t("Date out")}</Label>
                            <Input type="date" value={data.datum_uit} onChange={(e) => setData('datum_uit', e.target.value)} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>{t("Save")}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditBespelerDialog({ instrumentId, bespeler }: { instrumentId: number; bespeler: InstrumentBespeler }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, put, processing } = useForm({
        van: bespeler.van,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/instrumenten/${instrumentId}/bespelers/${bespeler.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleEnd = () => {
        router.delete(`/admin/instrumenten/${instrumentId}/bespelers/${bespeler.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit player')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {bespeler.relatie && (
                        <div className="space-y-2">
                            <Label>{t('Relation')}</Label>
                            <Input value={bespeler.relatie.volledige_naam} disabled />
                        </div>
                    )}
                    <div className="space-y-2">
                        <Label>{t('From')}</Label>
                        <Input type="date" value={data.van} onChange={(e) => setData('van', e.target.value)} required />
                    </div>
                    <div className="flex items-center justify-end border-t pt-4">
                        <div className="flex gap-2">
                            {!bespeler.tot && (
                                <Button type="button" variant="secondary" onClick={handleEnd} disabled={processing}>
                                    {t('End')}
                                </Button>
                            )}
                            <Button type="submit" disabled={processing}>{t('Save')}</Button>
                        </div>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const statusVariant = (status: string) => {
    switch (status) {
        case 'beschikbaar': return 'default' as const;
        case 'in_gebruik': return 'secondary' as const;
        case 'in_reparatie': return 'destructive' as const;
        case 'afgeschreven': return 'outline' as const;
        default: return 'secondary' as const;
    }
};

const formatCurrency = (amount: string | number | null) => {
    if (!amount) return '—';
    return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(amount));
};

export default function InstrumentShow({ instrument, relaties }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={`${t("Instrument")} ${instrument.nummer}`} />
            <div className="space-y-6 p-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/admin/instrumenten"><ArrowLeft className="mr-2 h-4 w-4" />{t("Back")}</Link>
                    </Button>

                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-2xl font-bold">{instrument.nummer} — {instrument.soort}</h2>
                            <p className="text-muted-foreground">
                                {[instrument.merk, instrument.model].filter(Boolean).join(' ')}
                                {instrument.serienummer && ` (S/N: ${instrument.serienummer})`}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Badge variant={statusVariant(instrument.status)}>{instrument.status.replace('_', ' ')}</Badge>
                            <Badge variant="outline">{instrument.eigendom}</Badge>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-muted-foreground text-sm">{t("Purchase year")}</p>
                                <p className="font-semibold">{instrument.aanschafjaar ?? '—'}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-muted-foreground text-sm">{t("Price")}</p>
                                <p className="font-semibold">{formatCurrency(instrument.prijs)}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-muted-foreground text-sm">{t("Location")}</p>
                                <p className="font-semibold">{instrument.locatie ?? '—'}</p>
                            </CardContent>
                        </Card>
                    </div>

                    {can('instrumenten.edit') && (
                        <div className="flex gap-2">
                            <AssignDialog instrumentId={instrument.id} relaties={relaties} />
                            <AddReparatieDialog instrumentId={instrument.id} />
                        </div>
                    )}

                    {/* Players */}
                    <Card>
                        <CardHeader><CardTitle>{t("Players")}</CardTitle></CardHeader>
                        <CardContent>
                            {instrument.bespelers && instrument.bespelers.length > 0 ? (
                                <div className="space-y-3">
                                    {instrument.bespelers.map((b) => (
                                        <div key={b.id} className="flex items-center justify-between rounded-md border p-3">
                                            <div>
                                                {b.relatie && (
                                                    <Link href={`/admin/relaties/${b.relatie.id}`} className="text-primary hover:underline font-medium">
                                                        {b.relatie.volledige_naam}
                                                    </Link>
                                                )}
                                                <div className="flex items-center gap-2">
                                                    <DateRangeDisplay van={b.van} tot={b.tot} />
                                                    {!b.tot && <Badge>{t("Current")}</Badge>}
                                                </div>
                                            </div>
                                            {can('instrumenten.edit') && (
                                                <EditBespelerDialog instrumentId={instrument.id} bespeler={b} />
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t("No players.")}</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Repairs */}
                    <Card>
                        <CardHeader><CardTitle>{t("Repairs")}</CardTitle></CardHeader>
                        <CardContent>
                            {instrument.reparaties && instrument.reparaties.length > 0 ? (
                                <div className="space-y-3">
                                    {instrument.reparaties.map((r) => (
                                        <div key={r.id} className="rounded-md border p-3">
                                            <div className="flex items-center justify-between">
                                                <p className="font-medium">{r.beschrijving}</p>
                                                {r.kosten && <span className="font-semibold">{formatCurrency(r.kosten)}</span>}
                                            </div>
                                            <div className="text-muted-foreground flex gap-2 text-sm">
                                                {r.reparateur && <span>{r.reparateur}</span>}
                                                <DateRangeDisplay van={r.datum_in} tot={r.datum_uit} />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t("No repairs.")}</p>
                            )}
                        </CardContent>
                    </Card>

                    {/* Details */}
                    {instrument.bijzonderheden && instrument.bijzonderheden.length > 0 && (
                        <Card>
                            <CardHeader><CardTitle>{t("Details")}</CardTitle></CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {instrument.bijzonderheden.map((b) => (
                                        <div key={b.id} className="rounded-md border p-3">
                                            <p>{b.beschrijving}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {new Date(b.datum).toLocaleDateString('nl-NL')}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}
            </div>
        </AppLayout>
    );
}
