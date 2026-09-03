import { Link } from '@inertiajs/react';
import { Check, Truck, Package, Calendar, Eye } from 'lucide-react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Separator } from '@/components/ui/separator';

interface Props {
    ulid: string;
    currentStep: number;
    completionScore?: number;
    children: React.ReactNode;
}

const STEPS = [
    { label: 'Route & Schedule', href: (ulid: string) => `/my-freight/${ulid}/route`, icon: Truck, desc: 'Origin, destination & pickup date' },
    { label: 'Cargo & Truck', href: (ulid: string) => `/my-freight/${ulid}/cargo`, icon: Package, desc: 'Cargo type, description & truck' },
    { label: 'Post Details', href: (ulid: string) => `/my-freight/${ulid}/schedule`, icon: Calendar, desc: 'Schedule type & flexibility' },
    { label: 'Review & Submit', href: (ulid: string) => `/my-freight/${ulid}/review`, icon: Eye, desc: 'Review & submit for approval' },
];

export default function FreightWizardShell({ ulid, currentStep, completionScore = 0, children }: Props) {
    const progress = ((currentStep + 1) / STEPS.length) * 100;

    return (
        <div className="mx-auto max-w-6xl space-y-6">
            <Card>
                <CardHeader className="pb-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle className="text-lg">Create Freight Post</CardTitle>
                            <CardDescription>Step {currentStep + 1} of {STEPS.length} — {STEPS[currentStep]?.label}</CardDescription>
                        </div>
                        <Badge variant="outline">{Math.round(progress)}% complete</Badge>
                    </div>
                    <Progress value={progress} className="mt-3 h-1.5" />
                </CardHeader>
                <CardContent className="pt-0">
                    <div className="flex flex-wrap items-center gap-2">
                        {STEPS.map((step, idx) => {
                            const isCompleted = idx < currentStep;
                            const isCurrent = idx === currentStep;
                            const Icon = step.icon;
                            return (
                                <div key={step.label} className="flex items-center gap-2">
                                    <Link
                                        href={step.href(ulid)}
                                        className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors ${isCurrent ? 'border-primary bg-primary text-primary-foreground shadow-sm' : isCompleted ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-900 dark:bg-green-950/30 dark:text-green-400' : 'bg-card text-muted-foreground hover:bg-muted'}`}
                                    >
                                        <span className={`flex size-6 items-center justify-center rounded-full border ${isCurrent ? 'border-primary-foreground/30 bg-white/20' : isCompleted ? 'border-green-300 bg-green-100 dark:border-green-800 dark:bg-green-900/50' : 'border-input bg-background'}`}>
                                            {isCompleted ? <Check className="size-3.5" /> : isCurrent ? <Icon className="size-3.5" /> : <span className="text-xs">{idx + 1}</span>}
                                        </span>
                                        <span className="hidden sm:inline">{step.label}</span>
                                        <span className="sm:hidden">{idx + 1}</span>
                                    </Link>
                                    {idx < STEPS.length - 1 && <span className="hidden sm:inline text-muted-foreground">→</span>}
                                </div>
                            );
                        })}
                    </div>
                    <p className="mt-2 text-xs text-muted-foreground hidden sm:block">{STEPS[currentStep]?.desc}</p>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-6">{children}</CardContent>
            </Card>

            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span className="flex items-center gap-2">
                    <span className="size-2 rounded-full bg-green-500" /> Saved • ULID {ulid.slice(0, 8)}
                </span>
                <span>{completionScore ? `${completionScore}% profile complete` : 'Draft — not yet submitted'}</span>
            </div>
        </div>
    );
}
