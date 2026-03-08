import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
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
import type { Relatie, RelatieType } from '@/types/admin';

type Props = {
    relatie: Relatie;
    relatieTypes: RelatieType[];
};

function EditTypeDialog({ relatieId, type }: { relatieId: number; type: RelatieType }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        van: type.pivot?.van ?? '',
        tot: type.pivot?.tot ?? '',
        functie: type.pivot?.functie ?? '',
        email: type.pivot?.email ?? '',
    });

    const isLid = type.naam === 'lid';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/types/${type.pivot!.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleEnd = () => {
        router.put(`/admin/relaties/${relatieId}/types/${type.pivot!.id}`, {
            ...data,
            tot: new Date().toISOString().split('T')[0],
        }, { onSuccess: () => setOpen(false) });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/types/${type.pivot!.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit type')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Type')}</Label>
                        <Input value={type.naam} disabled />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <Label>{t('From')}</Label>
                            <Input type="date" value={data.van} onChange={(e) => setData('van', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Until')}</Label>
                            <Input type="date" value={data.tot} onChange={(e) => setData('tot', e.target.value)} />
                        </div>
                    </div>
                    {!isLid && (
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>{t('Function (optional)')}</Label>
                                <Input value={data.functie} onChange={(e) => setData('functie', e.target.value)} placeholder={t('e.g. Chairman, Treasurer')} />
                            </div>
                            <div className="space-y-2">
                                <Label>{t('Email (optional)')}</Label>
                                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            </div>
                        </div>
                    )}
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
                            {!type.pivot?.tot && (
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

export default function RelatieTypesTab({ relatie, relatieTypes }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        relatie_type_id: '',
        van: new Date().toISOString().split('T')[0],
        tot: '',
        functie: '',
        email: '',
    });

    const selectedType = relatieTypes.find((t) => t.id.toString() === data.relatie_type_id);
    const isLid = selectedType?.naam === 'lid';

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatie.id}/types`, {
            onSuccess: () => {
                setOpen(false);
                reset();
            },
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{t('Types')}</CardTitle>
                {can('relaties.edit') && (
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button size="sm"><Plus className="mr-2 h-4 w-4" />{t('Add')}</Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t('Add type')}</DialogTitle>
                            </DialogHeader>
                            <form onSubmit={handleSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label>{t('Type')}</Label>
                                    <Select value={data.relatie_type_id} onValueChange={(v) => setData('relatie_type_id', v)}>
                                        <SelectTrigger><SelectValue placeholder={t('Select type')} /></SelectTrigger>
                                        <SelectContent>
                                            {relatieTypes.map((type) => (
                                                <SelectItem key={type.id} value={type.id.toString()}>{type.naam}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>{t('From')}</Label>
                                        <Input type="date" value={data.van} onChange={(e) => setData('van', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Until')}</Label>
                                        <Input type="date" value={data.tot} onChange={(e) => setData('tot', e.target.value)} />
                                    </div>
                                </div>
                                {!isLid && data.relatie_type_id && (
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label>{t('Function (optional)')}</Label>
                                            <Input value={data.functie} onChange={(e) => setData('functie', e.target.value)} placeholder={t('e.g. Chairman, Treasurer')} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Email (optional)')}</Label>
                                            <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                        </div>
                                    </div>
                                )}
                                <Button type="submit" disabled={processing}>{t('Save')}</Button>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}
            </CardHeader>
            <CardContent>
                {relatie.types && relatie.types.length > 0 ? (
                    <div className="space-y-3">
                        {relatie.types.map((type) => (
                            <div key={`${type.id}-${type.pivot?.van}`} className="flex items-center justify-between rounded-md border p-3">
                                <div className="flex items-center gap-3">
                                    <Badge variant="secondary">{type.naam}</Badge>
                                    {type.pivot?.functie && <span className="text-muted-foreground text-sm">{type.pivot.functie}</span>}
                                    {type.pivot?.email && <span className="text-muted-foreground text-sm">{type.pivot.email}</span>}
                                    {type.pivot && <DateRangeDisplay van={type.pivot.van} tot={type.pivot.tot} />}
                                </div>
                                {can('relaties.edit') && type.pivot && (
                                    <EditTypeDialog relatieId={relatie.id} type={type} />
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">{t('No types.')}</p>
                )}
            </CardContent>
        </Card>
    );
}
