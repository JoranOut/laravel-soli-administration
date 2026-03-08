import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { Relatie } from '@/types/admin';

type Props = {
    relatie: Relatie;
};

export default function RelatieAccountTab({ relatie }: Props) {
    const { t } = useTranslation();
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        destroy(`/admin/relaties/${relatie.id}/account`);
    };

    const actueelEmails = relatie.emails ?? [];

    const handleEmailChange = (email: string) => {
        router.put(`/admin/relaties/${relatie.id}/account`, { email }, {
            preserveScroll: true,
        });
    };

    if (!relatie.user) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{t('Linked user account')}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-muted-foreground">{t('No linked user account.')}</p>
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Linked user account')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground text-sm">{t('Name')}</dt>
                        <dd>{relatie.user.name}</dd>
                    </div>
                    <div>
                        <Label htmlFor="login-email" className="text-muted-foreground text-sm">{t('Login email')}</Label>
                        <Select value={relatie.user.email} onValueChange={handleEmailChange}>
                            <SelectTrigger id="login-email">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {actueelEmails.map((email) => (
                                    <SelectItem key={email.id} value={email.email}>
                                        {email.email}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </dl>

                <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p className="font-medium">{t('Delete user account')}</p>
                        <p className="text-sm">
                            {t('This will permanently delete the user account. The relation record will be preserved.')}
                        </p>
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">
                                {t('Delete user account')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                {t('Are you sure you want to delete this user account?')}
                            </DialogTitle>
                            <DialogDescription>
                                {t('This will permanently delete the user account. The relation record will be preserved.')}
                            </DialogDescription>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">
                                        {t('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    onClick={handleDelete}
                                >
                                    {t('Delete user account')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>
    );
}
