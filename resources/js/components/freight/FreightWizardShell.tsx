import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Progress } from '@/components/ui/progress';

interface Props {
    ulid: string;
    currentStep: number;
    completionScore?: number;
    children: React.ReactNode;
}

const STEPS = [
    { label: 'Route & Schedule', href: (ulid: string) => `/my-freight/${ulid}/route`, desc: 'Origin, destination & pickup date' },
    { label: 'Cargo & Truck', href: (ulid: string) => `/my-freight/${ulid}/cargo`, desc: 'Cargo type, description & truck' },
    { label: 'Post Details', href: (ulid: string) => `/my-freight/${ulid}/schedule`, desc: 'Schedule type & flexibility' },
    { label: 'Review & Submit', href: (ulid: string) => `/my-freight/${ulid}/review`, desc: 'Review & submit for approval' },
];

export default function FreightWizardShell({ ulid, currentStep, completionScore = 0, children }: Props) {
    const progress = ((currentStep + 1) / STEPS.length) * 100;

    return (
        <div className="flex h-full flex-1 flex-col gap-4 p-4">
            <div className="flex flex-col gap-1">
                <h1 className="text-2xl font-bold tracking-tight">Create Freight Post</h1>
                <p className="text-muted-foreground">Step {currentStep + 1} of {STEPS.length} — {STEPS[currentStep]?.label} • {STEPS[currentStep]?.desc}</p>
            </div>

            <div className="border-b pb-4">
                <div className="flex items-center justify-between gap-2 overflow-x-auto">
                    {STEPS.map((step, idx) => {
                        const isCompleted = idx < currentStep;
                        const isCurrent = idx === currentStep;
                        return (
                            <div key={step.label} className="flex flex-1 items-center gap-2">
                                <Link
                                    href={step.href(ulid)}
                                    className="flex flex-1 items-center gap-3 rounded-lg border p-2 text-left transition-colors hover:bg-muted/50"
                                    style={{
                                        borderColor: isCurrent ? 'hsl(var(--primary))' : undefined,
                                        backgroundColor: isCurrent ? 'hsl(var(--primary) / 0.06)' : isCompleted ? 'hsl(var(--muted))' : undefined,
                                    }}
                                >
                                    <span
                                        className={`flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-medium transition-colors ${isCompleted ? 'border-primary bg-primary text-primary-foreground' : isCurrent ? 'border-primary bg-primary text-primary-foreground shadow-sm' : 'border-muted-foreground/20 bg-background text-muted-foreground'}`}
                                    >
                                        {isCompleted ? <Check className="size-4" /> : idx + 1}
                                    </span>
                                    <span className="hidden min-w-0 flex-1 sm:block">
                                        <span className={`block text-sm font-medium leading-none ${isCurrent ? 'text-foreground' : isCompleted ? 'text-muted-foreground' : 'text-muted-foreground'}`}>{step.label}</span>
                                        <span className="hidden text-xs text-muted-foreground lg:block">{step.desc}</span>
                                    </span>
                                    <span className="sm:hidden text-sm font-medium">{idx + 1}</span>
                                </Link>
                                {idx < STEPS.length - 1 && (
                                    <span className={`hidden h-0.5 w-full max-w-8 shrink-0 rounded-full sm:block ${idx < currentStep ? 'bg-primary' : 'bg-border'}`} />
                                )}
                            </div>
                        );
                    })}
                </div>
                <div className="mt-3 flex items-center justify-between text-xs text-muted-foreground">
                    <span>Step {currentStep + 1} of {STEPS.length}</span>
                    <span className="flex items-center gap-2">
                        {Math.round(progress)}% complete
                        <Progress value={progress} className="h-1.5 w-24" />
                    </span>
                </div>
            </div>

            <div>{children}</div>

            <div className="flex items-center justify-between border-t pt-4 text-xs text-muted-foreground">
                <span className="flex items-center gap-2">
                    <span className="size-2 rounded-full bg-green-500" /> Saved • ULID {ulid.slice(0, 8)}
                </span>
                <span>{completionScore ? `${completionScore}%` : 'Draft'}</span>
            </div>
        </div>
    );
}
