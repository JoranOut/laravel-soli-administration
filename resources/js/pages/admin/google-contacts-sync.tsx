import { Head, router } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Pagination } from '@/components/admin/pagination';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { GoogleContactSyncLog, PaginatedResponse } from '@/types/admin';

function StatusBadge({ status }: { status: GoogleContactSyncLog['status'] }) {
    const { t } = useTranslation();

    const classes: Record<GoogleContactSyncLog['status'], string> = {
        running:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        completed:
            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        completed_with_errors:
            'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };

    const labels: Record<GoogleContactSyncLog['status'], string> = {
        running: 'running',
        completed: 'completed',
        completed_with_errors: 'completed with errors',
        failed: 'failed',
    };

    return (
        <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${classes[status]}`}
        >
            {t(labels[status])}
        </span>
    );
}

function formatDuration(
    startedAt: string,
    completedAt: string | null,
    t: (key: string, replacements?: Record<string, string | number>) => string,
): string {
    if (!completedAt) return '...';
    const seconds = Math.round(
        (new Date(completedAt).getTime() - new Date(startedAt).getTime()) /
            1000,
    );
    return t(':seconds seconds', { seconds });
}

function relatieLabel(log: GoogleContactSyncLog): string {
    if (!log.relatie) return '';
    const { voornaam, tussenvoegsel, achternaam } = log.relatie;
    return [voornaam, tussenvoegsel, achternaam].filter(Boolean).join(' ');
}

const COL_COUNT = 10;

function SyncLogRow({ log }: { log: GoogleContactSyncLog }) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState(false);
    const hasError =
        (log.status === 'failed' || log.status === 'completed_with_errors') &&
        log.error_message;

    return (
        <>
            <tr
                className={`border-b last:border-0 ${hasError ? 'cursor-pointer hover:bg-muted/50' : ''}`}
                onClick={() => hasError && setExpanded(!expanded)}
            >
                <td className="py-2 pr-4 whitespace-nowrap">
                    <div className="flex items-center gap-1">
                        {hasError &&
                            (expanded ? (
                                <ChevronDown className="size-3.5 text-muted-foreground" />
                            ) : (
                                <ChevronRight className="size-3.5 text-muted-foreground" />
                            ))}
                        {new Date(log.started_at).toLocaleString()}
                    </div>
                </td>
                <td className="py-2 pr-4 whitespace-nowrap">
                    {t(log.type)}
                    {log.type === 'relatie' && log.relatie && (
                        <span className="ml-1 text-muted-foreground">
                            ({relatieLabel(log)})
                        </span>
                    )}
                </td>
                <td className="py-2 pr-4">
                    <StatusBadge status={log.status} />
                </td>
                <td className="py-2 pr-4 text-center">{log.workspace_users}</td>
                <td className="py-2 pr-4 text-center">
                    {log.contacts_created}
                </td>
                <td className="py-2 pr-4 text-center">
                    {log.contacts_updated}
                </td>
                <td className="py-2 pr-4 text-center">
                    {log.contacts_deleted}
                </td>
                <td className="py-2 pr-4 text-center">
                    {log.contacts_skipped}
                </td>
                <td className="py-2 pr-4 text-center">
                    {log.contacts_failed > 0 ? (
                        <span className="font-medium text-red-600 dark:text-red-400">
                            {log.contacts_failed}
                        </span>
                    ) : (
                        log.contacts_failed
                    )}
                </td>
                <td className="py-2 whitespace-nowrap">
                    {formatDuration(log.started_at, log.completed_at, t)}
                </td>
            </tr>
            {hasError && expanded && (
                <tr className="border-b last:border-0">
                    <td colSpan={COL_COUNT} className="px-4 pt-0 pb-3">
                        <pre className="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs whitespace-pre-wrap text-muted-foreground">
                            {log.error_message}
                        </pre>
                    </td>
                </tr>
            )}
        </>
    );
}

export default function GoogleContactsSync({
    logs,
}: {
    logs: PaginatedResponse<GoogleContactSyncLog>;
}) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('Google Contacts'), href: '/admin/google-contacts-sync' },
    ];

    function handleSync() {
        setProcessing(true);
        router.post(
            '/admin/google-contacts-sync',
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Google Contacts')} />
            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('Google Contacts')}
                        description={t(
                            'Google Contacts sync history and controls.',
                        )}
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
                                    <tr className="border-b text-left text-xs text-muted-foreground">
                                        <th className="pr-4 pb-2">
                                            {t('Date')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Type')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Status')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Workspace users')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Created')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Updated')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Deleted')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Skipped')}
                                        </th>
                                        <th className="pr-4 pb-2">
                                            {t('Failed')}
                                        </th>
                                        <th className="pb-2">
                                            {t('Duration')}
                                        </th>
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
                    <p className="text-sm text-muted-foreground">
                        {t('No sync logs recorded.')}
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
