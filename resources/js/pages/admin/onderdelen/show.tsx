import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { Onderdeel } from '@/types/admin';

type Props = {
    onderdeel: Onderdeel & { relaties?: Array<{ id: number; volledige_naam: string; pivot?: { functie: string | null; van: string; tot: string | null } }> };
};

export default function OnderdeelShow({ onderdeel }: Props) {
    const { t } = useTranslation();

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
                            <h2 className="text-2xl font-bold">{onderdeel.naam}</h2>
                            {onderdeel.beschrijving && <p className="text-muted-foreground">{onderdeel.beschrijving}</p>}
                        </div>
                        <div className="flex gap-2">
                            <Badge variant="outline">{onderdeel.type}</Badge>
                            <Badge variant={onderdeel.actief ? 'default' : 'outline'}>{onderdeel.actief ? t('Active') : t('Inactive')}</Badge>
                        </div>
                    </div>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t("Members")} ({onderdeel.relaties?.length ?? 0})</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {onderdeel.relaties && onderdeel.relaties.length > 0 ? (
                                <div className="space-y-2">
                                    {onderdeel.relaties.map((relatie) => (
                                        <div key={relatie.id} className="flex items-center justify-between rounded-md border p-3">
                                            <div>
                                                <Link href={`/admin/relaties/${relatie.id}`} className="text-primary hover:underline font-medium">
                                                    {relatie.volledige_naam}
                                                </Link>
                                                {relatie.pivot?.functie && (
                                                    <Badge variant="secondary" className="ml-2">{relatie.pivot.functie}</Badge>
                                                )}
                                            </div>
                                        </div>
                                    ))}
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
