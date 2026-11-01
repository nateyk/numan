import { cn } from '@/lib/utils';

interface CompletionScoreProps {
    score: number;
    size?: number;
    strokeWidth?: number;
    className?: string;
}

export default function CompletionScore({
    score,
    size = 48,
    strokeWidth = 4,
    className,
}: CompletionScoreProps) {
    const radius = (size - strokeWidth) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (Math.min(score, 100) / 100) * circumference;

    const color =
        score >= 80
            ? 'text-emerald-500'
            : score >= 40
              ? 'text-amber-500'
              : 'text-primary';

    return (
        <div
            className={cn(
                'relative inline-flex items-center justify-center',
                className,
            )}
            style={{ width: size, height: size }}
        >
            <svg
                width={size}
                height={size}
                className="absolute inset-0 -rotate-90"
                aria-hidden="true"
            >
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={strokeWidth}
                    className="text-border"
                />
                <circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={strokeWidth}
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    strokeLinecap="round"
                    className={color}
                />
            </svg>
            <span className={cn('text-sm font-bold', color)}>{score}%</span>
        </div>
    );
}
