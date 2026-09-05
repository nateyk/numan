import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router } from '@inertiajs/react';
import { CreditCard, Webhook, Receipt } from 'lucide-react';

interface Tx {
    id: number;
    tx_ref: string;
    chapa_ref: string | null;
    workspace: { id: number; name: string } | null;
    plan: { name: string } | null;
    user: { name: string; email: string } | null;
    amount_etb: string;
    currency: string;
    status: string;
    mode: string;
    verified_at: string | null;
    created_at: string;
}

interface Wh {
    id: number;
    event: string | null;
    tx_ref: string;
    status: string;
    processed_at: string | null;
    created_at: string;
}

interface Sub {
    id: number;
    workspace: string | null;
    plan: string | null;
    status: string;
    billing_interval: string | null;
    current_period_ends_at: string | null;
    chapa_tx_ref: string | null;
}

interface Props {
    transactions: { data: Tx[]; links: { url: string | null; label: string; active: boolean }[] };
    webhooks: Wh[];
    subscriptions: Sub[];
}

export default function AdminMarketplacePayments({ transactions, webhooks, subscriptions }: Props) {
    return (
        <AdminLayout>
            <Head title="Marketplace Payments" />
            <div className="flex h-full flex-1 flex-col gap-6 rounded-md border border-sidebar-border/70 p-4 md:p-6 lg:p-8">
                <div>
                    <h2 className="flex items-center gap-2 text-2xl font-bold tracking-tight"><CreditCard className="h-6 w-6 text-primary" /> Marketplace Payments (Chapa ETB)</h2>
                    <p className="text-sm text-muted-foreground">Transactions, webhooks, subscriptions — Chapa ETB only, scope NumanFreight plans.</p>
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="px-5 py-4"><CardTitle className="flex items-center gap-2 text-base"><Receipt className="size-4" /> Recent Transactions</CardTitle></CardHeader>
                    <CardContent className="px-0 pb-0">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>TX Ref</TableHead>
                                        <TableHead>Workspace</TableHead>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead>Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.data.map((t) => (
                                        <TableRow key={t.id}>
                                            <TableCell className="font-mono text-xs">{t.tx_ref.slice(0, 16)}…</TableCell>
                                            <TableCell className="text-xs">{t.workspace?.name ?? '—'}</TableCell>
                                            <TableCell className="text-xs">{t.plan?.name ?? '—'}</TableCell>
                                            <TableCell className="text-xs font-medium">{Number(t.amount_etb).toLocaleString()} {t.currency}</TableCell>
                                            <TableCell><Badge variant={t.status === 'verified' ? 'default' : t.status === 'failed' ? 'destructive' : 'secondary'} className="text-[10px] capitalize">{t.status}</Badge></TableCell>
                                            <TableCell className="text-xs">{new Date(t.created_at).toLocaleString()}</TableCell>
                                            <TableCell>
                                                <Button variant="outline" size="sm" onClick={() => router.post(`/admin/marketplace-payments/${t.id}/reconcile`, {}, { preserveScroll: true })}>
                                                    Reconcile
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    {transactions.data.length === 0 && <TableRow><TableCell colSpan={7} className="py-8 text-center text-sm text-muted-foreground">No transactions yet.</TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="gap-0 py-0">
                        <CardHeader className="px-5 py-4"><CardTitle className="flex items-center gap-2 text-base"><Webhook className="size-4" /> Webhook Events</CardTitle></CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Event</TableHead>
                                        <TableHead>TX</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {webhooks.map((w) => (
                                        <TableRow key={w.id}>
                                            <TableCell className="text-xs">{w.event ?? '—'}</TableCell>
                                            <TableCell className="font-mono text-xs">{w.tx_ref.slice(0, 12)}…</TableCell>
                                            <TableCell><Badge variant={w.status === 'processed' ? 'default' : 'secondary'} className="text-[10px]">{w.status}</Badge></TableCell>
                                        </TableRow>
                                    ))}
                                    {webhooks.length === 0 && <TableRow><TableCell colSpan={3} className="py-6 text-center text-sm text-muted-foreground">No webhooks.</TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <CardHeader className="px-5 py-4"><CardTitle className="text-base">Recent Subscriptions</CardTitle></CardHeader>
                        <CardContent className="px-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Workspace</TableHead>
                                        <TableHead>Plan</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Ends</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {subscriptions.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell className="text-xs">{s.workspace ?? '—'}</TableCell>
                                            <TableCell className="text-xs">{s.plan ?? '—'} <span className="text-muted-foreground">({s.billing_interval ?? '-'})</span></TableCell>
                                            <TableCell><Badge className="text-[10px] capitalize">{s.status}</Badge></TableCell>
                                            <TableCell className="text-xs">{s.current_period_ends_at ? new Date(s.current_period_ends_at).toLocaleDateString() : '—'}</TableCell>
                                        </TableRow>
                                    ))}
                                    {subscriptions.length === 0 && <TableRow><TableCell colSpan={4} className="py-6 text-center text-sm text-muted-foreground">No subscriptions.</TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AdminLayout>
    );
}
