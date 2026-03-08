import { Check } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';

export type WizardStep = {
    label: string;
};

type Props = {
    steps: WizardStep[];
    currentStep: number;
    onStepClick: (step: number) => void;
};

export function WizardStepIndicator({ steps, currentStep, onStepClick }: Props) {
    const { t } = useTranslation();

    return (
        <div className="w-full">
            <p className="text-muted-foreground mb-4 text-sm">
                {t('Step :current of :total', { current: currentStep, total: steps.length })}
            </p>
            <nav aria-label="Progress">
                <ol className="flex items-center">
                    {steps.map((step, index) => {
                        const stepNumber = index + 1;
                        const isCompleted = stepNumber < currentStep;
                        const isCurrent = stepNumber === currentStep;
                        const isClickable = stepNumber <= currentStep;

                        return (
                            <li key={index} className={`relative ${index < steps.length - 1 ? 'flex-1 pr-4' : ''}`}>
                                <div className="flex items-center">
                                    <button
                                        type="button"
                                        onClick={() => isClickable && onStepClick(stepNumber)}
                                        disabled={!isClickable}
                                        className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-medium transition-colors ${
                                            isCompleted
                                                ? 'bg-primary text-primary-foreground cursor-pointer hover:bg-primary/80'
                                                : isCurrent
                                                  ? 'border-primary text-primary border-2 cursor-default'
                                                  : 'border-muted-foreground/30 text-muted-foreground border-2 cursor-default'
                                        }`}
                                    >
                                        {isCompleted ? <Check className="h-4 w-4" /> : stepNumber}
                                    </button>
                                    {index < steps.length - 1 && (
                                        <div
                                            className={`ml-2 h-0.5 w-full ${
                                                isCompleted ? 'bg-primary' : 'bg-muted-foreground/30'
                                            }`}
                                        />
                                    )}
                                </div>
                                <span
                                    className={`mt-1 hidden text-xs sm:block ${
                                        isCurrent ? 'text-primary font-medium' : isCompleted ? 'text-foreground' : 'text-muted-foreground'
                                    }`}
                                >
                                    {step.label}
                                </span>
                            </li>
                        );
                    })}
                </ol>
            </nav>
        </div>
    );
}
