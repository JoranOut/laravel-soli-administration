import { router, useForm } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { Relatie } from '@/types/admin';
import type { User } from '@/types/auth';

type Props = {
    relatie: Relatie;
    users: Pick<User, 'id' | 'name' | 'email'>[];
};

function generatePassword(length = 16): string {
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const digits = '23456789';
    const symbols = '!@#$%&*?';
    const all = lower + upper + digits + symbols;

    // Ensure at least one of each type
    const required = [
        lower[Math.floor(Math.random() * lower.length)],
        upper[Math.floor(Math.random() * upper.length)],
        digits[Math.floor(Math.random() * digits.length)],
        symbols[Math.floor(Math.random() * symbols.length)],
    ];

    const rest = Array.from({ length: length - required.length }, () =>
        all[Math.floor(Math.random() * all.length)]
    );

    return [...required, ...rest]
        .sort(() => Math.random() - 0.5)
        .join('');
}

function PasswordResetSection({ relatieId, t }: { relatieId: number; t: (key: string, replacements?: Record<string, string>) => string }) {
    const [password, setPassword] = useState('');
    const [saving, setSaving] = useState(false);

    const handleGenerate = () => {
        setPassword(generatePassword());
    };

    const handleReset = () => {
        if (!password) return;
        setSaving(true);
        router.put(`/admin/relaties/${relatieId}/account/password`, { password }, {
            preserveScroll: true,
            onFinish: () => {
                setSaving(false);
                setPassword('');
            },
        });
    };

    return (
        <div className="space-y-3">
            <div className="space-y-0.5">
                <p className="font-medium text-sm">{t('Reset password')}</p>
                <p className="text-muted-foreground text-sm">
                    {t('Set a new password for this user account.')}
                </p>
            </div>
            <div className="flex items-center gap-2">
                <Input
                    type="text"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder={t('New password')}
                    className="max-w-sm font-mono"
                />
                <Button type="button" variant="outline" size="icon" onClick={handleGenerate} title={t('Generate password')}>
                    <RefreshCw className="h-4 w-4" />
                </Button>
                <Button onClick={handleReset} disabled={!password || password.length < 8 || saving}>
                    {t('Apply new password')}
                </Button>
            </div>
            {password.length > 0 && password.length < 8 && (
                <p className="text-destructive text-sm">{t('Password must be at least :count characters.', { count: '8' })}</p>
            )}
        </div>
    );
}

export default function RelatieAccountTab({ relatie, users }: Props) {
    const { t } = useTranslation();
    const { delete: destroy, processing } = useForm({});
    const { processing: linking } = useForm({});
    const [search, setSearch] = useState('');
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null);

    const handleDelete = () => {
        destroy(`/admin/relaties/${relatie.id}/account`);
    };

    const handleLink = () => {
        if (!selectedUserId) return;
        router.post(`/admin/relaties/${relatie.id}/account`, { user_id: selectedUserId }, {
            preserveScroll: true,
        });
    };

    const actueelEmails = relatie.emails ?? [];
    const hasMultipleRelaties = (relatie.user?.relaties_count ?? 0) > 1;

    const handleEmailChange = (email: string) => {
        router.put(`/admin/relaties/${relatie.id}/account`, { email }, {
            preserveScroll: true,
        });
    };

    const filteredUsers = search.length > 0
        ? users.filter((u) => {
            const term = search.toLowerCase();
            return u.name.toLowerCase().includes(term) || u.email.toLowerCase().includes(term);
        }).slice(0, 20)
        : [];

    if (!relatie.user) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>{t('Linked user account')}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <p className="text-muted-foreground">{t('No linked user account.')}</p>
                    <div className="space-y-2">
                        <Label>{t('Link to user')}</Label>
                        <Input
                            placeholder={t('Search users...')}
                            value={search}
                            onChange={(e) => { setSearch(e.target.value); setSelectedUserId(null); }}
                        />
                        {filteredUsers.length > 0 && (
                            <div className="max-h-48 overflow-y-auto rounded-md border">
                                {filteredUsers.map((user) => (
                                    <button
                                        key={user.id}
                                        type="button"
                                        className={`w-full px-3 py-2 text-left text-sm hover:bg-accent ${selectedUserId === user.id ? 'bg-accent' : ''}`}
                                        onClick={() => { setSelectedUserId(user.id); setSearch(user.name); }}
                                    >
                                        <span className="font-medium">{user.name}</span>
                                        <span className="text-muted-foreground ml-2">{user.email}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                        <Button onClick={handleLink} disabled={!selectedUserId || linking}>
                            {t('Link')}
                        </Button>
                    </div>
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
                        <dt className="text-muted-foreground text-sm">{t('Login email')}</dt>
                        {actueelEmails.length > 0 ? (
                            <Select value={relatie.user.email} onValueChange={handleEmailChange}>
                                <SelectTrigger>
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
                        ) : (
                            <dd>{relatie.user.email}</dd>
                        )}
                    </div>
                </dl>

                <PasswordResetSection relatieId={relatie.id} t={t} />

                <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                    <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                        <p className="font-medium">
                            {hasMultipleRelaties ? t('Disconnect user account') : t('Delete user account')}
                        </p>
                        <p className="text-sm">
                            {hasMultipleRelaties
                                ? t('This user is linked to multiple relations. This will disconnect the user from this relation, but the user account will be preserved.')
                                : t('This will permanently delete the user account. The relation record will be preserved.')}
                        </p>
                    </div>

                    <Dialog>
                        <DialogTrigger asChild>
                            <Button variant="destructive">
                                {hasMultipleRelaties ? t('Disconnect user account') : t('Delete user account')}
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogTitle>
                                {hasMultipleRelaties
                                    ? t('Are you sure you want to disconnect this user account?')
                                    : t('Are you sure you want to delete this user account?')}
                            </DialogTitle>
                            <DialogDescription>
                                {hasMultipleRelaties
                                    ? t('This user is linked to multiple relations. This will disconnect the user from this relation, but the user account will be preserved.')
                                    : t('This will permanently delete the user account. The relation record will be preserved.')}
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
                                    {hasMultipleRelaties ? t('Disconnect user account') : t('Delete user account')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>
    );
}
