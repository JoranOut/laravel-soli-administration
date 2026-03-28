import { Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
import type { BreadcrumbItem } from '@/types';

export default function Profile() {
    const { t } = useTranslation();
    const { auth } = usePage().props;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Profile settings'),
            href: edit(),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t("Profile settings")} />

            <h1 className="sr-only">{t("Profile settings")}</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={t("Profile information")}
                        description={t("Contact ledenadministratie@soli.nl to update your name or email address.")}
                    />

                    <div className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="name">{t("Name")}</Label>
                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                defaultValue={auth.user.name}
                                disabled
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">{t("Email address")}</Label>
                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                defaultValue={auth.user.email}
                                disabled
                            />
                        </div>
                    </div>
                </div>

            </SettingsLayout>
        </AppLayout>
    );
}
