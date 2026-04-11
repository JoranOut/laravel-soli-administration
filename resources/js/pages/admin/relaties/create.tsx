import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { WizardStepIndicator, type WizardStep } from '@/components/admin/wizard-step-indicator';
import { useTranslation } from '@/hooks/use-translation';
import Step1Personal from '@/pages/admin/relaties/wizard/step-1-personal';
import Step2Contact from '@/pages/admin/relaties/wizard/step-2-contact';
import Step3Membership from '@/pages/admin/relaties/wizard/step-3-membership';
import Step4Education from '@/pages/admin/relaties/wizard/step-4-education';
import Step5Summary from '@/pages/admin/relaties/wizard/step-5-summary';
import type { EmailEntry, Onderdeel, RelatieCreateFormData, RelatieType } from '@/types/admin';

type Props = {
    relatieTypes: RelatieType[];
    nextRelatieNummer: number;
    onderdelen: Onderdeel[];
    preselectedTypeId: number | null;
};

const TOTAL_STEPS = 5;

// Map error key prefixes to the step they belong to
const errorStepMap: [RegExp, number][] = [
    [/^(relatie_nummer|voornaam|tussenvoegsel|achternaam|geslacht|geboortedatum|geboorteplaats|nationaliteit|types\.)/, 1],
    [/^(adressen\.|emails|telefoons\.|giro_gegevens\.)/, 2],
    [/^(lidmaatschappen\.|onderdelen\.)/, 3],
    [/^(opleidingen\.)/, 4],
];

function findStepForErrors(errors: Record<string, string>): number | null {
    const keys = Object.keys(errors);
    for (const key of keys) {
        for (const [pattern, step] of errorStepMap) {
            if (pattern.test(key)) return step;
        }
    }
    return null;
}

const today = () => new Date().toISOString().split('T')[0];

function emptyEmail(): EmailEntry {
    return { email: '' };
}

function createInitialData(nextRelatieNummer: number, preselectedTypeId: number | null): RelatieCreateFormData {
    const types = preselectedTypeId
        ? [{ type_id: preselectedTypeId.toString(), van: today(), tot: '', functie: '', email: '', onderdeel_id: '' }]
        : [];

    return {
        relatie_nummer: nextRelatieNummer,
        voornaam: '',
        tussenvoegsel: '',
        achternaam: '',
        geslacht: 'O',
        geboortedatum: '',
        geboorteplaats: '',
        nationaliteit: 'Nederlandse',
        types,
        adressen: [],
        emails: [emptyEmail()],
        telefoons: [],
        giro_gegevens: [],
        lidmaatschappen: [],
        onderdelen: [],
        opleidingen: [],
    };
}

export default function RelatieCreate({ relatieTypes, nextRelatieNummer, onderdelen, preselectedTypeId }: Props) {
    const { t } = useTranslation();
    const [currentStep, setCurrentStep] = useState(1);
    const [data, setDataState] = useState<RelatieCreateFormData>(() => createInitialData(nextRelatieNummer, preselectedTypeId));
    const [errors, setErrors] = useState<Partial<Record<string, string>>>({});
    const [processing, setProcessing] = useState(false);

    const setData = <K extends keyof RelatieCreateFormData>(key: K, value: RelatieCreateFormData[K]) => {
        setDataState((prev) => ({ ...prev, [key]: value }));
    };

    const steps: WizardStep[] = [
        { label: t('Personal info & type') },
        { label: t('Contact') },
        { label: t('Membership & sections') },
        { label: t('Training') },
        { label: t('Summary') },
    ];

    const canGoNext = () => {
        if (currentStep === 1) {
            return data.voornaam.trim() !== '' && data.achternaam.trim() !== '';
        }
        if (currentStep === 2) {
            return data.emails.length > 0 && data.emails[0].email.trim() !== '';
        }
        return true;
    };

    const goNext = () => {
        if (currentStep < TOTAL_STEPS) {
            setCurrentStep(currentStep + 1);
        }
    };

    const goBack = () => {
        if (currentStep > 1) {
            setCurrentStep(currentStep - 1);
        }
    };

    const handleSubmit = () => {
        setProcessing(true);
        setErrors({});

        router.post('/admin/relaties', data as any, {
            onError: (responseErrors) => {
                setErrors(responseErrors);
                setProcessing(false);
                const errorStep = findStepForErrors(responseErrors);
                if (errorStep) {
                    setCurrentStep(errorStep);
                }
            },
            onSuccess: () => {
                setProcessing(false);
            },
        });
    };

    const isSkippableStep = currentStep >= 3 && currentStep <= 4;

    return (
        <AppLayout>
            <Head title={t('New relation')} />
            <div className="mx-auto max-w-4xl space-y-6 p-4">
                <WizardStepIndicator
                    steps={steps}
                    currentStep={currentStep}
                    onStepClick={setCurrentStep}
                />

                <div className="min-h-[400px]">
                    {currentStep === 1 && (
                        <Step1Personal data={data} setData={setData} errors={errors} relatieTypes={relatieTypes} onderdelen={onderdelen} />
                    )}
                    {currentStep === 2 && (
                        <Step2Contact data={data} setData={setData} errors={errors} />
                    )}
                    {currentStep === 3 && (
                        <Step3Membership data={data} setData={setData} errors={errors} onderdelen={onderdelen} />
                    )}
                    {currentStep === 4 && (
                        <Step4Education data={data} setData={setData} errors={errors} />
                    )}
                    {currentStep === 5 && (
                        <Step5Summary data={data} relatieTypes={relatieTypes} onderdelen={onderdelen} onNavigateToStep={setCurrentStep} />
                    )}
                </div>

                <div className="flex items-center justify-between border-t pt-4">
                    <Button type="button" variant="outline" onClick={() => router.get('/admin/relaties')}>
                        {t('Cancel')}
                    </Button>

                    <div className="flex gap-2">
                        {currentStep > 1 && (
                            <Button type="button" variant="outline" onClick={goBack}>
                                {t('Previous')}
                            </Button>
                        )}

                        {currentStep < TOTAL_STEPS && (
                            <>
                                {isSkippableStep && (
                                    <Button type="button" variant="ghost" onClick={goNext}>
                                        {t('Skip')}
                                    </Button>
                                )}
                                <Button type="button" onClick={goNext} disabled={!canGoNext()}>
                                    {t('Next')}
                                </Button>
                            </>
                        )}

                        {currentStep === TOTAL_STEPS && (
                            <Button type="button" onClick={handleSubmit} disabled={processing}>
                                {t('Save')}
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
