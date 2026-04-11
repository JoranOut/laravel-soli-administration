import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import type { Onderdeel, RelatieCreateFormData, RelatieType, RelatieTypeEntry } from '@/types/admin';

type Props = {
    data: RelatieCreateFormData;
    setData: <K extends keyof RelatieCreateFormData>(key: K, value: RelatieCreateFormData[K]) => void;
    errors: Partial<Record<string, string>>;
    relatieTypes: RelatieType[];
    onderdelen: Onderdeel[];
};

const today = () => new Date().toISOString().split('T')[0];

function emptyTypeEntry(): RelatieTypeEntry {
    return { type_id: '', van: today(), tot: '', functie: '', email: '', onderdeel_id: '' };
}

export default function Step1Personal({ data, setData, errors, relatieTypes, onderdelen }: Props) {
    const { t } = useTranslation();

    const updateType = (index: number, field: keyof RelatieTypeEntry, value: string) => {
        const updated = [...data.types];
        updated[index] = { ...updated[index], [field]: value };
        setData('types', updated);
    };

    const addType = () => {
        setData('types', [...data.types, emptyTypeEntry()]);
    };

    const removeType = (index: number) => {
        setData('types', data.types.filter((_, i) => i !== index));
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>{t('Personal information')}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="relatie_nummer" required>{t('Relation number')}</Label>
                            <Input
                                id="relatie_nummer"
                                type="number"
                                value={data.relatie_nummer}
                                onChange={(e) => setData('relatie_nummer', parseInt(e.target.value))}
                            />
                            <InputError message={errors.relatie_nummer} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="geslacht" required>{t('Gender')}</Label>
                            <Select value={data.geslacht} onValueChange={(v) => setData('geslacht', v as 'M' | 'V' | 'O')}>
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="M">{t('Male')}</SelectItem>
                                    <SelectItem value="V">{t('Female')}</SelectItem>
                                    <SelectItem value="O">{t('Other')}</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.geslacht} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="voornaam" required>{t('First name')}</Label>
                            <Input
                                id="voornaam"
                                value={data.voornaam}
                                onChange={(e) => setData('voornaam', e.target.value)}
                                required
                            />
                            <InputError message={errors.voornaam} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="tussenvoegsel">{t('Prefix')}</Label>
                            <Input
                                id="tussenvoegsel"
                                value={data.tussenvoegsel}
                                onChange={(e) => setData('tussenvoegsel', e.target.value)}
                            />
                            <InputError message={errors.tussenvoegsel} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="achternaam" required>{t('Last name')}</Label>
                            <Input
                                id="achternaam"
                                value={data.achternaam}
                                onChange={(e) => setData('achternaam', e.target.value)}
                                required
                            />
                            <InputError message={errors.achternaam} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="geboortedatum">{t('Date of birth')}</Label>
                            <Input
                                id="geboortedatum"
                                type="date"
                                value={data.geboortedatum}
                                onChange={(e) => setData('geboortedatum', e.target.value)}
                            />
                            <InputError message={errors.geboortedatum} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="geboorteplaats">{t('Place of birth')}</Label>
                            <Input
                                id="geboorteplaats"
                                value={data.geboorteplaats}
                                onChange={(e) => setData('geboorteplaats', e.target.value)}
                            />
                            <InputError message={errors.geboorteplaats} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="nationaliteit">{t('Nationality')}</Label>
                            <Input
                                id="nationaliteit"
                                value={data.nationaliteit}
                                onChange={(e) => setData('nationaliteit', e.target.value)}
                            />
                            <InputError message={errors.nationaliteit} />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('Types')}</CardTitle>
                    <Button type="button" size="sm" variant="outline" onClick={addType}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add type')}
                    </Button>
                </CardHeader>
                <CardContent>
                    {data.types.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No types.')}</p>
                    ) : (
                        <div className="space-y-3">
                            {data.types.map((entry, index) => (
                                <div key={index} className="rounded-md border p-3">
                                    <div className="flex items-start gap-3">
                                        <div className="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div className="space-y-2">
                                                <Label required>{t('Type')}</Label>
                                                <Select value={entry.type_id} onValueChange={(v) => updateType(index, 'type_id', v)}>
                                                    <SelectTrigger>
                                                        <SelectValue placeholder={t('Select type')} />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {relatieTypes.map((type) => (
                                                            <SelectItem key={type.id} value={type.id.toString()}>
                                                                {type.naam}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors[`types.${index}.type_id`]} />
                                            </div>
                                            <div className="space-y-2">
                                                <Label required>{t('From')}</Label>
                                                <Input
                                                    type="date"
                                                    value={entry.van}
                                                    onChange={(e) => updateType(index, 'van', e.target.value)}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Until')}</Label>
                                                <Input
                                                    type="date"
                                                    value={entry.tot}
                                                    onChange={(e) => updateType(index, 'tot', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                        <Button type="button" variant="ghost" size="sm" className="mt-7" onClick={() => removeType(index)}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    {entry.type_id && relatieTypes.find((t) => t.id.toString() === entry.type_id)?.naam !== 'lid' && (
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 mt-3 border-t pt-3">
                                            <div className="space-y-2">
                                                <Label>{t('Function (optional)')}</Label>
                                                <Input
                                                    value={entry.functie}
                                                    onChange={(e) => updateType(index, 'functie', e.target.value)}
                                                    placeholder={t('e.g. Chairman, Treasurer')}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>{t('Email (optional)')}</Label>
                                                <Input
                                                    type="email"
                                                    value={entry.email}
                                                    onChange={(e) => updateType(index, 'email', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                    )}
                                    {entry.type_id && relatieTypes.find((t) => t.id.toString() === entry.type_id)?.onderdeel_koppelbaar && (
                                        <div className="mt-3 border-t pt-3">
                                            <div className="space-y-2 sm:w-1/2">
                                                <Label>{t('Section (optional)')}</Label>
                                                <Select value={entry.onderdeel_id} onValueChange={(v) => updateType(index, 'onderdeel_id', v === 'none' ? '' : v)}>
                                                    <SelectTrigger><SelectValue placeholder={t('Select section')} /></SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">-</SelectItem>
                                                        {onderdelen.map((o) => (
                                                            <SelectItem key={o.id} value={o.id.toString()}>{o.naam}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
