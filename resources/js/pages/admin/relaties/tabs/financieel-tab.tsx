import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import type { Relatie } from '@/types/admin';

type Props = {
    relatie: Relatie;
};

export default function RelatieFinancieelTab({ relatie }: Props) {
    const { can } = usePermissions();
    const { t } = useTranslation();

    if (!can('financieel.view')) {
        return (
            <Card>
                <CardContent className="py-8 text-center">
                    <p className="text-muted-foreground">{t('You do not have access to financial data.')}</p>
                </CardContent>
            </Card>
        );
    }

    const formatCurrency = (amount: string | number) => {
        return new Intl.NumberFormat('nl-NL', { style: 'currency', currency: 'EUR' }).format(Number(amount));
    };

    const formatDate = (date: string) => {
        return new Date(date).toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' });
    };

    const statusVariant = (status: string) => {
        switch (status) {
            case 'betaald': return 'default' as const;
            case 'open': return 'destructive' as const;
            case 'kwijtgescholden': return 'outline' as const;
            default: return 'secondary' as const;
        }
    };

    return (
        <Card>
            <CardHeader><CardTitle>{t('Outstanding contributions')}</CardTitle></CardHeader>
            <CardContent>
                {relatie.te_betaken_contributies && relatie.te_betaken_contributies.length > 0 ? (
                    <div className="space-y-3">
                        {relatie.te_betaken_contributies.map((tc) => (
                            <div key={tc.id} className="rounded-md border p-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">
                                            {tc.contributie?.soort_contributie?.naam ?? t('Contribution')} — {tc.jaar}
                                        </p>
                                        {tc.contributie?.tariefgroep && (
                                            <p className="text-muted-foreground text-sm">{t('Rate group')}: {tc.contributie.tariefgroep.naam}</p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-semibold">{formatCurrency(tc.bedrag)}</span>
                                        <Badge variant={statusVariant(tc.status)}>{tc.status}</Badge>
                                    </div>
                                </div>

                                {tc.betalingen && tc.betalingen.length > 0 && (
                                    <div className="mt-2 border-t pt-2">
                                        <p className="text-muted-foreground mb-1 text-xs font-medium">{t('Payments:')}</p>
                                        {tc.betalingen.map((betaling) => (
                                            <div key={betaling.id} className="text-muted-foreground flex justify-between text-sm">
                                                <span>{formatDate(betaling.datum)} {betaling.methode && `(${betaling.methode})`}</span>
                                                <span>{formatCurrency(betaling.bedrag)}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">{t('No outstanding contributions.')}</p>
                )}
            </CardContent>
        </Card>
    );
}
