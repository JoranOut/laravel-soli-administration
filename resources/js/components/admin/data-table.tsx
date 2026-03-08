import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

export type Column<T> = {
    key: string;
    label: string;
    sortable?: boolean;
    render?: (item: T) => ReactNode;
};

type DataTableProps<T> = {
    columns: Column<T>[];
    data: T[];
    sortKey?: string;
    sortDirection?: string;
    onSort?: (key: string) => void;
    emptyMessage?: string;
};

export function DataTable<T extends { id: number }>({
    columns,
    data,
    sortKey,
    sortDirection,
    onSort,
    emptyMessage,
}: DataTableProps<T>) {
    const { t } = useTranslation();
    const resolvedEmptyMessage = emptyMessage ?? t('No results found.');

    const handleSort = (key: string) => {
        if (onSort) {
            onSort(key);
        }
    };

    const getSortIcon = (key: string) => {
        if (sortKey !== key) return <ArrowUpDown className="ml-1 inline h-4 w-4" />;
        return sortDirection === 'asc' ? (
            <ArrowUp className="ml-1 inline h-4 w-4" />
        ) : (
            <ArrowDown className="ml-1 inline h-4 w-4" />
        );
    };

    return (
        <div className="overflow-x-auto rounded-md border">
            <table className="w-full text-sm">
                <thead>
                    <tr className="bg-muted/50 border-b">
                        {columns.map((col) => (
                            <th key={col.key} className="px-4 py-3 text-left font-medium">
                                {col.sortable ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="-ml-3 h-8"
                                        onClick={() => handleSort(col.key)}
                                    >
                                        {col.label}
                                        {getSortIcon(col.key)}
                                    </Button>
                                ) : (
                                    col.label
                                )}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {data.length === 0 ? (
                        <tr>
                            <td colSpan={columns.length} className="text-muted-foreground px-4 py-8 text-center">
                                {resolvedEmptyMessage}
                            </td>
                        </tr>
                    ) : (
                        data.map((item) => (
                            <tr key={item.id} className="hover:bg-muted/50 border-b last:border-0">
                                {columns.map((col) => (
                                    <td key={col.key} className="px-4 py-3">
                                        {col.render
                                            ? col.render(item)
                                            : (item as Record<string, unknown>)[col.key]?.toString() ?? ''}
                                    </td>
                                ))}
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
}
