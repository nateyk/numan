import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

export type OnboardingStepKey = 'capabilities' | 'account_type' | 'details';

interface StepperProps {
    currentStep: OnboardingStepKey | 1 | 2 | 3;
}

const STEPS: { key: OnboardingStepKey; label: string; shortLabel: string }[] = [
    { key: 'capabilities', label: 'Your use', shortLabel: 'Use' },
    { key: 'account_type', label: 'Account', shortLabel: 'Account' },
    { key: 'details', label: 'Details', shortLabel: 'Details' },
];

function stepToIndex(step: StepperProps['currentStep']): number {
    if (typeof step === 'number') return step;
    const map: Record<OnboardingStepKey, number> = {
        capabilities: 1,
        account_type: 2,
        details: 3,
    };
    return map[step] ?? 1;
}

export function Stepper({ currentStep }: StepperProps) {
    const current = stepToIndex(currentStep);

    return (
        <nav aria-label="Onboarding progress" className="w-full">
            <ol className="flex items-center justify-between gap-1 sm:gap-2">
                {STEPS.map((s, idx) => {
                    const stepNumber = idx + 1;
                    const isCompleted = current > stepNumber;
                    const isCurrent = current === stepNumber;
                    const isPending = current < stepNumber;

                    return (
                        <li
                            key={s.key}
                            className="flex flex-1 items-center gap-1 sm:gap-2"
                        >
                            <div className="flex flex-col items-center gap-1.5 sm:flex-row sm:gap-2">
                                <span
                                    className={cn(
                                        'flex size-7 items-center justify-center rounded-full border text-xs font-semibold transition-colors sm:size-8 sm:text-sm',
                                        isCompleted &&
                                            'border-primary bg-primary text-primary-foreground',
                                        isCurrent &&
                                            'border-primary bg-primary text-primary-foreground shadow-sm',
                                        isPending &&
                                            'border-border bg-muted text-muted-foreground',
                                    )}
                                    aria-current={
                                        isCurrent ? 'step' : undefined
                                    }
                                >
                                    {isCompleted ? (
                                        <Check className="size-3.5 sm:size-4" />
                                    ) : (
                                        stepNumber
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-center text-[11px] leading-none font-medium sm:text-xs',
                                        isCurrent && 'text-foreground',
                                        isCompleted && 'text-foreground',
                                        isPending && 'text-muted-foreground',
                                    )}
                                >
                                    <span className="hidden sm:inline">{`${stepNumber}. ${s.label}`}</span>
                                    <span className="sm:hidden">
                                        {s.shortLabel}
                                    </span>
                                </span>
                            </div>
                            {idx < STEPS.length - 1 && (
                                <div
                                    className={cn(
                                        'mx-1 h-px flex-1 sm:mx-2',
                                        current > stepNumber
                                            ? 'bg-primary'
                                            : 'bg-border',
                                    )}
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
