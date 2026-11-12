import InputError from '@/components/input-error';
import { Toggle } from '@/components/ui/toggle';
import { cn } from '@/lib/utils';
import { Check, Package, Truck } from 'lucide-react';
import { useState } from 'react';

export interface CapabilityValues {
    posts_freight: boolean;
    provides_transport: boolean;
}

interface Props {
    values: CapabilityValues;
    errors: Record<string, string | undefined>;
    onChange: (
        key: 'posts_freight' | 'provides_transport',
        value: boolean,
    ) => void;
}

/**
 * The two marketplace capabilities.
 *
 * They are not exclusive: a person can post freight, carry it, or both. The
 * only rule is that at least one stays on, because a profile that does neither
 * is a login rather than a marketplace identity. The server enforces the same
 * rule; this guard keeps the page from submitting a request it knows will fail.
 */
export default function CapabilityFields({ values, errors, onChange }: Props) {
    const [blocked, setBlocked] = useState(false);

    const toggle = (key: 'posts_freight' | 'provides_transport') => {
        const next = !values[key];

        if (
            !next &&
            !values[
                key === 'posts_freight' ? 'provides_transport' : 'posts_freight'
            ]
        ) {
            setBlocked(true);

            return;
        }

        setBlocked(false);
        onChange(key, next);
    };

    return (
        <div className="space-y-3">
            <div className="grid gap-3 sm:grid-cols-2">
                <Toggle
                    variant="outline"
                    pressed={values.posts_freight}
                    onPressedChange={() => toggle('posts_freight')}
                    className={cn(
                        'min-h-20 w-full justify-start gap-3 rounded-lg px-3 py-3 text-left whitespace-normal sm:px-4',
                        values.posts_freight &&
                            'border-primary/60 data-[state=on]:bg-primary/10 data-[state=on]:text-foreground',
                    )}
                    data-test="capability-posts-freight"
                >
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-foreground">
                        <Package className="size-5" aria-hidden="true" />
                    </span>
                    <span className="min-w-0 flex-1 space-y-0.5">
                        <span className="block font-semibold">Post freight</span>
                        <span className="block text-xs font-normal leading-relaxed text-muted-foreground">
                            Find transport for your loads
                        </span>
                    </span>
                    <span
                        className={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-full border',
                            values.posts_freight
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-muted-foreground/40',
                        )}
                        aria-hidden="true"
                    >
                        {values.posts_freight && <Check className="size-3" />}
                    </span>
                </Toggle>
                <Toggle
                    variant="outline"
                    pressed={values.provides_transport}
                    onPressedChange={() => toggle('provides_transport')}
                    className={cn(
                        'min-h-20 w-full justify-start gap-3 rounded-lg px-3 py-3 text-left whitespace-normal sm:px-4',
                        values.provides_transport &&
                            'border-primary/60 data-[state=on]:bg-primary/10 data-[state=on]:text-foreground',
                    )}
                    data-test="capability-provides-transport"
                >
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-foreground">
                        <Truck className="size-5" aria-hidden="true" />
                    </span>
                    <span className="min-w-0 flex-1 space-y-0.5">
                        <span className="block font-semibold">Provide transport</span>
                        <span className="block text-xs font-normal leading-relaxed text-muted-foreground">
                            Offer trucks and routes to shippers
                        </span>
                    </span>
                    <span
                        className={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-full border',
                            values.provides_transport
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-muted-foreground/40',
                        )}
                        aria-hidden="true"
                    >
                        {values.provides_transport && <Check className="size-3" />}
                    </span>
                </Toggle>
            </div>

            {blocked && (
                <p className="text-sm text-destructive">
                    Keep at least one capability on. You can turn the other back
                    on at any time.
                </p>
            )}

            <InputError message={errors.capabilities} />
        </div>
    );
}
