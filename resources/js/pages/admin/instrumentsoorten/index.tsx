import { Head, useForm } from '@inertiajs/react';
import { Info, Pencil, Plus, Settings, Trash } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import { DataTable, type Column } from '@/components/admin/data-table';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { InstrumentFamilie, InstrumentSoort } from '@/types/admin';

type InstrumentSoortWithCount = InstrumentSoort & {
    relatie_instrumenten_count: number;
};

type Props = {
    instrumentSoorten: InstrumentSoortWithCount[];
    families: InstrumentFamilie[];
};

export default function InstrumentSoortenIndex({ instrumentSoorten, families }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();
    const [addOpen, setAddOpen] = useState(false);
    const [editItem, setEditItem] = useState<InstrumentSoortWithCount | null>(null);
    const [deleteItem, setDeleteItem] = useState<InstrumentSoortWithCount | null>(null);
    const [deleteConfirmation, setDeleteConfirmation] = useState('');
    const [familiesOpen, setFamiliesOpen] = useState(false);
    const [editFamily, setEditFamily] = useState<InstrumentFamilie | null>(null);

    const addForm = useForm({ naam: '', instrument_familie_id: '' });
    const editForm = useForm({ naam: '', instrument_familie_id: '' });
    const deleteForm = useForm({});

    const addFamilyForm = useForm({ naam: '' });
    const editFamilyForm = useForm({ naam: '' });
    const deleteFamilyForm = useForm({});

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post('/admin/instrumentsoorten', {
            onSuccess: () => { setAddOpen(false); addForm.reset(); },
        });
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editItem) return;
        editForm.put(`/admin/instrumentsoorten/${editItem.id}`, {
            onSuccess: () => { setEditItem(null); editForm.reset(); },
        });
    };

    const handleDelete = () => {
        if (!deleteItem) return;
        deleteForm.delete(`/admin/instrumentsoorten/${deleteItem.id}`, {
            onSuccess: () => { setDeleteItem(null); setDeleteConfirmation(''); },
        });
    };

    const openEdit = (item: InstrumentSoortWithCount) => {
        editForm.setData({ naam: item.naam, instrument_familie_id: String(item.instrument_familie_id) });
        setEditItem(item);
    };

    const openDelete = (item: InstrumentSoortWithCount) => {
        setDeleteConfirmation('');
        setDeleteItem(item);
    };

    const handleAddFamily = (e: React.FormEvent) => {
        e.preventDefault();
        addFamilyForm.post('/admin/instrumentsoorten/families', {
            onSuccess: () => addFamilyForm.reset(),
        });
    };

    const handleEditFamily = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editFamily) return;
        editFamilyForm.put(`/admin/instrumentsoorten/families/${editFamily.id}`, {
            onSuccess: () => { setEditFamily(null); editFamilyForm.reset(); },
        });
    };

    const handleDeleteFamily = (family: InstrumentFamilie) => {
        deleteFamilyForm.delete(`/admin/instrumentsoorten/families/${family.id}`);
    };

    const openEditFamily = (family: InstrumentFamilie) => {
        editFamilyForm.setData({ naam: family.naam });
        setEditFamily(family);
    };

    const familyHasSoorten = (familyId: number) =>
        instrumentSoorten.some((s) => s.instrument_familie_id === familyId);

    const columns: Column<InstrumentSoortWithCount>[] = [
        {
            key: 'naam',
            label: t('Name'),
        },
        {
            key: 'instrument_familie',
            label: t('Family'),
            render: (item) => item.instrument_familie?.naam ?? '',
        },
        {
            key: 'relatie_instrumenten_count',
            label: t('Members'),
            render: (item) => item.relatie_instrumenten_count,
        },
        ...(can('instrumentsoorten.edit') || can('instrumentsoorten.delete')
            ? [{
                key: 'actions' as const,
                label: t('Actions'),
                render: (item: InstrumentSoortWithCount) => (
                    <div className="flex items-center gap-1">
                        {can('instrumentsoorten.edit') && (
                            <Button variant="ghost" size="sm" onClick={() => openEdit(item)}>
                                <Pencil className="h-4 w-4" />
                            </Button>
                        )}
                        {can('instrumentsoorten.delete') && (
                            <Button variant="ghost" size="sm" onClick={() => openDelete(item)}>
                                <Trash className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                ),
            }]
            : []),
    ];

    return (
        <AppLayout>
            <Head title={t('Instrument types')} />
            <div className="space-y-4 p-4">
                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertTitle>{t('Instrument types vs. Soli instruments')}</AlertTitle>
                    <AlertDescription>
                        {t('instrument_types_explanation')}
                    </AlertDescription>
                </Alert>

                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-semibold">{t('Instrument types')}</h2>
                    <div className="flex items-center gap-2">
                        {can('instrumentsoorten.edit') && (
                            <Dialog open={familiesOpen} onOpenChange={setFamiliesOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline"><Settings className="mr-2 h-4 w-4" />{t('Manage families')}</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader><DialogTitle>{t('Manage families')}</DialogTitle></DialogHeader>
                                    <div className="space-y-4">
                                        <div className="max-h-64 space-y-2 overflow-y-auto">
                                            {families.map((family) => (
                                                <div key={family.id} className="flex items-center justify-between rounded border p-2">
                                                    {editFamily?.id === family.id ? (
                                                        <form onSubmit={handleEditFamily} className="flex flex-1 items-center gap-2">
                                                            <Input
                                                                value={editFamilyForm.data.naam}
                                                                onChange={(e) => editFamilyForm.setData('naam', e.target.value)}
                                                                className="h-8"
                                                                required
                                                            />
                                                            <Button type="submit" size="sm" disabled={editFamilyForm.processing}>{t('Save')}</Button>
                                                            <Button type="button" variant="ghost" size="sm" onClick={() => setEditFamily(null)}>{t('Cancel')}</Button>
                                                        </form>
                                                    ) : (
                                                        <>
                                                            <span className="text-sm">{family.naam}</span>
                                                            <div className="flex items-center gap-1">
                                                                {can('instrumentsoorten.edit') && (
                                                                    <Button variant="ghost" size="sm" onClick={() => openEditFamily(family)}>
                                                                        <Pencil className="h-3 w-3" />
                                                                    </Button>
                                                                )}
                                                                {can('instrumentsoorten.delete') && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="sm"
                                                                        disabled={familyHasSoorten(family.id) || deleteFamilyForm.processing}
                                                                        onClick={() => handleDeleteFamily(family)}
                                                                    >
                                                                        <Trash className="h-3 w-3" />
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                        <InputError message={editFamilyForm.errors.naam} />
                                        {can('instrumentsoorten.create') && (
                                            <form onSubmit={handleAddFamily} className="flex items-center gap-2">
                                                <Input
                                                    placeholder={t('Name')}
                                                    value={addFamilyForm.data.naam}
                                                    onChange={(e) => addFamilyForm.setData('naam', e.target.value)}
                                                    className="h-8"
                                                    required
                                                />
                                                <Button type="submit" size="sm" disabled={addFamilyForm.processing}>
                                                    <Plus className="mr-1 h-3 w-3" />{t('Add')}
                                                </Button>
                                            </form>
                                        )}
                                        <InputError message={addFamilyForm.errors.naam} />
                                    </div>
                                </DialogContent>
                            </Dialog>
                        )}
                        {can('instrumentsoorten.create') && (
                            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                                <DialogTrigger asChild>
                                    <Button><Plus className="mr-2 h-4 w-4" />{t('Add instrument type')}</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader><DialogTitle>{t('Add instrument type')}</DialogTitle></DialogHeader>
                                    <form onSubmit={handleAdd} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="add-naam">{t('Name')}</Label>
                                            <Input id="add-naam" value={addForm.data.naam} onChange={(e) => addForm.setData('naam', e.target.value)} required />
                                            <InputError message={addForm.errors.naam} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Family')}</Label>
                                            <Select value={addForm.data.instrument_familie_id} onValueChange={(v) => addForm.setData('instrument_familie_id', v)}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('Select...')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {families.map((f) => (
                                                        <SelectItem key={f.id} value={String(f.id)}>{f.naam}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={addForm.errors.instrument_familie_id} />
                                        </div>
                                        <Button type="submit" disabled={addForm.processing}>{t('Save')}</Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
                </div>

                <DataTable columns={columns} data={instrumentSoorten} />

                {/* Edit dialog */}
                <Dialog open={editItem !== null} onOpenChange={(open) => { if (!open) setEditItem(null); }}>
                    <DialogContent>
                        <DialogHeader><DialogTitle>{t('Edit instrument type')}</DialogTitle></DialogHeader>
                        <form onSubmit={handleEdit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-naam">{t('Name')}</Label>
                                <Input id="edit-naam" value={editForm.data.naam} onChange={(e) => editForm.setData('naam', e.target.value)} required />
                                <InputError message={editForm.errors.naam} />
                            </div>
                            <div className="space-y-2">
                                <Label>{t('Family')}</Label>
                                <Select value={editForm.data.instrument_familie_id} onValueChange={(v) => editForm.setData('instrument_familie_id', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('Select...')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {families.map((f) => (
                                            <SelectItem key={f.id} value={String(f.id)}>{f.naam}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={editForm.errors.instrument_familie_id} />
                            </div>
                            <Button type="submit" disabled={editForm.processing}>{t('Save')}</Button>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Delete dialog with type-to-confirm */}
                <Dialog open={deleteItem !== null} onOpenChange={(open) => { if (!open) { setDeleteItem(null); setDeleteConfirmation(''); } }}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{t('Delete instrument type')}</DialogTitle>
                            <DialogDescription>
                                {t('This instrument type may be linked to the music library. Removing it here does not remove it from the music library.')}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-4">
                            <p className="text-sm">
                                {t("Type ':name' to confirm.", { name: deleteItem?.naam ?? '' })}
                            </p>
                            <Input
                                value={deleteConfirmation}
                                onChange={(e) => setDeleteConfirmation(e.target.value)}
                                placeholder={deleteItem?.naam ?? ''}
                            />
                        </div>
                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">{t('Cancel')}</Button>
                            </DialogClose>
                            <Button
                                variant="destructive"
                                disabled={deleteConfirmation !== deleteItem?.naam || deleteForm.processing}
                                onClick={handleDelete}
                            >
                                {t('Delete')}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
