import { Head } from '@inertiajs/react';
import { CircleHelp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
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
    csrfToken: string;
};

export default function OAuthAuthorize({ client, scopes, authToken, request, csrfToken }: Props) {
    const { t } = useTranslation();

    const visibleScopes = scopes.filter((scope) => scope.id !== 'openid');

    return (
        <AuthLayout
            title={t('Authorization Request')}
            description={t(':client is requesting access to your account. You only need to approve this once.', { client: client.name })}
        >
            <Head title={t('Authorize')} />

            <div className="flex flex-col gap-6">
                {visibleScopes.length > 0 && (
                    <div className="grid gap-2">
                        <p className="text-sm font-medium">{t('This application will access:')}</p>
                        <ul className="list-inside list-disc text-sm text-muted-foreground">
                            {visibleScopes.map((scope) => (
                                <li key={scope.id}>{scope.description}</li>
                            ))}
                        </ul>
                    </div>
                )}

                <TooltipProvider>
                    <p className="inline-flex items-center gap-1 text-sm text-muted-foreground">
                        {t('Why do I see this?')}
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <CircleHelp className="size-3.5" />
                            </TooltipTrigger>
                            <TooltipContent className="max-w-xs">
                                <p>{t('You are logging in to a Soli website. No data is shared with third parties. All Soli servers and websites are managed by Soli.')}</p>
                            </TooltipContent>
                        </Tooltip>
                    </p>
                </TooltipProvider>

                <div className="flex gap-3">
                    <form method="POST" action="/oauth/authorize" className="flex-1">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="state" value={request.state} />
                        <input type="hidden" name="client_id" value={client.id} />
                        <input type="hidden" name="auth_token" value={authToken} />
                        <Button type="submit" className="w-full">
                            {t('Authorize')}
                        </Button>
                    </form>

                    <form method="POST" action="/oauth/authorize" className="flex-1">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="_method" value="DELETE" />
                        <input type="hidden" name="state" value={request.state} />
                        <input type="hidden" name="client_id" value={client.id} />
                        <input type="hidden" name="auth_token" value={authToken} />
                        <Button type="submit" variant="outline" className="w-full">
                            {t('Deny')}
                        </Button>
                    </form>
                </div>
            </div>
        </AuthLayout>
    );
}
