import { Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import type { AdresEntry, EmailEntry, GiroGegevenEntry, RelatieCreateFormData, TelefoonEntry } from '@/types/admin';

type Props = {
    data: RelatieCreateFormData;
    setData: <K extends keyof RelatieCreateFormData>(key: K, value: RelatieCreateFormData[K]) => void;
    errors: Partial<Record<string, string>>;
};

function emptyAdres(): AdresEntry {
    return { straat: '', huisnummer: '', huisnummer_toevoeging: '', postcode: '', plaats: '', land: 'Nederland' };
}

function emptyEmail(): EmailEntry {
    return { email: '' };
}

function emptyTelefoon(): TelefoonEntry {
    return { nummer: '' };
}

function emptyGiroGegeven(): GiroGegevenEntry {
    return { iban: '', bic: '', tenaamstelling: '', machtiging: false };
}

export default function Step2Contact({ data, setData, errors }: Props) {
    const { t } = useTranslation();

    // Generic updaters
    const updateAdres = (index: number, field: keyof AdresEntry, value: string) => {
        const updated = [...data.adressen];
        updated[index] = { ...updated[index], [field]: value };
        setData('adressen', updated);
    };

    const updateEmail = (index: number, field: keyof EmailEntry, value: string) => {
        const updated = [...data.emails];
        updated[index] = { ...updated[index], [field]: value };
        setData('emails', updated);
    };

    const updateTelefoon = (index: number, field: keyof TelefoonEntry, value: string) => {
        const updated = [...data.telefoons];
        updated[index] = { ...updated[index], [field]: value };
        setData('telefoons', updated);
    };

    const updateGiro = (index: number, field: keyof GiroGegevenEntry, value: string | boolean) => {
        const updated = [...data.giro_gegevens];
        updated[index] = { ...updated[index], [field]: value };
        setData('giro_gegevens', updated);
    };

    return (
        <div className="space-y-6">
            {/* Addresses */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('Addresses')}</CardTitle>
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('adressen', [...data.adressen, emptyAdres()])}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add address')}
                    </Button>
                </CardHeader>
                <CardContent>
                    {data.adressen.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No data entered')}</p>
                    ) : (
                        <div className="space-y-4">
                            {data.adressen.map((adres, index) => (
                                <div key={index} className="rounded-md border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-sm font-medium">{t('Address')} {index + 1}</span>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('adressen', data.adressen.filter((_, i) => i !== index))}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="grid grid-cols-3 gap-2 sm:col-span-2">
                                            <div className="col-span-2 space-y-2">
                                                <Label required>{t('Street')}</Label>
                                                <Input value={adres.straat} onChange={(e) => updateAdres(index, 'straat', e.target.value)} />
                                                <InputError message={errors[`adressen.${index}.straat`]} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label required>{t('No.')}</Label>
                                                <Input value={adres.huisnummer} onChange={(e) => updateAdres(index, 'huisnummer', e.target.value)} />
                                                <InputError message={errors[`adressen.${index}.huisnummer`]} />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label required>{t('Postal code')}</Label>
                                            <Input value={adres.postcode} onChange={(e) => updateAdres(index, 'postcode', e.target.value)} />
                                            <InputError message={errors[`adressen.${index}.postcode`]} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label required>{t('City')}</Label>
                                            <Input value={adres.plaats} onChange={(e) => updateAdres(index, 'plaats', e.target.value)} />
                                            <InputError message={errors[`adressen.${index}.plaats`]} />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Emails */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('Emails')}</CardTitle>
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('emails', [...data.emails, emptyEmail()])}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add email')}
                    </Button>
                </CardHeader>
                <CardContent>
                    <InputError message={errors['emails']} />
                    {data.emails.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No data entered')}</p>
                    ) : (
                        <div className="space-y-4">
                            {data.emails.map((email, index) => (
                                <div key={index} className="rounded-md border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-medium">{t('Email')} {index + 1}</span>
                                            {index === 0 && <Badge variant="secondary">{t('Login email')}</Badge>}
                                        </div>
                                        {data.emails.length > 1 && (
                                            <Button type="button" variant="ghost" size="sm" onClick={() => setData('emails', data.emails.filter((_, i) => i !== index))}>
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label required>{t('Email')}</Label>
                                        <Input type="email" value={email.email} onChange={(e) => updateEmail(index, 'email', e.target.value)} />
                                        <InputError message={errors[`emails.${index}.email`]} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Phones */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('Phones')}</CardTitle>
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('telefoons', [...data.telefoons, emptyTelefoon()])}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add phone')}
                    </Button>
                </CardHeader>
                <CardContent>
                    {data.telefoons.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No data entered')}</p>
                    ) : (
                        <div className="space-y-4">
                            {data.telefoons.map((tel, index) => (
                                <div key={index} className="rounded-md border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-sm font-medium">{t('Phone')} {index + 1}</span>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('telefoons', data.telefoons.filter((_, i) => i !== index))}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="space-y-2">
                                        <Label required>{t('Number')}</Label>
                                        <Input value={tel.nummer} onChange={(e) => updateTelefoon(index, 'nummer', e.target.value)} />
                                        <InputError message={errors[`telefoons.${index}.nummer`]} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Bank details */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('Bank details')}</CardTitle>
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('giro_gegevens', [...data.giro_gegevens, emptyGiroGegeven()])}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add bank details')}
                    </Button>
                </CardHeader>
                <CardContent>
                    {data.giro_gegevens.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No data entered')}</p>
                    ) : (
                        <div className="space-y-4">
                            {data.giro_gegevens.map((giro, index) => (
                                <div key={index} className="rounded-md border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-sm font-medium">{t('Bank details')} {index + 1}</span>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('giro_gegevens', data.giro_gegevens.filter((_, i) => i !== index))}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label required>{t('IBAN')}</Label>
                                            <Input value={giro.iban} onChange={(e) => updateGiro(index, 'iban', e.target.value)} />
                                            <InputError message={errors[`giro_gegevens.${index}.iban`]} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('BIC')}</Label>
                                            <Input value={giro.bic} onChange={(e) => updateGiro(index, 'bic', e.target.value)} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label required>{t('Account holder')}</Label>
                                            <Input value={giro.tenaamstelling} onChange={(e) => updateGiro(index, 'tenaamstelling', e.target.value)} />
                                            <InputError message={errors[`giro_gegevens.${index}.tenaamstelling`]} />
                                        </div>
                                        <div className="flex items-end space-x-2 pb-1">
                                            <Checkbox
                                                id={`machtiging-${index}`}
                                                checked={giro.machtiging}
                                                onCheckedChange={(checked) => updateGiro(index, 'machtiging', checked === true)}
                                            />
                                            <label htmlFor={`machtiging-${index}`} className="text-sm">{t('Mandate')}<span className="text-destructive ml-0.5">*</span></label>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
