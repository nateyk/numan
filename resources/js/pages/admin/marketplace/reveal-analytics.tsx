import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Day {
    date: string;
    reveals: number;
    blocks: number;
}

interface Summary {
    daily: Day[];
    totals: { reveals: number; blocks: number; days: number };
    conversion: {
        blocked_users: number;
        converted_users: number;
        conversion_rate: number;
        window_days: number;
    };
    ledger_rows: number;
    distinct_pairs: number;
    blocked_rows: number;
    paid_subscribers: number;
}

interface Props {
    summary: Summary;
}

function Stat({ label, value, hint }: { label: string; value: string | number; hint?: string }) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{label}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
            </CardHeader>
            {hint && (
                <CardContent>
                    <p className="text-xs text-muted-foreground">{hint}</p>
                </CardContent>
            )}
        </Card>
    );
}

export default function MarketplaceRevealAnalytics({ summary }: Props) {
    const peak = Math.max(1, ...summary.daily.map((d) => Math.max(d.reveals, d.blocks)));
    const { conversion } = summary;

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <Head title="Contact Reveal Analytics" />

            <div className="flex flex-col gap-1">
                <h1 className="text-2xl font-bold tracking-tight">Contact Reveal Analytics</h1>
                <p className="text-sm text-muted-foreground">
                    Counts only. No counterparty and no contact detail is ever resolved here.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    label={`Reveals (${summary.totals.days}d)`}
                    value={summary.totals.reveals}
                    hint={`${summary.ledger_rows} ledger rows, ${summary.distinct_pairs} distinct pairs`}
                />
                <Stat
                    label={`Blocked attempts (${summary.totals.days}d)`}
                    value={summary.totals.blocks}
                    hint={`${summary.blocked_rows} rows recorded in total`}
                />
                <Stat
                    label={`Subscribed within ${conversion.window_days}d of a block`}
                    value={conversion.converted_users}
                    hint={`of ${conversion.blocked_users} users who hit the wall`}
                />
                <Stat
                    label="Conversion rate"
                    value={`${(conversion.conversion_rate * 100).toFixed(1)}%`}
                    hint={`${summary.paid_subscribers} paid subscribers on the platform`}
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Reveals and blocks per day</CardTitle>
                    <CardDescription>
                        Zero-filled, so a quiet day reads as a zero rather than a gap.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex h-40 items-end gap-[2px]" role="img" aria-label="Daily reveals and blocks">
                        {summary.daily.map((day) => (
                            <div
                                key={day.date}
                                className="group relative flex flex-1 flex-col justify-end gap-[1px]"
                                title={`${day.date}: ${day.reveals} reveals, ${day.blocks} blocks`}
                            >
                                <div
                                    className="rounded-t-sm bg-destructive/70"
                                    style={{ height: `${(day.blocks / peak) * 100}%` }}
                                />
                                <div
                                    className="rounded-b-sm bg-primary/70"
                                    style={{ height: `${(day.reveals / peak) * 100}%` }}
                                />
                            </div>
                        ))}
                    </div>
                    <div className="mt-3 flex items-center gap-4 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span className="size-2 rounded-sm bg-primary/70" /> reveals
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="size-2 rounded-sm bg-destructive/70" /> blocked
                        </span>
                        <span className="ml-auto">
                            {summary.daily[0]?.date} to {summary.daily[summary.daily.length - 1]?.date}
                        </span>
                    </div>
                </CardContent>
            </Card>

            {conversion.blocked_users === 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            No blocked attempts yet
                            <Badge variant="secondary">nothing to convert</Badge>
                        </CardTitle>
                        <CardDescription>
                            Nobody has hit the reveal limit in this window, so the conversion figures above
                            are zero by absence rather than by measurement.
                        </CardDescription>
                    </CardHeader>
                </Card>
            )}
        </div>
    );
}
