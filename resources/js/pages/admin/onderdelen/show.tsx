import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { ONDERDEEL_TYPES } from '@/constants/onderdeel';
import type { Onderdeel } from '@/types/admin';

type Props = {
    onderdeel: Onderdeel & { relaties?: Array<{ id: number; volledige_naam: string; pivot?: { functie: string | null; van: string; tot: string | null }; types?: Array<{ id: number; naam: string }> }> };
    instrumentsByRelatie: Record<number, string[]>;
};

export default function OnderdeelShow({ onderdeel, instrumentsByRelatie }: Props) {
    const { t } = useTranslation();
    const { can } = usePermissions();
    const [editOpen, setEditOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        naam: onderdeel.naam,
        afkorting: onderdeel.afkorting ?? '',
        type: onderdeel.type,
        beschrijving: onderdeel.beschrijving ?? '',
        actief: onderdeel.actief,
    });

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/onderdelen/${onderdeel.id}`, {
            onSuccess: () => setEditOpen(false),
        });
    };

    const handleDelete = () => {
        setDeleting(true);
        router.delete(`/admin/onderdelen/${onderdeel.id}`, {
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <AppLayout>
            <Head title={onderdeel.naam} />
            <div className="space-y-6 p-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/admin/onderdelen">
                            <ArrowLeft className="mr-2 h-4 w-4" />{t("Back")}
                        </Link>
                    </Button>

                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-2xl font-bold">
                                {onderdeel.naam}
                                {onderdeel.afkorting && <span className="text-muted-foreground ml-2 text-lg">({onderdeel.afkorting})</span>}
                            </h2>
                            {onderdeel.beschrijving && <p className="text-muted-foreground">{onderdeel.beschrijving}</p>}
                        </div>
                        <div className="flex items-center gap-2">
                            <Badge variant="outline">{t(onderdeel.type)}</Badge>
                            <Badge variant={onderdeel.actief ? 'default' : 'outline'}>{onderdeel.actief ? t('Active') : t('Inactive')}</Badge>
                            {can('onderdelen.edit') && (
                                <Button variant="outline" size="sm" onClick={() => setEditOpen(true)}>
                                    <Pencil className="mr-2 h-4 w-4" />{t("Edit")}
                                </Button>
                            )}
                            {can('onderdelen.delete') && (
                                <Button variant="destructive" size="sm" onClick={() => setDeleteOpen(true)}>
                                    <Trash className="mr-2 h-4 w-4" />{t("Delete")}
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Edit dialog */}
                    <Dialog open={editOpen} onOpenChange={setEditOpen}>
                        <DialogContent>
                            <DialogHeader><DialogTitle>{t("Edit section")}</DialogTitle></DialogHeader>
                            <form onSubmit={handleEditSubmit} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="edit-naam">{t("Name")}</Label>
                                    <Input id="edit-naam" value={data.naam} onChange={(e) => setData('naam', e.target.value)} required />
                                    <InputError message={errors.naam} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit-afkorting">{t("Abbreviation")}</Label>
                                    <Input id="edit-afkorting" value={data.afkorting} onChange={(e) => setData('afkorting', e.target.value)} maxLength={10} />
                                    <InputError message={errors.afkorting} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit-type">{t("Type")}</Label>
                                    <Select value={data.type} onValueChange={(v) => setData('type', v as typeof data.type)}>
                                        <SelectTrigger id="edit-type"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            {ONDERDEEL_TYPES.map((tp) => (
                                                <SelectItem key={tp} value={tp}>{t(tp)}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="edit-beschrijving">{t("Description")}</Label>
                                    <Input id="edit-beschrijving" value={data.beschrijving} onChange={(e) => setData('beschrijving', e.target.value)} />
                                    <InputError message={errors.beschrijving} />
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="edit-actief"
                                        checked={data.actief}
                                        onCheckedChange={(checked) => setData('actief', !!checked)}
                                    />
                                    <label htmlFor="edit-actief" className="text-sm">{t("Active")}</label>
                                </div>
                                <Button type="submit" disabled={processing}>{t("Save")}</Button>
                            </form>
                        </DialogContent>
                    </Dialog>

                    {/* Delete confirmation dialog */}
                    <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>{t("Delete section")}</DialogTitle>
                                <DialogDescription>
                                    {t('Are you sure you want to delete :name?', { name: onderdeel.naam })}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">{t("Cancel")}</Button>
                                </DialogClose>
                                <Button variant="destructive" disabled={deleting} onClick={handleDelete}>
                                    {t("Delete")}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t("Members")} ({onderdeel.relaties?.length ?? 0})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {onderdeel.relaties && onderdeel.relaties.length > 0 ? (
                                <div className="space-y-2">
                                    {onderdeel.relaties.map((relatie) => {
                                        const instruments = instrumentsByRelatie[relatie.id] ?? [];
                                        return (
                                            <div key={relatie.id} className="flex items-center justify-between rounded-md border p-3">
                                                <div className="flex items-center gap-2">
                                                    <Link href={`/admin/relaties/${relatie.id}`} className="text-primary hover:underline font-medium">
                                                        {relatie.volledige_naam}
                                                    </Link>
                                                    {relatie.types?.map((type) => (
                                                        <Badge key={type.id} variant="outline">{type.naam}</Badge>
                                                    ))}
                                                    {relatie.pivot?.functie && (
                                                        <Badge variant="secondary">{relatie.pivot.functie}</Badge>
                                                    )}
                                                    {instruments.map((soort) => (
                                                        <Badge key={soort}>{soort}</Badge>
                                                    ))}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            ) : (
                                <p className="text-muted-foreground text-sm">{t("No members.")}</p>
                            )}
                        </CardContent>
                    </Card>
            </div>
        </AppLayout>
    );
}
