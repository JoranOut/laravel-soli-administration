import { Head } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';

export default function NotLinked() {
    const { t } = useTranslation();

    return (
        <AppLayout>
            <Head title={t('My data')} />
            <div className="flex items-center justify-center p-12">
                <div className="text-center space-y-4">
                    <AlertTriangle className="mx-auto h-12 w-12 text-muted-foreground" />
                    <p className="text-muted-foreground">
                        {t('Your profile is not linked yet. Contact an administrator.')}
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
