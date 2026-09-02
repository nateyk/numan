import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';
import type { ReactNode } from 'react';

interface CapabilityCardProps {
    icon: ReactNode;
    title: string;
    description: string;
    selected: boolean;
    onToggle: () => void;
    testId?: string;
}

export function CapabilityCard({
    icon,
    title,
    description,
    selected,
    onToggle,
    testId,
}: CapabilityCardProps) {
    return (
        <button
            type="button"
            onClick={onToggle}
            data-test={testId}
            aria-pressed={selected}
            className={cn(
                'relative flex w-full flex-col gap-3 rounded-lg border-2 p-4 text-left transition-colors',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none',
                selected
                    ? 'border-primary bg-primary/5'
                    : 'border-border bg-card hover:border-primary/40 hover:bg-muted/30',
            )}
        >
            <span
                className={cn(
                    'absolute top-3 right-3 flex size-5 items-center justify-center rounded-full border text-[10px] transition-colors',
                    selected
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background text-transparent',
                )}
                aria-hidden="true"
            >
                <Check className="size-3" />
            </span>

            <span
                className={cn(
                    'flex size-9 items-center justify-center rounded-md border',
                    selected
                        ? 'border-primary/20 bg-primary/10 text-primary'
                        : 'border-border bg-muted text-muted-foreground',
                )}
            >
                {icon}
            </span>

            <span className="space-y-1 pr-6">
                <span className="block text-sm leading-none font-semibold">
                    {title}
                </span>
                <span className="block text-xs leading-relaxed text-muted-foreground">
                    {description}
                </span>
            </span>
        </button>
    );
}
