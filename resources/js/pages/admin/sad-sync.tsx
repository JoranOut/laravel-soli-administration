import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import Heading from '@/components/heading';
import { Pagination } from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import type { BreadcrumbItem } from '@/types';
import type { SadSyncLog, PaginatedResponse } from '@/types/admin';

function StatusBadge({ status }: { status: SadSyncLog['status'] }) {
    const { t } = useTranslation();

    const classes: Record<SadSyncLog['status'], string> = {
        running: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        completed_with_errors: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };

    const labels: Record<SadSyncLog['status'], string> = {
        running: 'running',
        completed: 'completed',
        completed_with_errors: 'completed with errors',
        failed: 'failed',
    };

    return (
        <span className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${classes[status]}`}>
            {t(labels[status])}
        </span>
    );
}

function formatDuration(startedAt: string, completedAt: string | null, t: (key: string, replacements?: Record<string, string | number>) => string): string {
    if (!completedAt) return '...';
    const seconds = Math.round((new Date(completedAt).getTime() - new Date(startedAt).getTime()) / 1000);
    return t(':seconds seconds', { seconds });
}

const COL_COUNT = 9;

function SyncLogRow({ log }: { log: SadSyncLog }) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState(false);
    const hasError = (log.status === 'failed' || log.status === 'completed_with_errors') && log.error_message;

    return (
        <>
            <tr
                className={`border-b last:border-0 ${hasError ? 'cursor-pointer hover:bg-muted/50' : ''}`}
                onClick={() => hasError && setExpanded(!expanded)}
            >
                <td className="py-2 pr-4 whitespace-nowrap">
                    <div className="flex items-center gap-1">
                        {hasError && (
                            expanded
                                ? <ChevronDown className="text-muted-foreground size-3.5" />
                                : <ChevronRight className="text-muted-foreground size-3.5" />
                        )}
                        {new Date(log.started_at).toLocaleString()}
                    </div>
                </td>
                <td className="py-2 pr-4">
                    <StatusBadge status={log.status} />
                </td>
                <td className="py-2 pr-4 text-center">{log.total ?? '—'}</td>
                <td className="py-2 pr-4 text-center">{log.created ?? '—'}</td>
                <td className="py-2 pr-4 text-center">{log.updated ?? '—'}</td>
                <td className="py-2 pr-4 text-center">{log.skipped ?? '—'}</td>
                <td className="py-2 pr-4 text-center">
                    {log.failed !== null && log.failed > 0 ? (
                        <span className="text-red-600 dark:text-red-400 font-medium">{log.failed}</span>
                    ) : (
                        log.failed ?? '—'
                    )}
                </td>
                <td className="py-2 pr-4 text-center">{log.deactivated ?? '—'}</td>
                <td className="py-2 whitespace-nowrap">
                    {formatDuration(log.started_at, log.completed_at, t)}
                </td>
            </tr>
            {hasError && expanded && (
                <tr className="border-b last:border-0">
                    <td colSpan={COL_COUNT} className="px-4 pb-3 pt-0">
                        <pre className="bg-muted text-muted-foreground max-h-48 overflow-auto rounded-md p-3 text-xs whitespace-pre-wrap">
                            {log.error_message}
                        </pre>
                    </td>
                </tr>
            )}
        </>
    );
}

export default function SadSync({
    logs,
}: {
    logs: PaginatedResponse<SadSyncLog>;
}) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('SAD Sync'), href: '/admin/sad-sync' },
    ];

    function handleSync() {
        setProcessing(true);
        router.post('/admin/sad-sync', {}, {
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('SAD Sync')} />
            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('SAD Sync')}
                        description={t('SAD member sync history and controls.')}
                    />
                    <Button onClick={handleSync} disabled={processing}>
                        {processing ? t('Syncing...') : t('Run full sync')}
                    </Button>
                </div>

                {logs.data.length > 0 ? (
                    <div className="space-y-3">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-muted-foreground border-b text-left text-xs">
                                        <th className="pb-2 pr-4">{t('Date')}</th>
                                        <th className="pb-2 pr-4">{t('Status')}</th>
                                        <th className="pb-2 pr-4">{t('Total')}</th>
                                        <th className="pb-2 pr-4">{t('Created')}</th>
                                        <th className="pb-2 pr-4">{t('Updated')}</th>
                                        <th className="pb-2 pr-4">{t('Skipped')}</th>
                                        <th className="pb-2 pr-4">{t('Failed')}</th>
                                        <th className="pb-2 pr-4">{t('Deactivated')}</th>
                                        <th className="pb-2">{t('Duration')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((log) => (
                                        <SyncLogRow key={log.id} log={log} />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Pagination pagination={logs} />
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">{t('No sync logs recorded.')}</p>
                )}
            </div>
        </AppLayout>
    );
}
