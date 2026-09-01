import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';

interface Props {
    ulid: string;
    currentStep: number;
    completionScore?: number;
    children: React.ReactNode;
}

const STEPS = [
    { label: 'Route & Schedule', href: (ulid: string) => `/my-freight/${ulid}/route` },
    { label: 'Cargo & Truck', href: (ulid: string) => `/my-freight/${ulid}/cargo` },
    { label: 'Post Details', href: (ulid: string) => `/my-freight/${ulid}/schedule` },
    { label: 'Review & Submit', href: (ulid: string) => `/my-freight/${ulid}/review` },
];

export default function FreightWizardShell({ ulid, currentStep, children }: Props) {
    return (
        <div className="mx-auto max-w-6xl space-y-6">
            <div className="flex items-center gap-2 border-b pb-4">
                {STEPS.map((step, idx) => {
                    const isCompleted = idx < currentStep;
                    const isCurrent = idx === currentStep;
                    return (
                        <div key={step.label} className="flex items-center gap-2">
                            <Link
                                href={step.href(ulid)}
                                className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium ${isCurrent ? 'bg-primary text-primary-foreground' : isCompleted ? 'bg-muted text-muted-foreground' : 'bg-card text-muted-foreground'}`}
                            >
                                {isCompleted ? <Check className="size-3.5" /> : <span className="flex size-3.5 items-center justify-center rounded-full border text-xs">{idx + 1}</span>}
                                {step.label}
                            </Link>
                            {idx < STEPS.length - 1 && <span className="text-muted-foreground">→</span>}
                        </div>
                    );
                })}
            </div>
            <div>{children}</div>
            <div className="flex items-center justify-between border-t pt-4">
                <span className="text-sm text-muted-foreground">Save status: Saved</span>
            </div>
        </div>
    );
}
