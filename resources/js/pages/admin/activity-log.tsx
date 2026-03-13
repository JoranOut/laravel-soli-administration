import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/admin/pagination';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/admin';

type ActivityEntry = {
    id: number;
    description: string;
    event: string | null;
    subject_type: string | null;
    causer: { name: string } | null;
    created_at: string;
    properties: Record<string, unknown>;
};

export default function ActivityLog({
    activities,
}: {
    activities: PaginatedResponse<ActivityEntry>;
}) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Activity log'), href: '/admin/activity-log' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Activity log')} />
            <div className="space-y-6 p-4">
                <Heading
                    title={t('Activity log')}
                    description={t('All changes are logged for audit purposes.')}
                />

                {activities.data.length > 0 ? (
                    <div className="space-y-3">
                        {activities.data.map((activity) => (
                            <Card key={activity.id}>
                                <CardContent className="flex items-center justify-between py-3">
                                    <div>
                                        <p className="text-sm font-medium">{activity.description}</p>
                                        <p className="text-muted-foreground text-xs">
                                            {activity.causer?.name ?? t('System')} &middot;{' '}
                                            {new Date(activity.created_at).toLocaleString()}
                                        </p>
                                    </div>
                                    {activity.event && (
                                        <span className="text-muted-foreground text-xs uppercase">
                                            {t(activity.event)}
                                        </span>
                                    )}
                                </CardContent>
                            </Card>
                        ))}

                        <Pagination pagination={activities} />
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">{t('No activity recorded.')}</p>
                )}
            </div>
        </AppLayout>
    );
}
