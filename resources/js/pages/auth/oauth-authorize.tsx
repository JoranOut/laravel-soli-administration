import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';

type Scope = {
    id: string;
    description: string;
};

type Client = {
    id: string;
    name: string;
};

type Props = {
    client: Client;
    scopes: Scope[];
    authToken: string;
    request: {
        state: string;
    };
};

export default function OAuthAuthorize({ client, scopes, authToken, request }: Props) {
    const { t } = useTranslation();

    const approveForm = useForm({
        state: request.state,
        client_id: client.id,
        auth_token: authToken,
    });

    const denyForm = useForm({
        state: request.state,
        client_id: client.id,
        auth_token: authToken,
    });

    function approve(e: React.FormEvent) {
        e.preventDefault();
        approveForm.post('/oauth/authorize');
    }

    function deny(e: React.FormEvent) {
        e.preventDefault();
        denyForm.delete('/oauth/authorize');
    }

    return (
        <AuthLayout
            title={t('Authorization Request')}
            description={t(':client is requesting access to your account.', { client: client.name })}
        >
            <Head title={t('Authorize')} />

            <div className="flex flex-col gap-6">
                {scopes.length > 0 && (
                    <div className="grid gap-2">
                        <p className="text-sm font-medium">{t('This application will be able to:')}</p>
                        <ul className="list-inside list-disc text-sm text-muted-foreground">
                            {scopes.map((scope) => (
                                <li key={scope.id}>{scope.description}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <div className="flex gap-3">
                    <form onSubmit={approve} className="flex-1">
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={approveForm.processing}
                        >
                            {approveForm.processing && <Spinner />}
                            {t('Authorize')}
                        </Button>
                    </form>

                    <form onSubmit={deny} className="flex-1">
                        <Button
                            type="submit"
                            variant="outline"
                            className="w-full"
                            disabled={denyForm.processing}
                        >
                            {denyForm.processing && <Spinner />}
                            {t('Deny')}
                        </Button>
                    </form>
                </div>
            </div>
        </AuthLayout>
    );
}
