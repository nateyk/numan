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
    { label: 'Route & Schedule', href: (ulid: string) => `/my-freight/${ulid}/route`, desc: 'Origin & destination' },
    { label: 'Cargo & Truck', href: (ulid: string) => `/my-freight/${ulid}/cargo`, desc: 'Cargo type, description & truck' },
    { label: 'Post Details', href: (ulid: string) => `/my-freight/${ulid}/schedule`, desc: 'Pickup date, time & schedule' },
    { label: 'Review & Submit', href: (ulid: string) => `/my-freight/${ulid}/review`, desc: 'Review & submit for approval' },
];

export default function FreightWizardShell({ ulid, currentStep, completionScore = 0, children }: Props) {
    const progress = ((currentStep + 1) / STEPS.length) * 100;

    return (
        <div className="flex h-full flex-1 flex-col gap-4 p-4">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold tracking-tight">Create Freight Post</h1>
                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                    {Math.round(progress)}% <Progress value={progress} className="h-1.5 w-20" />
                </span>
            </div>

            <div className="flex items-center gap-2 overflow-x-auto border-b pb-4">
                {STEPS.map((step, idx) => {
                    const isCompleted = idx < currentStep;
                    const isCurrent = idx === currentStep;
                    return (
                        <div key={step.label} className="flex items-center gap-2">
                            <Link
                                href={step.href(ulid)}
                                className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap ${isCurrent ? 'bg-primary text-primary-foreground' : isCompleted ? 'bg-muted text-muted-foreground' : 'text-muted-foreground hover:bg-muted'}`}
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

            <div className="flex items-center justify-between border-t pt-4 text-xs text-muted-foreground">
                <span className="flex items-center gap-2">
                    <span className="size-2 rounded-full bg-green-500" /> Saved • ULID {ulid.slice(0, 8)}
                </span>
                <span>{completionScore ? `${completionScore}%` : 'Draft'}</span>
            </div>
        </div>
    );
}
