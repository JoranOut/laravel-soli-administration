import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { PaginatedResponse } from '@/types/admin';

type PaginationProps = {
    pagination: PaginatedResponse<unknown>;
};

export function Pagination({ pagination }: PaginationProps) {
    const { t } = useTranslation();

    if (pagination.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between pt-4">
            <p className="text-muted-foreground text-sm">
                {t(':from–:to of :total results', { from: pagination.from ?? 0, to: pagination.to ?? 0, total: pagination.total })}
            </p>
            <div className="flex gap-1">
                {pagination.links.map((link, i) => (
                    <Button
                        key={i}
                        variant={link.active ? 'default' : 'outline'}
                        size="sm"
                        disabled={!link.url}
                        asChild={!!link.url}
                    >
                        {link.url ? (
                            <Link
                                href={link.url}
                                preserveState
                                preserveScroll
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                        )}
                    </Button>
                ))}
            </div>
        </div>
    );
}
