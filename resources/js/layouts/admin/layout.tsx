import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

export default function AdminLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    const sidebarNavItems: NavItem[] = [
        {
            title: t('Roles & permissions'),
            href: '/admin/roles',
            icon: null,
        },
        {
            title: t('User roles'),
            href: '/admin/users',
            icon: null,
        },
        {
            title: t('User-relation links'),
            href: '/admin/koppelingen',
            icon: null,
        },
        {
            title: t('Activity log'),
            href: '/admin/activity-log',
            icon: null,
        },
    ];

    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="px-4 py-6">
            <Heading
                title={t('Authentication')}
                description={t('Manage system roles and permissions')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Administration"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${item.href}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(
                                        item.href as string,
                                    ),
                                })}
                            >
                                <Link href={item.href as string}>
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
