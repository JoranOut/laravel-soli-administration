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
 * Roles like contactpersoon can hold onderdelen.view without relaties.view,
 * and the route would answer 403, so the name renders as plain text for them.
 */
export function RelatieLink({
    relatieId,
    className,
    children,
}: RelatieLinkProps) {
    const { can } = usePermissions();

    if (!can('relaties.view')) {
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
