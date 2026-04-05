import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from '@/hooks/use-translation';
import type { BreadcrumbItem } from '@/types';
import type { OauthClient, RelatieType } from '@/types/admin';

type RoleMappingEntry = {
    relatie_type_id: string;
    mapped_role: string;
};

const NO_ACCESS = '__no_access__';

const WORDPRESS_ROLES = [
    { value: NO_ACCESS, label: 'No access' },
    { value: 'administrator', label: 'Administrator' },
    { value: 'editor', label: 'Editor' },
    { value: 'author', label: 'Author' },
    { value: 'contributor', label: 'Contributor' },
    { value: 'subscriber', label: 'Subscriber' },
];

export default function OauthClients({
    clients,
    relatieTypes,
}: {
    clients: OauthClient[];
    relatieTypes: RelatieType[];
}) {
    const { t } = useTranslation();
    const [editingClient, setEditingClient] = useState<OauthClient | null>(null);
    const [type, setType] = useState('');
    const [defaultRole, setDefaultRole] = useState('');
    const [mappings, setMappings] = useState<RoleMappingEntry[]>([]);

    function roleLabel(role: string): string {
        if (role === NO_ACCESS) return t('No access');
        return role;
    }

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('OAuth clients'), href: '/admin/oauth-clients' },
    ];

    function openEdit(client: OauthClient) {
        setEditingClient(client);
        setType(client.setting?.type ?? 'wordpress');
        setDefaultRole(client.setting?.default_role ?? NO_ACCESS);
        setMappings(
            client.setting?.role_mappings.map((m) => ({
                relatie_type_id: String(m.relatie_type_id),
                mapped_role: m.mapped_role,
            })) ?? [],
        );
    }

    function addMapping() {
        setMappings([...mappings, { relatie_type_id: '', mapped_role: '' }]);
    }

    function removeMapping(index: number) {
        setMappings(mappings.filter((_, i) => i !== index));
    }

    function updateMapping(index: number, field: keyof RoleMappingEntry, value: string) {
        setMappings(mappings.map((m, i) => (i === index ? { ...m, [field]: value } : m)));
    }

    function moveMapping(index: number, direction: 'up' | 'down') {
        const target = direction === 'up' ? index - 1 : index + 1;
        if (target < 0 || target >= mappings.length) return;
        const updated = [...mappings];
        [updated[index], updated[target]] = [updated[target], updated[index]];
        setMappings(updated);
    }

    function saveSettings() {
        if (!editingClient) return;

        const validMappings = mappings.filter((m) => m.relatie_type_id && m.mapped_role);

        router.put(
            `/admin/oauth-clients/${editingClient.id}`,
            {
                type,
                default_role: defaultRole || null,
                role_mappings: validMappings.map((m) => ({
                    relatie_type_id: Number(m.relatie_type_id),
                    mapped_role: m.mapped_role,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditingClient(null),
            },
        );
    }

    // Relatie types not yet used in current mappings
    function availableTypes(currentIndex: number) {
        const usedIds = mappings
            .filter((_, i) => i !== currentIndex)
            .map((m) => m.relatie_type_id);
        return relatieTypes.filter((rt) => !usedIds.includes(String(rt.id)));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('OAuth clients')} />

            <div className="space-y-6 p-4">
                <Heading
                    title={t('OAuth clients')}
                    description={t('Manage client types and role mappings')}
                />

                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b">
                                <th className="py-3 pr-4 text-left font-medium">{t('Name')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('Client type')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('Default role')}</th>
                                <th className="px-4 py-3 text-left font-medium">{t('Role mappings')}</th>
                                <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {clients.map((client) => (
                                <tr key={client.id} className="border-b">
                                    <td className="py-3 pr-4 font-medium">{client.name}</td>
                                    <td className="px-4 py-3">
                                        {client.setting ? (
                                            <Badge variant="secondary">{client.setting.type}</Badge>
                                        ) : (
                                            <span className="text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {client.setting?.default_role ? (
                                            roleLabel(client.setting.default_role)
                                        ) : (
                                            <span className="text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {client.setting?.role_mappings.length ? (
                                            <div className="flex flex-wrap gap-1">
                                                {client.setting.role_mappings.map((m) => (
                                                    <Badge key={m.id} variant={m.mapped_role === NO_ACCESS ? 'destructive' : 'outline'}>
                                                        {m.relatie_type_naam} &rarr; {roleLabel(m.mapped_role)}
                                                    </Badge>
                                                ))}
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => openEdit(client)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {clients.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="py-6 text-center text-muted-foreground">
                                        {t('No OAuth clients found.')}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={!!editingClient} onOpenChange={(open) => !open && setEditingClient(null)}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {t('Edit client settings')}: {editingClient?.name}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>{t('Client type')}</Label>
                                <Select value={type} onValueChange={setType}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="wordpress">WordPress</SelectItem>
                                        <SelectItem value="other">{t('Other')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>{t('Default role')}</Label>
                                {type === 'wordpress' ? (
                                    <Select value={defaultRole} onValueChange={setDefaultRole}>
                                        <SelectTrigger>
                                            <SelectValue placeholder={t('Select...')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {WORDPRESS_ROLES.map((r) => (
                                                <SelectItem key={r.value} value={r.value}>
                                                    {r.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <Input
                                        value={defaultRole}
                                        onChange={(e) => setDefaultRole(e.target.value)}
                                        placeholder="e.g. subscriber"
                                    />
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>{t('Role mappings')}</Label>
                                    {mappings.length > 1 && (
                                        <p className="text-xs text-muted-foreground">{t('Highest priority first')}</p>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addMapping}
                                    disabled={mappings.length >= relatieTypes.length}
                                >
                                    {t('Add')}
                                </Button>
                            </div>

                            {mappings.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    {t('No role mappings configured. System roles will be used.')}
                                </p>
                            )}

                            {mappings.map((mapping, index) => (
                                <div key={index} className="flex items-center gap-2">
                                    <div className="flex flex-col">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-5 w-5"
                                            disabled={index === 0}
                                            onClick={() => moveMapping(index, 'up')}
                                        >
                                            <ArrowUp className="h-3 w-3" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-5 w-5"
                                            disabled={index === mappings.length - 1}
                                            onClick={() => moveMapping(index, 'down')}
                                        >
                                            <ArrowDown className="h-3 w-3" />
                                        </Button>
                                    </div>
                                    <span className="w-6 text-center text-xs text-muted-foreground">{index + 1}</span>
                                    <Select
                                        value={mapping.relatie_type_id}
                                        onValueChange={(v) => updateMapping(index, 'relatie_type_id', v)}
                                    >
                                        <SelectTrigger className="w-[180px]">
                                            <SelectValue placeholder={t('Select type')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableTypes(index).map((rt) => (
                                                <SelectItem key={rt.id} value={String(rt.id)}>
                                                    {rt.naam}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <span className="text-muted-foreground">&rarr;</span>
                                    {type === 'wordpress' ? (
                                        <Select
                                            value={mapping.mapped_role}
                                            onValueChange={(v) => updateMapping(index, 'mapped_role', v)}
                                        >
                                            <SelectTrigger className="flex-1">
                                                <SelectValue placeholder={t('Select...')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {WORDPRESS_ROLES.map((r) => (
                                                    <SelectItem key={r.value} value={r.value}>
                                                        {r.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    ) : (
                                        <Input
                                            value={mapping.mapped_role}
                                            onChange={(e) => updateMapping(index, 'mapped_role', e.target.value)}
                                            placeholder={t('Mapped role')}
                                            className="flex-1"
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeMapping(index)}
                                    >
                                        &times;
                                    </Button>
                                </div>
                            ))}
                        </div>

                        <div className="flex justify-end gap-2 pt-4">
                            <Button variant="outline" onClick={() => setEditingClient(null)}>
                                {t('Cancel')}
                            </Button>
                            <Button onClick={saveSettings}>{t('Save')}</Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
