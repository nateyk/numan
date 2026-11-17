import { cn } from '@/lib/utils';

/**
 * Numan Freight's existing, approved SVG identity. Never replace the mark
 * with a generic truck icon; this lockup is shared across app navigation.
 */
export default function BrandLockup({
    className,
    iconOnly = false,
}: {
    className?: string;
    iconOnly?: boolean;
}) {
    return (
        <span className={cn('inline-flex min-w-0 items-center gap-2', className)}>
            <span className="flex size-9 shrink-0 items-center justify-center rounded-md border border-border/60 bg-white p-1">
                <img
                    src="/brand/numanfreight-symbol.svg"
                    alt=""
                    aria-hidden="true"
                    className="h-full w-full object-contain"
                />
            </span>
            {!iconOnly && (
                <span className="block h-6 w-[132px] shrink-0">
                    <img
                        src="/brand/numanfreight-wordmark.svg"
                        alt=""
                        aria-hidden="true"
                        className="h-full w-full object-contain dark:hidden"
                    />
                    <img
                        src="/brand/numanfreight-wordmark-white.svg"
                        alt=""
                        aria-hidden="true"
                        className="hidden h-full w-full object-contain dark:block"
                    />
                </span>
            )}
            <span className="sr-only">Numan Freight</span>
        </span>
    );
}
