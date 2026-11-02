import InputError from '@/components/input-error';
import { Toggle } from '@/components/ui/toggle';
import { Package, Truck } from 'lucide-react';
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
                    className="justify-start gap-2 px-3"
                    data-test="capability-posts-freight"
                >
                    <Package className="size-4" />
                    Post freight
                </Toggle>
                <Toggle
                    variant="outline"
                    pressed={values.provides_transport}
                    onPressedChange={() => toggle('provides_transport')}
                    className="justify-start gap-2 px-3"
                    data-test="capability-provides-transport"
                >
                    <Truck className="size-4" />
                    Provide transport
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
