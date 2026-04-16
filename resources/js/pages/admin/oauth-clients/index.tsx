import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronsUpDown, Pencil } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type UserRoleEntry = {
    user_id: string;
    mapped_role: string;
};

type UserOption = {
    id: number;
    name: string;
    email: string;
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

function UserCombobox({
    users,
    value,
    onChange,
    placeholder,
}: {
    users: UserOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        function onPointerDown(event: PointerEvent) {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setOpen(false);
            }
        }
        function onKey(event: KeyboardEvent) {
            if (event.key === 'Escape') setOpen(false);
        }
        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const selected = users.find((u) => String(u.id) === value);
    const query = search.trim().toLowerCase();
    const filtered = query
        ? users.filter(
              (u) => u.name.toLowerCase().includes(query) || u.email.toLowerCase().includes(query),
          )
        : users;

    return (
        <div ref={containerRef} className="relative w-[260px]">
            <Button
                type="button"
                variant="outline"
                className="w-full justify-between font-normal"
                onClick={() => setOpen((o) => !o)}
            >
                <span className={selected ? 'truncate' : 'text-muted-foreground truncate'}>
                    {selected ? `${selected.name} — ${selected.email}` : placeholder}
                </span>
                <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
            {open && (
                <div className="bg-popover text-popover-foreground absolute top-full left-0 z-50 mt-1 w-full rounded-md border shadow-md">
                    <div className="border-b p-1">
                        <Input
                            autoFocus
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search...')}
                            className="h-8"
                        />
                    </div>
                    <div className="max-h-60 overflow-y-auto py-1">
                        {filtered.length === 0 ? (
                            <div className="text-muted-foreground px-3 py-2 text-sm">
                                {t('No results found.')}
                            </div>
                        ) : (
                            filtered.map((u) => (
                                <button
                                    key={u.id}
                                    type="button"
                                    className="hover:bg-accent hover:text-accent-foreground block w-full px-3 py-1.5 text-left text-sm"
                                    onClick={() => {
                                        onChange(String(u.id));
                                        setOpen(false);
                                        setSearch('');
                                    }}
                                >
                                    <div className="truncate">{u.name}</div>
                                    <div className="text-muted-foreground truncate text-xs">
                                        {u.email}
                                    </div>
                                </button>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

export default function OauthClients({
    clients,
    relatieTypes,
    users,
}: {
    clients: OauthClient[];
    relatieTypes: RelatieType[];
    users: UserOption[];
}) {
    const { t } = useTranslation();
    const [editingClient, setEditingClient] = useState<OauthClient | null>(null);
    const [type, setType] = useState('');
    const [defaultRole, setDefaultRole] = useState('');
    const [skipAuthorization, setSkipAuthorization] = useState(false);
    const [mappings, setMappings] = useState<RoleMappingEntry[]>([]);
    const [userRoles, setUserRoles] = useState<UserRoleEntry[]>([]);

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
        setSkipAuthorization(client.setting?.skip_authorization ?? false);
        setMappings(
            client.setting?.role_mappings.map((m) => ({
                relatie_type_id: String(m.relatie_type_id),
                mapped_role: m.mapped_role,
            })) ?? [],
        );
        setUserRoles(
            client.setting?.user_roles.map((u) => ({
                user_id: String(u.user_id),
                mapped_role: u.mapped_role,
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
        const validUserRoles = userRoles.filter((u) => u.user_id && u.mapped_role);

        router.put(
            `/admin/oauth-clients/${editingClient.id}`,
            {
                type,
                default_role: defaultRole || null,
                skip_authorization: skipAuthorization,
                role_mappings: validMappings.map((m) => ({
                    relatie_type_id: Number(m.relatie_type_id),
                    mapped_role: m.mapped_role,
                })),
                user_roles: validUserRoles.map((u) => ({
                    user_id: Number(u.user_id),
                    mapped_role: u.mapped_role,
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

    function addUserRole() {
        setUserRoles([...userRoles, { user_id: '', mapped_role: '' }]);
    }

    function removeUserRole(index: number) {
        setUserRoles(userRoles.filter((_, i) => i !== index));
    }

    function updateUserRole(index: number, field: keyof UserRoleEntry, value: string) {
        setUserRoles(userRoles.map((u, i) => (i === index ? { ...u, [field]: value } : u)));
    }

    // Users not yet picked in other rows
    function availableUsers(currentIndex: number) {
        const usedIds = userRoles
            .filter((_, i) => i !== currentIndex)
            .map((u) => u.user_id);
        return users.filter((u) => !usedIds.includes(String(u.id)));
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
                                        <div className="flex flex-wrap gap-1">
                                            {client.setting ? (
                                                <Badge variant="secondary">{client.setting.type}</Badge>
                                            ) : (
                                                <span className="text-muted-foreground">-</span>
                                            )}
                                            {client.setting?.skip_authorization && (
                                                <Badge variant="outline">{t('Skip auth')}</Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {client.setting?.default_role ? (
                                            roleLabel(client.setting.default_role)
                                        ) : (
                                            <span className="text-muted-foreground">-</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {client.setting?.role_mappings.length || client.setting?.user_roles.length ? (
                                            <div className="flex flex-wrap gap-1">
                                                {client.setting?.role_mappings.map((m) => (
                                                    <Badge key={m.id} variant={m.mapped_role === NO_ACCESS ? 'destructive' : 'outline'}>
                                                        {m.relatie_type_naam} &rarr; {roleLabel(m.mapped_role)}
                                                    </Badge>
                                                ))}
                                                {client.setting && client.setting.user_roles.length > 0 && (
                                                    <Badge variant="secondary">
                                                        {client.setting.user_roles.length} {t('user overrides')}
                                                    </Badge>
                                                )}
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
                <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
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

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="skip-authorization"
                                checked={skipAuthorization}
                                onCheckedChange={(checked) => setSkipAuthorization(checked === true)}
                            />
                            <div>
                                <Label htmlFor="skip-authorization">{t('Skip authorization screen')}</Label>
                                <p className="text-xs text-muted-foreground">
                                    {t('Automatically approve authorization for this client')}
                                </p>
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

                        <div className="space-y-2">
                            <div className="flex items-center justify-between">
                                <div>
                                    <Label>{t('User-specific roles')}</Label>
                                    <p className="text-xs text-muted-foreground">
                                        {t('Overrides type mapping for specific users')}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addUserRole}
                                    disabled={userRoles.length >= users.length}
                                >
                                    {t('Add')}
                                </Button>
                            </div>

                            {userRoles.map((userRole, index) => (
                                <div key={index} className="flex items-center gap-2">
                                    <UserCombobox
                                        users={availableUsers(index)}
                                        value={userRole.user_id}
                                        onChange={(v) => updateUserRole(index, 'user_id', v)}
                                        placeholder={t('Select user')}
                                    />
                                    <span className="text-muted-foreground">&rarr;</span>
                                    {type === 'wordpress' ? (
                                        <Select
                                            value={userRole.mapped_role}
                                            onValueChange={(v) => updateUserRole(index, 'mapped_role', v)}
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
                                            value={userRole.mapped_role}
                                            onChange={(e) => updateUserRole(index, 'mapped_role', e.target.value)}
                                            placeholder={t('Mapped role')}
                                            className="flex-1"
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeUserRole(index)}
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
