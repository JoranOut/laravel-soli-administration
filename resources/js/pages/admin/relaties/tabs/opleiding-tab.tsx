import { router, useForm } from '@inertiajs/react';
import { GraduationCap, Award, FileCheck, Plus, Pencil } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Relatie, Opleiding, Insigne, Diploma } from '@/types/admin';

type Props = {
    relatie: Relatie;
};

function AddOpleidingDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        naam: '',
        instituut: '',
        instrument: '',
        diploma: '',
        datum_start: '',
        datum_einde: '',
        opmerking: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/opleidingen`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t('Training')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add training')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Name')}</Label>
                        <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('Institute')}</Label>
                            <Input value={data.instituut} onChange={(e) => setData('instituut', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Instrument')}</Label>
                            <Input value={data.instrument} onChange={(e) => setData('instrument', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Diploma')}</Label>
                        <Input value={data.diploma} onChange={(e) => setData('diploma', e.target.value)} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('Start date')}</Label>
                            <Input type="date" value={data.datum_start} onChange={(e) => setData('datum_start', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('End date')}</Label>
                            <Input type="date" value={data.datum_einde} onChange={(e) => setData('datum_einde', e.target.value)} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditOpleidingDialog({ relatieId, opl }: { relatieId: number; opl: Opleiding }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        naam: opl.naam,
        instituut: opl.instituut ?? '',
        instrument: opl.instrument ?? '',
        diploma: opl.diploma ?? '',
        datum_start: opl.datum_start ?? '',
        datum_einde: opl.datum_einde ?? '',
        opmerking: opl.opmerking ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/opleidingen/${opl.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleEnd = () => {
        router.put(`/admin/relaties/${relatieId}/opleidingen/${opl.id}`, {
            ...data,
            datum_einde: new Date().toISOString().split('T')[0],
        }, { onSuccess: () => setOpen(false) });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/opleidingen/${opl.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit training')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Name')}</Label>
                        <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('Institute')}</Label>
                            <Input value={data.instituut} onChange={(e) => setData('instituut', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Instrument')}</Label>
                            <Input value={data.instrument} onChange={(e) => setData('instrument', e.target.value)} />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Diploma')}</Label>
                        <Input value={data.diploma} onChange={(e) => setData('diploma', e.target.value)} />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('Start date')}</Label>
                            <Input type="date" value={data.datum_start} onChange={(e) => setData('datum_start', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('End date')}</Label>
                            <Input type="date" value={data.datum_einde} onChange={(e) => setData('datum_einde', e.target.value)} />
                        </div>
                    </div>
                    <div className="flex items-center justify-between border-t pt-4">
                        <div>
                            {!confirmDelete ? (
                                <Button type="button" variant="link" size="sm" className="text-destructive p-0 h-auto" onClick={() => setConfirmDelete(true)}>
                                    {t('Delete permanently')}
                                </Button>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <span className="text-destructive text-sm">{t('Are you sure?')}</span>
                                    <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>{t('Delete')}</Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setConfirmDelete(false)}>{t('Cancel')}</Button>
                                </div>
                            )}
                        </div>
                        <div className="flex gap-2">
                            {!opl.datum_einde && (
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

function AddDiplomaDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        naam: '',
        instrument: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/diplomas`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t("Diploma's")}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add diploma')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Name')}</Label>
                        <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Instrument')}</Label>
                        <Input value={data.instrument} onChange={(e) => setData('instrument', e.target.value)} />
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditDiplomaDialog({ relatieId, diploma }: { relatieId: number; diploma: Diploma }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        naam: diploma.naam,
        instrument: diploma.instrument ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/diplomas/${diploma.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/diplomas/${diploma.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit diploma')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Name')}</Label>
                        <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Instrument')}</Label>
                        <Input value={data.instrument} onChange={(e) => setData('instrument', e.target.value)} />
                    </div>
                    <div className="flex items-center justify-between border-t pt-4">
                        <div>
                            {!confirmDelete ? (
                                <Button type="button" variant="link" size="sm" className="text-destructive p-0 h-auto" onClick={() => setConfirmDelete(true)}>
                                    {t('Delete permanently')}
                                </Button>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <span className="text-destructive text-sm">{t('Are you sure?')}</span>
                                    <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>{t('Delete')}</Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setConfirmDelete(false)}>{t('Cancel')}</Button>
                                </div>
                            )}
                        </div>
                        <Button type="submit" disabled={processing}>{t('Save')}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddInsigneDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        naam: '',
        datum: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/insignes`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t('Badges')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add badge')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Name')}</Label>
                        <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Date')}</Label>
                        <Input type="date" value={data.datum} onChange={(e) => setData('datum', e.target.value)} required />
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditInsigneDialog({ relatieId, ins }: { relatieId: number; ins: Insigne }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        naam: ins.naam,
        datum: ins.datum,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/insignes/${ins.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/insignes/${ins.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit badge')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Name')}</Label>
                        <Input value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                    </div>
                    <div className="space-y-2">
                        <Label>{t('Date')}</Label>
                        <Input type="date" value={data.datum} onChange={(e) => setData('datum', e.target.value)} required />
                    </div>
                    <div className="flex items-center justify-between border-t pt-4">
                        <div>
                            {!confirmDelete ? (
                                <Button type="button" variant="link" size="sm" className="text-destructive p-0 h-auto" onClick={() => setConfirmDelete(true)}>
                                    {t('Delete permanently')}
                                </Button>
                            ) : (
                                <div className="flex items-center gap-2">
                                    <span className="text-destructive text-sm">{t('Are you sure?')}</span>
                                    <Button type="button" variant="destructive" size="sm" onClick={handleDelete}>{t('Delete')}</Button>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setConfirmDelete(false)}>{t('Cancel')}</Button>
                                </div>
                            )}
                        </div>
                        <Button type="submit" disabled={processing}>{t('Save')}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function RelatieOpleidingTab({ relatie }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    const formatDate = (date: string | null) => {
        if (!date) return '—';
        return new Date(date).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' });
    };

    return (
        <div className="space-y-6">
            {can('relaties.edit') && (
                <div className="flex flex-wrap gap-2">
                    <AddOpleidingDialog relatieId={relatie.id} />
                    <AddDiplomaDialog relatieId={relatie.id} />
                    <AddInsigneDialog relatieId={relatie.id} />
                </div>
            )}

            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2"><GraduationCap className="h-4 w-4" />{t('Training')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.opleidingen && relatie.opleidingen.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.opleidingen.map((opl) => (
                                <div key={opl.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="font-medium">{opl.naam}</p>
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            {opl.instituut && <span>{opl.instituut}</span>}
                                            {opl.instrument && <Badge variant="outline">{opl.instrument}</Badge>}
                                            {opl.diploma && <Badge variant="secondary">{opl.diploma}</Badge>}
                                        </div>
                                        <p className="text-muted-foreground text-xs mt-1">
                                            {formatDate(opl.datum_start)} – {formatDate(opl.datum_einde)}
                                        </p>
                                    </div>
                                    {can('relaties.edit') && (
                                        <EditOpleidingDialog relatieId={relatie.id} opl={opl} />
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No training.')}</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2"><FileCheck className="h-4 w-4" />{t("Diploma's")}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.diplomas && relatie.diplomas.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.diplomas.map((diploma) => (
                                <div key={diploma.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-medium">{diploma.naam}</p>
                                        {diploma.instrument && <Badge variant="outline">{diploma.instrument}</Badge>}
                                    </div>
                                    {can('relaties.edit') && (
                                        <EditDiplomaDialog relatieId={relatie.id} diploma={diploma} />
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t("No diploma's.")}</p>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2"><Award className="h-4 w-4" />{t('Badges')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.insignes && relatie.insignes.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.insignes.map((ins) => (
                                <div key={ins.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="font-medium">{ins.naam}</p>
                                        <p className="text-muted-foreground text-xs mt-1">{formatDate(ins.datum)}</p>
                                    </div>
                                    {can('relaties.edit') && (
                                        <EditInsigneDialog relatieId={relatie.id} ins={ins} />
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No badges.')}</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
