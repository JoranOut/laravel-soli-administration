import { Head, Link } from '@inertiajs/react';
import { Music, Users, Wrench, Heart, AlertTriangle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, DashboardStats, DashboardAlerts } from '@/types';

type Props = {
    stats: DashboardStats;
    alerts?: DashboardAlerts;
};

export default function Dashboard({ stats, alerts }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Dashboard'),
            href: dashboard(),
        },
    ];

    const statCards = [
        {
            title: t('Active members'),
            value: stats.actieve_leden,
            icon: Users,
            description: t('Current active members'),
        },
        {
            title: t('Donors'),
            value: stats.donateurs,
            icon: Heart,
            description: t('Active donors'),
        },
        {
            title: t('Instruments in use'),
            value: stats.instrumenten_in_gebruik,
            icon: Music,
            description: t('Loaned instruments'),
        },
        {
            title: t('In repair'),
            value: stats.openstaande_reparaties,
            icon: Wrench,
            description: t('Instruments in repair'),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Dashboard')} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold">{t('Dashboard')}</h1>
                    <p className="text-muted-foreground">{t('Overview of Muziekvereniging Soli')}</p>
                </div>

                {alerts && (alerts.unlinked_users > 0 || alerts.unlinked_relaties > 0) && (
                    <Alert className="border-amber-500/50 bg-amber-50 text-amber-900 dark:bg-amber-950/50 dark:text-amber-200">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>{t('Linking issues detected')}</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {alerts.unlinked_users > 0 && (
                                    <li>{t(':count users without a linked relation').replace(':count', String(alerts.unlinked_users))}</li>
                                )}
                                {alerts.unlinked_relaties > 0 && (
                                    <li>{t(':count active relations without a linked user').replace(':count', String(alerts.unlinked_relaties))}</li>
                                )}
                            </ul>
                            <Link href="/admin/koppelingen" className="mt-2 inline-block font-medium underline underline-offset-4">
                                {t('Fix linking issues')}
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((stat) => (
                        <Card key={stat.title}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{stat.title}</CardTitle>
                                <stat.icon className="text-muted-foreground h-4 w-4" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stat.value}</div>
                                <p className="text-muted-foreground text-xs">{stat.description}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
