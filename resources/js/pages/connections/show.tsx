import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';

interface Connection {
    public_id: string;
    freightPost: { ulid: string; user: { name: string } };
    consignor: { name: string };
    transporter: { name: string };
    outcome?: { outcome: string; notes?: string } | null;
}

interface Props {
    connection: Connection;
    contactDetails: { name: string; email?: string; phone?: string } | null;
}

export default function ConnectionShow({ connection, contactDetails }: Props) {
    return (
        <AppLayout>
            <Head title={`Connection ${connection.public_id.slice(0, 8)}`} />
            <div className="mx-auto max-w-2xl space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-bold">Connection</h1>
                    <Link href="/connections"><Button variant="outline" size="sm">Back</Button></Link>
                </div>
                <div className="rounded-md border p-4 space-y-2">
                    <div><span className="text-muted-foreground">Post:</span> {connection.freightPost.ulid}</div>
                    <div><span className="text-muted-foreground">Consignor:</span> {connection.consignor.name}</div>
                    <div><span className="text-muted-foreground">Transporter:</span> {connection.transporter.name}</div>
                    <div><span className="text-muted-foreground">Outcome:</span> {connection.outcome?.outcome ?? 'pending'}</div>
                </div>
                {contactDetails && (
                    <div className="rounded-md border bg-muted/40 p-4 space-y-1">
                        <h3 className="font-semibold">Contact Details</h3>
                        <div>Name: {contactDetails.name}</div>
                        {contactDetails.email && <div>Email: {contactDetails.email}</div>}
                        {contactDetails.phone && <div>Phone: {contactDetails.phone}</div>}
                    </div>
                )}
                {!contactDetails && (
                    <form onSubmit={(e) => { e.preventDefault(); router.post(`/connections/${connection.public_id}/reveal`); }}>
                        <Button>Reveal Contact</Button>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
