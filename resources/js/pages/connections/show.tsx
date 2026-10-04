import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface Connection {
    public_id: string;
    status: string;
    created_at: string | null;
    freight_post: {
        ulid: string;
        cargo_description: string | null;
        status: string;
        origin: string | null;
        destination: string | null;
    } | null;
    consignor: { id: number; display_name: string } | null;
    transporter: { id: number; display_name: string } | null;
    outcome?: { outcome: string; notes?: string } | null;
}

interface ContactDetails {
    name: string;
    phone: string | null;
    email: string | null;
    whatsapp: string | null;
}

interface Counterparty {
    id: number;
    display_name: string;
    is_verified: boolean;
}

interface Props {
    connection: Connection;
    canReveal: boolean;
    alreadyRevealed: boolean;
    counterparty: Counterparty | null;
    // Included only once the reveal has already happened, so a later page load
    // shows the contact without another action.
    revealedContacts?: ContactDetails | null;
    // Flash from the reveal action, so a fresh reveal survives the redirect.
    revealed_contact?: ContactDetails | null;
    reveal_blocked?: { limit: number; used: number; window_days: number } | null;
}

export default function ConnectionShow({
    connection,
    canReveal,
    alreadyRevealed,
    counterparty,
    revealedContacts,
    revealed_contact,
    reveal_blocked,
}: Props) {
    const contact = revealedContacts ?? revealed_contact ?? null;

    return (
        <AppLayout>
            <Head title={`Connection ${connection.public_id.slice(0, 8)}`} />
            <div className="mx-auto max-w-2xl space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold">Connection</h1>
                    <Link href="/connections">
                        <Button variant="outline" size="sm">
                            Back
                        </Button>
                    </Link>
                </div>

                <div className="space-y-2 rounded-md border p-4">
                    <div>
                        <span className="text-muted-foreground">Cargo:</span>{' '}
                        {connection.freight_post?.cargo_description ?? '—'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Route:</span>{' '}
                        {connection.freight_post?.origin ?? '—'} → {connection.freight_post?.destination ?? '—'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Consignor:</span>{' '}
                        {connection.consignor?.display_name ?? '—'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Transporter:</span>{' '}
                        {connection.transporter?.display_name ?? '—'}
                    </div>
                    <div>
                        <span className="text-muted-foreground">Outcome:</span>{' '}
                        {connection.outcome?.outcome ?? 'pending'}
                    </div>
                </div>

                {counterparty && (
                    <div className="flex items-center gap-2 rounded-md border p-4">
                        <span className="font-medium">{counterparty.display_name}</span>
                        {counterparty.is_verified && <Badge variant="secondary">Verified</Badge>}
                    </div>
                )}

                {contact ? (
                    <div className="space-y-1 rounded-md border bg-muted/40 p-4">
                        <h3 className="font-semibold">Contact Details</h3>
                        <div>Name: {contact.name}</div>
                        {contact.phone && <div>Phone: {contact.phone}</div>}
                        {contact.whatsapp && <div>WhatsApp: {contact.whatsapp}</div>}
                        {contact.email && <div>Email: {contact.email}</div>}
                    </div>
                ) : reveal_blocked ? (
                    <div className="space-y-3 rounded-md border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                        <p className="text-sm">
                            You have used all {reveal_blocked.limit} contact reveals in the
                            last {reveal_blocked.window_days} days.
                        </p>
                        <Button asChild>
                            <Link href="/billing/plans">Subscribe for unlimited reveals</Link>
                        </Button>
                    </div>
                ) : canReveal ? (
                    <div className="space-y-2 rounded-md border p-4">
                        <p className="text-sm text-muted-foreground">
                            Contact details are released only when you ask for them, so they are never
                            sitting in the page waiting to be read.
                        </p>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.post(`/connections/${connection.public_id}/reveal`, {}, { preserveScroll: true });
                            }}
                        >
                            <Button type="submit">Reveal contact</Button>
                        </form>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">Contact details are not available.</p>
                )}
            </div>
        </AppLayout>
    );
}
