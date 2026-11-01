import CompletionScore from '@/components/transporter/completion-score';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { CheckCircle2, Circle, Clock } from 'lucide-react';
import type { ReactNode } from 'react';

export type ProfileStrengthState = 'done' | 'todo' | 'pending';

export interface ProfileStrengthItem {
    label: string;
    state: ProfileStrengthState;
    href?: string;
}

interface Props {
    score: number | null;
    items: ProfileStrengthItem[];
}

const STATE_ICON: Record<ProfileStrengthState, ReactNode> = {
    done: (
        <CheckCircle2
            className="size-4 text-emerald-600 dark:text-emerald-400"
            aria-hidden="true"
        />
    ),
    todo: (
        <Circle
            className="size-4 text-muted-foreground/60"
            aria-hidden="true"
        />
    ),
    pending: (
        <Clock
            className="size-4 text-amber-600 dark:text-amber-400"
            aria-hidden="true"
        />
    ),
};

/**
 * A saved-state readiness panel for the public profile.
 *
 * It reads the saved profile rather than the draft in the form, so a tick never
 * claims work that has not been persisted. Every row links to the section that
 * fixes it.
 */
export default function ProfileStrength({ score, items }: Props) {
    const done = items.filter((item) => item.state === 'done').length;
    const hasScore = score !== null;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">
                    {hasScore ? 'Profile strength' : 'Profile checklist'}
                </CardTitle>
                <CardDescription>
                    {hasScore
                        ? 'Based on your saved details. It updates after each save.'
                        : 'The details that make your profile useful to other people.'}
                </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4">
                {hasScore && (
                    <div className="flex items-center gap-4">
                        <CompletionScore
                            score={score}
                            size={56}
                            strokeWidth={5}
                        />
                        <div className="min-w-0 space-y-1">
                            <p className="text-sm font-medium">
                                Your saved profile
                            </p>
                            <p className="text-xs leading-relaxed text-muted-foreground">
                                Vehicles, routes, and documents carry most of
                                the score.
                            </p>
                        </div>
                    </div>
                )}

                <ul className="space-y-0.5">
                    {items.map((item) => {
                        const content = (
                            <span className="flex items-start gap-2.5">
                                <span className="mt-0.5 shrink-0">
                                    {STATE_ICON[item.state]}
                                </span>
                                <span
                                    className={cn(
                                        'text-sm leading-5',
                                        item.state === 'done'
                                            ? 'text-muted-foreground'
                                            : 'font-medium',
                                    )}
                                >
                                    <span className="sr-only">
                                        {item.state === 'done'
                                            ? 'Done: '
                                            : item.state === 'pending'
                                              ? 'Pending: '
                                              : 'Not done: '}
                                    </span>
                                    {item.label}
                                </span>
                            </span>
                        );

                        return (
                            <li key={item.label}>
                                {item.href ? (
                                    <a
                                        href={item.href}
                                        className="-mx-2 flex rounded-md px-2 py-1.5 transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {content}
                                    </a>
                                ) : (
                                    <div className="-mx-2 px-2 py-1.5">
                                        {content}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>

                <p className="border-t pt-3 text-xs text-muted-foreground">
                    {done} of {items.length} steps done.
                </p>
            </CardContent>
        </Card>
    );
}
