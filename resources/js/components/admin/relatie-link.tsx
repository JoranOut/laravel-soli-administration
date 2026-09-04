import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';

type RelatieLinkProps = {
    relatieId: number;
    className?: string;
    children: ReactNode;
};

/**
 * A relatie name, linked only for users who may open the relatie page.
 *
 * A role can hold onderdelen.view or instrumenten.view without
 * relaties.view.all, and the relatie route answers 403 for them, so the name
 * renders as plain text rather than as a dead link.
 */
export function RelatieLink({
    relatieId,
    className,
    children,
}: RelatieLinkProps) {
    const { can } = usePermissions();

    if (!can('relaties.view.all')) {
        return <span className={className}>{children}</span>;
    }

    return (
        <Link
            href={`/admin/relaties/${relatieId}`}
            className={cn('text-primary hover:underline', className)}
        >
            {children}
        </Link>
    );
}
