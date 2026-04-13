import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import type { LidmaatschapEntry, Onderdeel, OnderdeelEntry, RelatieCreateFormData } from '@/types/admin';

type Props = {
    data: RelatieCreateFormData;
    setData: <K extends keyof RelatieCreateFormData>(key: K, value: RelatieCreateFormData[K]) => void;
    errors: Partial<Record<string, string>>;
    onderdelen: Onderdeel[];
};

const today = () => new Date().toISOString().split('T')[0];

function emptyLidmaatschap(): LidmaatschapEntry {
    return { lid_sinds: today(), lid_tot: '', reden_vertrek: '' };
}

function emptyOnderdeel(): OnderdeelEntry {
    return { onderdeel_id: '', functie: '', van: today(), tot: '' };
}

export default function Step3Membership({ data, setData, errors, onderdelen }: Props) {
    const { t } = useTranslation();

    const updateLidmaatschap = (index: number, field: keyof LidmaatschapEntry, value: string) => {
        const updated = [...data.lidmaatschappen];
        updated[index] = { ...updated[index], [field]: value };
        setData('lidmaatschappen', updated);
    };

    const updateOnderdeel = (index: number, field: keyof OnderdeelEntry, value: string) => {
        const updated = [...data.onderdelen];
        updated[index] = { ...updated[index], [field]: value };
        setData('onderdelen', updated);
    };

    return (
        <div className="space-y-6">
            {/* Membership periods */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('Membership periods')}</CardTitle>
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('lidmaatschappen', [...data.lidmaatschappen, emptyLidmaatschap()])}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add membership')}
                    </Button>
                </CardHeader>
                <CardContent>
                    {data.lidmaatschappen.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No data entered')}</p>
                    ) : (
                        <div className="space-y-4">
                            {data.lidmaatschappen.map((lid, index) => (
                                <div key={index} className="rounded-md border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-sm font-medium">{t('Membership')} {index + 1}</span>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('lidmaatschappen', data.lidmaatschappen.filter((_, i) => i !== index))}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label required>{t('Member since')}</Label>
                                            <Input type="date" value={lid.lid_sinds} onChange={(e) => updateLidmaatschap(index, 'lid_sinds', e.target.value)} />
                                            <InputError message={errors[`lidmaatschappen.${index}.lid_sinds`]} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Member until')}</Label>
                                            <Input type="date" value={lid.lid_tot} onChange={(e) => updateLidmaatschap(index, 'lid_tot', e.target.value)} />
                                        </div>
                                        <div className="col-span-full space-y-2">
                                            <Label>{t('Reason for departure')}</Label>
                                            <Textarea value={lid.reden_vertrek} onChange={(e) => updateLidmaatschap(index, 'reden_vertrek', e.target.value)} rows={3} />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Onderdelen */}
            <Card>
                <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>{t('Sections')}</CardTitle>
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('onderdelen', [...data.onderdelen, emptyOnderdeel()])}>
                        <Plus className="mr-2 h-4 w-4" />
                        {t('Add section')}
                    </Button>
                </CardHeader>
                <CardContent>
                    {data.onderdelen.length === 0 ? (
                        <p className="text-muted-foreground text-sm">{t('No data entered')}</p>
                    ) : (
                        <div className="space-y-4">
                            {data.onderdelen.map((entry, index) => (
                                <div key={index} className="rounded-md border p-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <span className="text-sm font-medium">{t('Section')} {index + 1}</span>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setData('onderdelen', data.onderdelen.filter((_, i) => i !== index))}>
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label required>{t('Section')}</Label>
                                            <Select value={entry.onderdeel_id} onValueChange={(v) => updateOnderdeel(index, 'onderdeel_id', v)}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('Select section')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {onderdelen.map((o) => (
                                                        <SelectItem key={o.id} value={o.id.toString()}>{o.naam}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors[`onderdelen.${index}.onderdeel_id`]} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Function')}</Label>
                                            <Input value={entry.functie} onChange={(e) => updateOnderdeel(index, 'functie', e.target.value)} placeholder={t('e.g. Conductor, Chairman')} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label required>{t('From')}</Label>
                                            <Input type="date" value={entry.van} onChange={(e) => updateOnderdeel(index, 'van', e.target.value)} />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>{t('Until')}</Label>
                                            <Input type="date" value={entry.tot} onChange={(e) => updateOnderdeel(index, 'tot', e.target.value)} />
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
