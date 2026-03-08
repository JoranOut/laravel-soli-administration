import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import type { OpleidingEntry, RelatieCreateFormData } from '@/types/admin';

type Props = {
    data: RelatieCreateFormData;
    setData: <K extends keyof RelatieCreateFormData>(key: K, value: RelatieCreateFormData[K]) => void;
    errors: Partial<Record<string, string>>;
};

function emptyOpleiding(): OpleidingEntry {
    return { naam: '', instituut: '', instrument: '', diploma: '', datum_start: '', datum_einde: '', opmerking: '' };
}

export default function Step4Education({ data, setData, errors }: Props) {
    const { t } = useTranslation();

    const updateOpleiding = (index: number, field: keyof OpleidingEntry, value: string) => {
        const updated = [...data.opleidingen];
        updated[index] = { ...updated[index], [field]: value };
        setData('opleidingen', updated);
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>{t('Training')}</CardTitle>
                <Button type="button" size="sm" variant="outline" onClick={() => setData('opleidingen', [...data.opleidingen, emptyOpleiding()])}>
                    <Plus className="mr-2 h-4 w-4" />
                    {t('Add training')}
                </Button>
            </CardHeader>
            <CardContent>
                {data.opleidingen.length === 0 ? (
                    <p className="text-muted-foreground text-sm">{t('No data entered')}</p>
                ) : (
                    <div className="space-y-4">
                        {data.opleidingen.map((opl, index) => (
                            <div key={index} className="rounded-md border p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="text-sm font-medium">{t('Training')} {index + 1}</span>
                                    <Button type="button" variant="ghost" size="sm" onClick={() => setData('opleidingen', data.opleidingen.filter((_, i) => i !== index))}>
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label required>{t('Name')}</Label>
                                        <Input value={opl.naam} onChange={(e) => updateOpleiding(index, 'naam', e.target.value)} />
                                        <InputError message={errors[`opleidingen.${index}.naam`]} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Institute')}</Label>
                                        <Input value={opl.instituut} onChange={(e) => updateOpleiding(index, 'instituut', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Instrument')}</Label>
                                        <Input value={opl.instrument} onChange={(e) => updateOpleiding(index, 'instrument', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Diploma')}</Label>
                                        <Input value={opl.diploma} onChange={(e) => updateOpleiding(index, 'diploma', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('Start date')}</Label>
                                        <Input type="date" value={opl.datum_start} onChange={(e) => updateOpleiding(index, 'datum_start', e.target.value)} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>{t('End date')}</Label>
                                        <Input type="date" value={opl.datum_einde} onChange={(e) => updateOpleiding(index, 'datum_einde', e.target.value)} />
                                    </div>
                                    <div className="col-span-full space-y-2">
                                        <Label>{t('Remark')}</Label>
                                        <Input value={opl.opmerking} onChange={(e) => updateOpleiding(index, 'opmerking', e.target.value)} />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
