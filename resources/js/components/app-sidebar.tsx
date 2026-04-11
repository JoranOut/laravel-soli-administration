import { Link, usePage } from '@inertiajs/react';
import { Coins, Globe, Guitar, LayoutGrid, Mail, Music, Rocket, Shield, ShoppingCart, User, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { canAny, hasRole } = usePermissions();
    const { t } = useTranslation();
    const { sidebarRelatieTypes } = usePage().props;
    const isMember = hasRole('member') && !hasRole('admin') && !hasRole('bestuur') && !hasRole('ledenadministratie');

    const mainNavItems: NavItem[] = [];

    if (isMember) {
        mainNavItems.push({
            title: t('My data'),
            href: dashboard(),
            icon: User,
        });
    } else if (!isMember) {
        mainNavItems.push({
            title: t('Overview'),
            href: dashboard(),
            icon: LayoutGrid,
        });
    }

    mainNavItems.push({
        title: t('Contact'),
        href: '/contact',
        icon: Mail,
    });

    const footerNavItems: NavItem[] = [
        {
            title: 'soli.nl',
            href: 'https://soli.nl',
            icon: Globe,
        },
        {
            title: t('My page'),
            href: 'https://soli.nl/mijn-pagina',
            icon: User,
        },
        {
            title: t('Shop'),
            href: 'https://winkel.soli.nl',
            icon: ShoppingCart,
        },
        {
            title: 'dev.soli.nl',
            href: 'https://dev.soli.nl',
            icon: Rocket,
        },
    ];

    const dataNavItems: NavItem[] = [];

    if (canAny(['relaties.view']) && !isMember) {
        const typeChildren: NavItem[] = (sidebarRelatieTypes ?? []).map((type) => ({
            title: type.naam.charAt(0).toUpperCase() + type.naam.slice(1),
            href: `/admin/relaties?type=${type.naam}`,
        }));

        dataNavItems.push({
            title: t('Relations'),
            href: '/admin/relaties',
            icon: Users,
            children: typeChildren,
            allLabel: t('All relations'),
        });
    }

    if (canAny(['onderdelen.view'])) {
        dataNavItems.push({
            title: t('Sections'),
            href: '/admin/onderdelen',
            icon: Music,
        });
    }

    if (canAny(['instrumenten.view'])) {
        dataNavItems.push({
            title: t('Instruments'),
            href: '/admin/instrumenten',
            icon: Guitar,
        });
    }

    if (canAny(['financieel.view'])) {
        dataNavItems.push({
            title: t('Financial'),
            href: '/admin/financieel/tariefgroepen',
            icon: Coins,
        });
    }

    const adminNavItems: NavItem[] = [];

    if (hasRole('admin')) {
        adminNavItems.push({
            title: t('Authentication'),
            href: '/admin/roles',
            icon: Shield,
            allLabel: t('Roles & permissions'),
            children: [
                { title: t('Users'), href: '/admin/users' },
                { title: t('Links'), href: '/admin/koppelingen' },
                { title: t('Activity log'), href: '/admin/activity-log' },
                { title: t('OAuth clients'), href: '/admin/oauth-clients' },
                { title: t('Google Contacts'), href: '/admin/google-contacts-sync' },
            ],
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} label={t('Platform')} />
                {dataNavItems.length > 0 && <NavMain items={dataNavItems} label={t('Management')} />}
                {adminNavItems.length > 0 && <NavMain items={adminNavItems} label={t('System')} />}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <LocaleSwitcher />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
