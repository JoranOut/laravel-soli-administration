import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

import { DataTable, type Column } from '@/components/admin/data-table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useTranslation } from '@/hooks/use-translation';
import type { InstrumentFamilie, InstrumentSoort } from '@/types/admin';

type InstrumentSoortWithCount = InstrumentSoort & {
    relatie_instrumenten_count: number;
};

type Props = {
    instrumentSoorten: InstrumentSoortWithCount[];
    families: InstrumentFamilie[];
};

export default function InstrumentSoortenIndex({ instrumentSoorten }: Props) {
    const { t } = useTranslation();

    const columns: Column<InstrumentSoortWithCount>[] = [
        {
            key: 'naam',
            label: t('Name'),
        },
        {
            key: 'instrument_familie',
            label: t('Family'),
            render: (item) => item.instrument_familie?.naam ?? '',
        },
        {
            key: 'relatie_instrumenten_count',
            label: t('Members'),
            render: (item) => item.relatie_instrumenten_count,
        },
    ];

    return (
        <AppLayout>
            <Head title={t('Instrument types')} />
            <div className="space-y-4 p-4">
                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertTitle>{t('Instrument types vs. Soli instruments')}</AlertTitle>
                    <AlertDescription>
                        {t('instrument_types_explanation')}
                    </AlertDescription>
                </Alert>

                <h2 className="text-lg font-semibold">{t('Instrument types')}</h2>

                <DataTable columns={columns} data={instrumentSoorten} />
            </div>
        </AppLayout>
    );
}
