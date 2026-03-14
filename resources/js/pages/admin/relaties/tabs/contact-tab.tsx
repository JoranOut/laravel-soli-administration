import { router, useForm } from '@inertiajs/react';
import { Mail, MapPin, Phone, Landmark, Plus, Pencil, AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Relatie, Adres, EmailRecord, Telefoon, GiroGegeven } from '@/types/admin';

type Props = {
    relatie: Relatie;
};

function AddAdresDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        straat: '',
        huisnummer: '',
        huisnummer_toevoeging: '',
        postcode: '',
        plaats: '',
        land: 'Nederland',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/adressen`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline"><Plus className="mr-2 h-4 w-4" />{t('Address')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add address')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-3 gap-2">
                        <div className="col-span-2 space-y-2">
                            <Label>{t('Street')}</Label>
                            <Input value={data.straat} onChange={(e) => setData('straat', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('No.')}</Label>
                            <Input value={data.huisnummer} onChange={(e) => setData('huisnummer', e.target.value)} required />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-2">
                            <Label>{t('Postal code')}</Label>
                            <Input value={data.postcode} onChange={(e) => setData('postcode', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('City')}</Label>
                            <Input value={data.plaats} onChange={(e) => setData('plaats', e.target.value)} required />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditAdresDialog({ relatieId, adres }: { relatieId: number; adres: Adres }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        straat: adres.straat,
        huisnummer: adres.huisnummer,
        huisnummer_toevoeging: adres.huisnummer_toevoeging ?? '',
        postcode: adres.postcode,
        plaats: adres.plaats,
        land: adres.land ?? 'Nederland',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/adressen/${adres.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/adressen/${adres.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit address')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="grid grid-cols-3 gap-2">
                        <div className="col-span-2 space-y-2">
                            <Label>{t('Street')}</Label>
                            <Input value={data.straat} onChange={(e) => setData('straat', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('No.')}</Label>
                            <Input value={data.huisnummer} onChange={(e) => setData('huisnummer', e.target.value)} required />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-2">
                            <Label>{t('Postal code')}</Label>
                            <Input value={data.postcode} onChange={(e) => setData('postcode', e.target.value)} required />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('City')}</Label>
                            <Input value={data.plaats} onChange={(e) => setData('plaats', e.target.value)} required />
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
                        <Button type="submit" disabled={processing}>{t('Save')}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function AddEmailDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        email: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/emails`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline"><Plus className="mr-2 h-4 w-4" />{t('Email')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add email')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Email')}</Label>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditEmailDialog({ relatieId, email, isLoginEmail }: { relatieId: number; email: EmailRecord; isLoginEmail: boolean }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        email: email.email,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/emails/${email.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/emails/${email.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit email')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {isLoginEmail && (
                        <Alert className="border-amber-500/50 bg-amber-50 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                {t('This email is used as the login email. Changing it will also update the login email for the linked user account.')}
                            </AlertDescription>
                        </Alert>
                    )}
                    <div className="space-y-2">
                        <Label>{t('Email')}</Label>
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                    </div>
                    <div className="flex items-center justify-between border-t pt-4">
                        <div>
                            {isLoginEmail ? (
                                <span className="text-muted-foreground text-sm">{t('Cannot delete login email')}</span>
                            ) : !confirmDelete ? (
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

function AddTelefoonDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        nummer: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/telefoons`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline"><Plus className="mr-2 h-4 w-4" />{t('Phone')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add phone')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Number')}</Label>
                        <Input value={data.nummer} onChange={(e) => setData('nummer', e.target.value)} required />
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditTelefoonDialog({ relatieId, telefoon }: { relatieId: number; telefoon: Telefoon }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        nummer: telefoon.nummer,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/telefoons/${telefoon.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/telefoons/${telefoon.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit phone')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>{t('Number')}</Label>
                        <Input value={data.nummer} onChange={(e) => setData('nummer', e.target.value)} required />
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

function AddGiroGegevenDialog({ relatieId }: { relatieId: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        iban: '',
        bic: '',
        tenaamstelling: '',
        machtiging: false as boolean,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/relaties/${relatieId}/giro-gegevens`, {
            onSuccess: () => { setOpen(false); reset(); },
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline"><Plus className="mr-2 h-4 w-4" />{t('Bank details')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Add bank details')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>IBAN</Label>
                        <Input value={data.iban} onChange={(e) => setData('iban', e.target.value)} required />
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-2">
                            <Label>BIC</Label>
                            <Input value={data.bic} onChange={(e) => setData('bic', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Account holder')}</Label>
                            <Input value={data.tenaamstelling} onChange={(e) => setData('tenaamstelling', e.target.value)} />
                        </div>
                    </div>
                    <Button type="submit" disabled={processing}>{t('Save')}</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function EditGiroGegevenDialog({ relatieId, giro }: { relatieId: number; giro: GiroGegeven }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const { data, setData, put, processing } = useForm({
        iban: giro.iban,
        bic: giro.bic ?? '',
        tenaamstelling: giro.tenaamstelling,
        machtiging: giro.machtiging,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatieId}/giro-gegevens/${giro.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    const handleDelete = () => {
        router.delete(`/admin/relaties/${relatieId}/giro-gegevens/${giro.id}`, {
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={(v) => { setOpen(v); if (!v) setConfirmDelete(false); }}>
            <DialogTrigger asChild>
                <Button variant="ghost" size="sm"><Pencil className="h-4 w-4" /></Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader><DialogTitle>{t('Edit bank details')}</DialogTitle></DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label>IBAN</Label>
                        <Input value={data.iban} onChange={(e) => setData('iban', e.target.value)} required />
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <div className="space-y-2">
                            <Label>BIC</Label>
                            <Input value={data.bic} onChange={(e) => setData('bic', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Account holder')}</Label>
                            <Input value={data.tenaamstelling} onChange={(e) => setData('tenaamstelling', e.target.value)} />
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Checkbox checked={data.machtiging} onCheckedChange={(v) => setData('machtiging', v === true)} />
                        <Label>{t('Mandate')}</Label>
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

export default function RelatieContactTab({ relatie }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    return (
        <div className="space-y-6">
            {can('relaties.edit') && (
                <div className="flex flex-wrap gap-2">
                    <AddAdresDialog relatieId={relatie.id} />
                    <AddEmailDialog relatieId={relatie.id} />
                    <AddTelefoonDialog relatieId={relatie.id} />
                    <AddGiroGegevenDialog relatieId={relatie.id} />
                </div>
            )}

            {/* Addresses */}
            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2"><MapPin className="h-4 w-4" />{t('Addresses')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.adressen && relatie.adressen.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.adressen.map((adres) => (
                                <div key={adres.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="font-medium">{adres.volledig_adres}</p>
                                    </div>
                                    {can('relaties.edit') && (
                                        <EditAdresDialog relatieId={relatie.id} adres={adres} />
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No addresses.')}</p>
                    )}
                </CardContent>
            </Card>

            {/* Emails */}
            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2"><Mail className="h-4 w-4" />{t('Emails')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.emails && relatie.emails.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.emails.map((email) => {
                                const isLoginEmail = relatie.user?.email === email.email;
                                return (
                                    <div key={email.id} className="flex items-center justify-between rounded-md border p-3">
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium">{email.email}</p>
                                            {isLoginEmail && (
                                                <Badge variant="secondary" className="text-xs">{t('Login')}</Badge>
                                            )}
                                        </div>
                                        {can('relaties.edit') && (
                                            <EditEmailDialog relatieId={relatie.id} email={email} isLoginEmail={isLoginEmail} />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No emails.')}</p>
                    )}
                </CardContent>
            </Card>

            {/* Phones */}
            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2"><Phone className="h-4 w-4" />{t('Phones')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.telefoons && relatie.telefoons.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.telefoons.map((tel) => (
                                <div key={tel.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="font-medium">{tel.nummer}</p>
                                    </div>
                                    {can('relaties.edit') && (
                                        <EditTelefoonDialog relatieId={relatie.id} telefoon={tel} />
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No phones.')}</p>
                    )}
                </CardContent>
            </Card>

            {/* Bank details */}
            <Card>
                <CardHeader><CardTitle className="flex items-center gap-2"><Landmark className="h-4 w-4" />{t('Bank details')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.giro_gegevens && relatie.giro_gegevens.length > 0 ? (
                        <div className="space-y-3">
                            {relatie.giro_gegevens.map((giro) => (
                                <div key={giro.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="font-medium">{giro.iban}</p>
                                        <p className="text-muted-foreground text-sm">{giro.tenaamstelling}</p>
                                    </div>
                                    {can('relaties.edit') && (
                                        <EditGiroGegevenDialog relatieId={relatie.id} giro={giro} />
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No bank details.')}</p>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
