import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Day {
    date: string;
    total: number;
    chapa: number;
    manual: number;
}

interface Month {
    month: string;
    total: number;
    chapa: number;
    manual: number;
    count: number;
}

interface Summary {
    totals: {
        total_etb: number;
        by_provider: Record<string, number>;
        by_interval: Record<string, number>;
        verified_count: number;
        pending_count: number;
    };
    daily: Day[];
    monthly: Month[];
    by_plan: Array<{ plan: string; total: number; count: number }>;
}

interface Props {
    summary: Summary;
}

const etb = (n: number) => `${n.toLocaleString()} ETB`;

export default function MarketplaceRevenue({ summary }: Props) {
    const { totals } = summary;
    const peak = Math.max(1, ...summary.daily.map((d) => d.total));

    return (
        <div className="flex flex-col gap-6 p-4 md:p-6">
            <Head title="Marketplace revenue" />

            <div className="space-y-1">
                <h1 className="text-2xl font-bold tracking-tight">Marketplace revenue</h1>
                <p className="text-sm text-muted-foreground">
                    Verified payments only. A pending request is not revenue and grants nothing.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardDescription>Total verified</CardDescription>
                        <CardTitle className="text-3xl tabular-nums">
                            {etb(totals.total_etb)}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-xs text-muted-foreground">
                            {totals.verified_count} verified payment
                            {totals.verified_count === 1 ? '' : 's'}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardDescription>Card / Chapa</CardDescription>
                        <CardTitle className="text-3xl tabular-nums">
                            {etb(totals.by_provider.chapa ?? 0)}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardDescription>Manual transfer</CardDescription>
                        <CardTitle className="text-3xl tabular-nums">
                            {etb(totals.by_provider.manual ?? 0)}
                        </CardTitle>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardDescription>Not yet revenue</CardDescription>
                        <CardTitle className="text-3xl tabular-nums">
                            {totals.pending_count}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-xs text-muted-foreground">
                            pending or awaiting confirmation
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>By duration</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 sm:grid-cols-3">
                    {Object.entries(totals.by_interval).map(([interval, total]) => (
                        <div key={interval} className="rounded-md border p-3">
                            <p className="text-xs capitalize text-muted-foreground">{interval}</p>
                            <p className="tabular-nums text-lg font-semibold">{etb(total)}</p>
                        </div>
                    ))}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Verified revenue per day</CardTitle>
                    <CardDescription>
                        Zero-filled, so a quiet day reads as a zero rather than a gap.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex h-40 items-end gap-[2px]" role="img" aria-label="Daily verified revenue">
                        {summary.daily.map((day) => (
                            <div
                                key={day.date}
                                className="relative flex flex-1 flex-col justify-end"
                                title={`${day.date}: ${etb(day.total)} (chapa ${etb(day.chapa)}, manual ${etb(day.manual)})`}
                            >
                                <div
                                    className="rounded-t-sm bg-primary/70"
                                    style={{ height: `${(day.total / peak) * 100}%` }}
                                />
                            </div>
                        ))}
                    </div>
                    <p className="mt-3 text-xs text-muted-foreground">
                        {summary.daily[0]?.date} to {summary.daily[summary.daily.length - 1]?.date}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>By month</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2">
                    {summary.monthly
                        .slice()
                        .reverse()
                        .map((m) => (
                            <div
                                key={m.month}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-md border p-3 text-sm"
                            >
                                <span className="font-medium">{m.month}</span>
                                <div className="flex items-center gap-3">
                                    {m.count > 0 && (
                                        <span className="text-xs text-muted-foreground">
                                            {m.count} payment{m.count === 1 ? '' : 's'}
                                        </span>
                                    )}
                                    {m.chapa > 0 && (
                                        <Badge variant="outline">
                                            card {etb(m.chapa)}
                                        </Badge>
                                    )}
                                    {m.manual > 0 && (
                                        <Badge variant="outline">
                                            manual {etb(m.manual)}
                                        </Badge>
                                    )}
                                    <span className="tabular-nums font-semibold">
                                        {etb(m.total)}
                                    </span>
                                </div>
                            </div>
                        ))}
                </CardContent>
            </Card>

            {summary.by_plan.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>By plan</CardTitle>
                        <CardDescription>
                            What was sold, at the price that was in force at the time.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        {summary.by_plan.map((row) => (
                            <div
                                key={row.plan}
                                className="flex items-center justify-between rounded-md border p-3 text-sm"
                            >
                                <span className="font-medium">{row.plan}</span>
                                <span className="tabular-nums">
                                    {etb(row.total)} · {row.count} payment
                                    {row.count === 1 ? '' : 's'}
                                </span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
