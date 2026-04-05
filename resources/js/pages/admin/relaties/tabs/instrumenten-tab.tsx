import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangeDisplay } from '@/components/admin/date-range-display';
import { useTranslation } from '@/hooks/use-translation';
import type { Relatie } from '@/types/admin';

type Props = {
    relatie: Relatie;
};

export default function RelatieInstrumentenTab({ relatie }: Props) {
    const { t } = useTranslation();

    const statusVariant = (status: string) => {
        switch (status) {
            case 'beschikbaar': return 'default' as const;
            case 'in_gebruik': return 'secondary' as const;
            case 'in_reparatie': return 'destructive' as const;
            case 'afgeschreven': return 'outline' as const;
            default: return 'secondary' as const;
        }
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader><CardTitle>{t('Soli instruments')}</CardTitle></CardHeader>
                <CardContent>
                    {relatie.instrument_bespelers && relatie.instrument_bespelers.filter(b => !b.tot).length > 0 ? (
                        <div className="space-y-3">
                            {relatie.instrument_bespelers.filter(b => !b.tot).map((bespeler) => (
                                <div key={bespeler.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="font-medium">
                                            {bespeler.instrument ? (
                                                <Link href={`/admin/instrumenten/${bespeler.instrument.id}`} className="text-primary hover:underline">
                                                    {bespeler.instrument.nummer} — {bespeler.instrument.soort}
                                                </Link>
                                            ) : (
                                                t('Unknown instrument')
                                            )}
                                        </p>
                                        <div className="flex items-center gap-2">
                                            {bespeler.instrument && (
                                                <>
                                                    {bespeler.instrument.merk && <span className="text-muted-foreground text-sm">{bespeler.instrument.merk} {bespeler.instrument.model}</span>}
                                                    <Badge variant={statusVariant(bespeler.instrument.status)}>{bespeler.instrument.status}</Badge>
                                                </>
                                            )}
                                            <DateRangeDisplay van={bespeler.van} tot={bespeler.tot} />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-muted-foreground text-sm">{t('No Soli instruments.')}</p>
                    )}
                </CardContent>
            </Card>

            {relatie.instrument_bespelers && relatie.instrument_bespelers.filter(b => b.tot).length > 0 && (
                <Card>
                    <CardHeader><CardTitle>{t('Previous instruments')}</CardTitle></CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {relatie.instrument_bespelers.filter(b => b.tot).map((bespeler) => (
                                <div key={bespeler.id} className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <p className="font-medium">
                                            {bespeler.instrument?.nummer} — {bespeler.instrument?.soort}
                                        </p>
                                        <DateRangeDisplay van={bespeler.van} tot={bespeler.tot} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
