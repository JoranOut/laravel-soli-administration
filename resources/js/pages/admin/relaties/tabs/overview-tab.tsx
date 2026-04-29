import { useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/input-error';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Relatie } from '@/types/admin';

type Props = {
    relatie: Relatie;
};

export default function RelatieOverviewTab({ relatie }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();
    const { data, setData, put, processing, errors, isDirty } = useForm({
        voornaam: relatie.voornaam,
        tussenvoegsel: relatie.tussenvoegsel ?? '',
        achternaam: relatie.achternaam,
        geslacht: relatie.geslacht,
        geboortedatum: relatie.geboortedatum ?? '',
        actief: relatie.actief,
        beheerd_in_admin: relatie.beheerd_in_admin,
        geboorteplaats: relatie.geboorteplaats ?? '',
        nationaliteit: relatie.nationaliteit ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/admin/relaties/${relatie.id}`);
    };

    const formatDate = (date: string | null) => {
        if (!date) return '—';
        return new Date(date).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    };

    const today = new Date().toISOString().split('T')[0];
    const hasActiveLidmaatschap = (relatie.relatie_sinds ?? []).some(
        (rs) => !rs.lid_tot || rs.lid_tot >= today,
    );
    const hasActiveOnderdelen = (relatie.onderdelen ?? []).some(
        (o) => o.pivot && (!o.pivot.tot || o.pivot.tot >= today),
    );
    const cannotDeactivate = relatie.actief && (hasActiveLidmaatschap || hasActiveOnderdelen);

    if (!can('relaties.edit')) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{t('Personal information')}</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt className="text-muted-foreground text-sm">{t('First name')}</dt><dd>{relatie.voornaam}</dd></div>
                        <div><dt className="text-muted-foreground text-sm">{t('Prefix')}</dt><dd>{relatie.tussenvoegsel ?? '—'}</dd></div>
                        <div><dt className="text-muted-foreground text-sm">{t('Last name')}</dt><dd>{relatie.achternaam}</dd></div>
                        <div><dt className="text-muted-foreground text-sm">{t('Gender')}</dt><dd>{relatie.geslacht === 'M' ? t('Male') : relatie.geslacht === 'V' ? t('Female') : t('Other')}</dd></div>
                        <div><dt className="text-muted-foreground text-sm">{t('Date of birth')}</dt><dd>{formatDate(relatie.geboortedatum)}</dd></div>
                        <div><dt className="text-muted-foreground text-sm">{t('Place of birth')}</dt><dd>{relatie.geboorteplaats ?? '—'}</dd></div>
                        <div><dt className="text-muted-foreground text-sm">{t('Nationality')}</dt><dd>{relatie.nationaliteit ?? '—'}</dd></div>
                        <div><dt className="text-muted-foreground text-sm">{t('Status')}</dt><dd><Badge variant={relatie.actief ? 'default' : 'outline'}>{relatie.actief ? t('Active') : t('Inactive')}</Badge></dd></div>
                    </dl>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Personal information')}</CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="voornaam">{t('First name')}</Label>
                            <Input id="voornaam" value={data.voornaam} onChange={(e) => setData('voornaam', e.target.value)} />
                            <InputError message={errors.voornaam} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tussenvoegsel">{t('Prefix')}</Label>
                            <Input id="tussenvoegsel" value={data.tussenvoegsel} onChange={(e) => setData('tussenvoegsel', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="achternaam">{t('Last name')}</Label>
                            <Input id="achternaam" value={data.achternaam} onChange={(e) => setData('achternaam', e.target.value)} />
                            <InputError message={errors.achternaam} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="geslacht">{t('Gender')}</Label>
                            <Select value={data.geslacht} onValueChange={(v) => setData('geslacht', v as 'M' | 'V' | 'O')}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="M">{t('Male')}</SelectItem>
                                    <SelectItem value="V">{t('Female')}</SelectItem>
                                    <SelectItem value="O">{t('Other')}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="geboortedatum">{t('Date of birth')}</Label>
                            <Input id="geboortedatum" type="date" value={data.geboortedatum} onChange={(e) => setData('geboortedatum', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="geboorteplaats">{t('Place of birth')}</Label>
                            <Input id="geboorteplaats" value={data.geboorteplaats} onChange={(e) => setData('geboorteplaats', e.target.value)} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="nationaliteit">{t('Nationality')}</Label>
                            <Input id="nationaliteit" value={data.nationaliteit} onChange={(e) => setData('nationaliteit', e.target.value)} />
                        </div>
                        <div className="space-y-3 pt-6">
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Checkbox id="actief" checked={data.actief} onCheckedChange={(checked) => setData('actief', checked === true)} disabled={cannotDeactivate} />
                                    <Label htmlFor="actief">{t('Active')}</Label>
                                </div>
                                {cannotDeactivate && (
                                    <p className="text-muted-foreground text-xs">
                                        {t('Cannot deactivate: this relation still has an active membership or is part of a group.')}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Checkbox id="beheerd_in_admin" checked={data.beheerd_in_admin} onCheckedChange={(checked) => setData('beheerd_in_admin', checked === true)} />
                                    <Label htmlFor="beheerd_in_admin">{t('Managed in admin')}</Label>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    {t('When enabled, this relation will not be overwritten by the SAD import.')}
                                </p>
                            </div>
                        </div>
                    </div>

                    <Button type="submit" disabled={processing || !isDirty}>
                        {t('Save')}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
