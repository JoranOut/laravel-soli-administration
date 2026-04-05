import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Music, Users, Wrench, Heart, AlertTriangle } from 'lucide-react';
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from 'recharts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent, ChartLegend, ChartLegendContent, type ChartConfig } from '@/components/ui/chart';
import { Alert, AlertTitle, AlertDescription } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, DashboardStats, DashboardAlerts, OnderdeelHistoryEntry } from '@/types';

type Props = {
    stats: DashboardStats;
    alerts?: DashboardAlerts;
    onderdeel_history?: OnderdeelHistoryEntry[];
    onderdeel_names?: string[];
};

const CHART_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

function slugify(name: string): string {
    return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

export default function Dashboard({ stats, alerts, onderdeel_history, onderdeel_names }: Props) {
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
            extra: (
                <p className="text-muted-foreground mt-1 text-xs">
                    <span className="text-green-600 dark:text-green-400">+{stats.leden_joined_12m}</span>
                    {' / '}
                    <span className="text-red-600 dark:text-red-400">-{stats.leden_left_12m}</span>
                    {' ' + t('last 12 months')}
                </p>
            ),
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

    const [showAllYears, setShowAllYears] = useState(false);

    const { chartConfig, chartData, chartKeys } = useMemo(() => {
        if (!onderdeel_names?.length || !onderdeel_history?.length) {
            return { chartConfig: {} as ChartConfig, chartData: [] as OnderdeelHistoryEntry[], chartKeys: [] as string[] };
        }

        const nameToSlug = new Map(onderdeel_names.map((name) => [name, slugify(name)]));

        // Filter to last 5 years unless showing all
        let filteredHistory = onderdeel_history;
        if (!showAllYears) {
            const fiveYearsAgo = new Date();
            fiveYearsAgo.setFullYear(fiveYearsAgo.getFullYear() - 5);
            const cutoff = `${fiveYearsAgo.getFullYear()}-${String(fiveYearsAgo.getMonth() + 1).padStart(2, '0')}`;
            filteredHistory = onderdeel_history.filter((entry) => entry.month >= cutoff);
        }

        const data = filteredHistory.map((entry) => {
            const row: OnderdeelHistoryEntry = { month: entry.month };
            for (const name of onderdeel_names) {
                row[nameToSlug.get(name)!] = entry[name];
            }
            return row;
        });

        // Only include onderdelen that have at least one non-zero value in the visible data
        const visibleSlugs = onderdeel_names
            .map((name) => nameToSlug.get(name)!)
            .filter((slug) => data.some((row) => (row[slug] as number) > 0));

        const config = onderdeel_names.reduce<ChartConfig>((acc, name, index) => {
            const slug = nameToSlug.get(name)!;
            if (visibleSlugs.includes(slug)) {
                acc[slug] = {
                    label: name,
                    color: CHART_COLORS[index % CHART_COLORS.length],
                };
            }
            return acc;
        }, {});

        return { chartConfig: config, chartData: data, chartKeys: visibleSlugs };
    }, [onderdeel_names, onderdeel_history, showAllYears]);

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
                                {'extra' in stat && stat.extra}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {chartData.length > 0 && chartKeys.length > 0 && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between">
                            <CardTitle>{t('Sections membership over time')}</CardTitle>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setShowAllYears(!showAllYears)}
                            >
                                {showAllYears ? t('Last 5 years') : t('All years')}
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <ChartContainer config={chartConfig} className="aspect-auto h-[350px] w-full">
                                <LineChart data={chartData} margin={{ top: 5, right: 10, left: 10, bottom: 0 }}>
                                    <CartesianGrid vertical={false} />
                                    <XAxis
                                        dataKey="month"
                                        tickLine={false}
                                        axisLine={false}
                                        tickMargin={8}
                                        tickFormatter={(value: string) => {
                                            const [year, month] = value.split('-');
                                            return month === '01' ? year : '';
                                        }}
                                    />
                                    <YAxis tickLine={false} axisLine={false} tickMargin={8} allowDecimals={false} scale="sqrt" domain={[0, 'auto']} />
                                    <ChartTooltip
                                        content={<ChartTooltipContent />}
                                        labelFormatter={(label) => {
                                            const str = String(label);
                                            const [year, month] = str.split('-');
                                            const date = new Date(Number(year), Number(month) - 1);
                                            return date.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
                                        }}
                                    />
                                    <ChartLegend content={<ChartLegendContent className="flex-wrap" />} />
                                    {chartKeys.map((key) => (
                                        <Line
                                            key={key}
                                            type="monotone"
                                            dataKey={key}
                                            stroke={`var(--color-${key})`}
                                            strokeWidth={2}
                                            dot={false}
                                        />
                                    ))}
                                </LineChart>
                            </ChartContainer>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
