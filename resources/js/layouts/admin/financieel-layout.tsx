import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { NavItem } from '@/types';

export default function FinancieelLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslation();

    const navItems: NavItem[] = [
        { title: t('Rate groups'), href: '/admin/financieel/tariefgroepen', icon: null },
        { title: t('Contributions'), href: '/admin/financieel/contributies', icon: null },
        { title: t('Payments'), href: '/admin/financieel/betalingen', icon: null },
    ];

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-semibold">{t("Financial")}</h2>
                <p className="text-muted-foreground text-sm">{t("Rate groups, contributions and payments")}</p>
            </div>

            <nav className="flex gap-1 border-b pb-1">
                {navItems.map((item) => (
                    <Button
                        key={item.href as string}
                        variant="ghost"
                        size="sm"
                        asChild
                        className={cn(
                            'rounded-none border-b-2 border-transparent',
                            isCurrentOrParentUrl(item.href as string) && 'border-primary text-primary',
                        )}
                    >
                        <Link href={item.href as string}>{item.title}</Link>
                    </Button>
                ))}
            </nav>

            {children}
        </div>
    );
}
