import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Info } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { TabNavigation, type Tab } from '@/components/admin/tab-navigation';
import { usePermissions } from '@/hooks/use-permissions';
import { useTranslation } from '@/hooks/use-translation';
import RelatieOverviewTab from '@/pages/admin/relaties/tabs/overview-tab';
import RelatieTypesTab from '@/pages/admin/relaties/tabs/types-tab';
import RelatieContactTab from '@/pages/admin/relaties/tabs/contact-tab';
import RelatieLidmaatschapTab from '@/pages/admin/relaties/tabs/lidmaatschap-tab';
import RelatieOpleidingTab from '@/pages/admin/relaties/tabs/opleiding-tab';
import RelatieFinancieelTab from '@/pages/admin/relaties/tabs/financieel-tab';
import RelatieInstrumentenTab from '@/pages/admin/relaties/tabs/instrumenten-tab';
import RelatieAccountTab from '@/pages/admin/relaties/tabs/account-tab';
import type { InstrumentSoort, Onderdeel, Relatie, RelatieType } from '@/types/admin';
import type { User } from '@/types/auth';

type RelatieSummary = Pick<Relatie, 'id' | 'voornaam' | 'tussenvoegsel' | 'achternaam' | 'relatie_nummer'>;

type Props = {
    relatie: Relatie;
    relatieTypes: RelatieType[];
    onderdelen: Onderdeel[];
    instrumentSoorten?: InstrumentSoort[];
    users?: Pick<User, 'id' | 'name' | 'email'>[];
    userRelaties?: RelatieSummary[];
};

function formatName(r: RelatieSummary): string {
    return [r.voornaam, r.tussenvoegsel, r.achternaam].filter(Boolean).join(' ');
}

export default function RelatieShow({ relatie, relatieTypes, onderdelen, instrumentSoorten, users, userRelaties }: Props) {
    const [activeTab, setActiveTab] = useState('overview');
    const { can, hasRole } = usePermissions();
    const { t } = useTranslation();

    const showSwitcher = userRelaties && userRelaties.length > 1;

    const handleRelatieSwitched = (id: string) => {
        router.get('/dashboard', { relatie: id }, { preserveState: false });
    };

    const tabs: Tab[] = [
        { key: 'overview', label: t('Overview') },
        { key: 'types', label: t('Types') },
        { key: 'contact', label: t('Contact') },
        { key: 'lidmaatschap', label: t('Membership') },
        { key: 'opleiding', label: t('Education') },
        { key: 'financieel', label: t('Financial') },
        { key: 'instrumenten', label: t('Instruments') },
        ...(can('users.edit') ? [{ key: 'account', label: t('Account') }] : []),
    ];

    return (
        <AppLayout>
            <Head title={relatie.volledige_naam} />
            <div className="space-y-6 p-4">
                    {!hasRole('member') && (
                        <div className="flex items-center gap-4">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href="/admin/relaties">
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    {t('Back')}
                                </Link>
                            </Button>
                        </div>
                    )}

                    <Alert>
                        <Info className="h-4 w-4" />
                        <AlertTitle>{t('Please note')}</AlertTitle>
                        <AlertDescription>
                            {t('All relation data is currently stored in a different system. This page only stores information needed for communication with other systems.')}
                        </AlertDescription>
                    </Alert>

                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="text-2xl font-bold">{relatie.volledige_naam}</h2>
                            <p className="text-muted-foreground">{t('Relation number')}: {relatie.relatie_nummer}</p>
                        </div>
                        <div className="flex items-center gap-3">
                            {showSwitcher && (
                                <Select value={String(relatie.id)} onValueChange={handleRelatieSwitched}>
                                    <SelectTrigger className="w-[220px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {userRelaties.map((r) => (
                                            <SelectItem key={r.id} value={String(r.id)}>
                                                {formatName(r)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                            <Badge variant={relatie.actief ? 'default' : 'outline'}>
                                {relatie.actief ? t('Active') : t('Inactive')}
                            </Badge>
                        </div>
                    </div>

                    <TabNavigation tabs={tabs} activeTab={activeTab} onTabChange={setActiveTab} />

                    <div className="pt-4">
                        {activeTab === 'overview' && (
                            <RelatieOverviewTab relatie={relatie} />
                        )}
                        {activeTab === 'types' && (
                            <RelatieTypesTab relatie={relatie} relatieTypes={relatieTypes} onderdelen={onderdelen} />
                        )}
                        {activeTab === 'contact' && (
                            <RelatieContactTab relatie={relatie} />
                        )}
                        {activeTab === 'lidmaatschap' && (
                            <RelatieLidmaatschapTab relatie={relatie} onderdelen={onderdelen} instrumentSoorten={instrumentSoorten ?? []} />
                        )}
                        {activeTab === 'opleiding' && (
                            <RelatieOpleidingTab relatie={relatie} />
                        )}
                        {activeTab === 'financieel' && (
                            <RelatieFinancieelTab relatie={relatie} />
                        )}
                        {activeTab === 'instrumenten' && (
                            <RelatieInstrumentenTab relatie={relatie} />
                        )}
                        {activeTab === 'account' && can('users.edit') && (
                            <RelatieAccountTab relatie={relatie} users={users ?? []} />
                        )}
                    </div>
            </div>
        </AppLayout>
    );
}
