import { Form, Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslation();
    const [submitting, setSubmitting] = useState(false);

    // A successful login ends in a full-page redirect (Inertia::location to
    // the OAuth client), which takes a moment after the request finishes.
    // Keep the spinner until the page unloads; reset it on failure or when
    // the browser restores this page from the back/forward cache.
    useEffect(() => {
        const reset = () => setSubmitting(false);
        const onPageShow = (event: PageTransitionEvent) => {
            if (event.persisted) {
                reset();
            }
        };
        const removeInvalid = router.on('invalid', reset);
        const removeException = router.on('exception', reset);
        window.addEventListener('pageshow', onPageShow);

        return () => {
            removeInvalid();
            removeException();
            window.removeEventListener('pageshow', onPageShow);
        };
    }, []);

    return (
        <AuthLayout
            title={t('Log in to your account')}
            description={t('Enter your email and password below to log in')}
        >
            <Head title={t('Log in')} />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                onStart={() => setSubmitting(true)}
                onError={() => setSubmitting(false)}
                onCancel={() => setSubmitting(false)}
                className="flex flex-col gap-6"
            >
                {({ errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder={t('email@example.com')}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        {t('Password')}
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                            tabIndex={5}
                                        >
                                            {t('Forgot password?')}
                                        </TextLink>
                                    )}
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder={t('Password')}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember">
                                    {t('Remember me')}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                tabIndex={4}
                                disabled={submitting}
                                data-test="login-button"
                            >
                                {submitting && <Spinner />}
                                {t('Log in')}
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </AuthLayout>
    );
}
