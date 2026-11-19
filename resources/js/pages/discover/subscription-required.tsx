import {
    DiscoveryFooter,
    DiscoveryHeader,
} from '@/components/freight/discovery-chrome';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, LockKeyhole } from 'lucide-react';

/**
 * Intentionally receives no listing data, filters, counts or pagination.
 * The server enforces the access decision before running the freight query.
 */
export default function FreightSubscriptionRequired() {
    return (
        <>
            <Head title="Freight access paused — Numan Freight" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <DiscoveryHeader />
                <main
                    id="main-content"
                    className="flex flex-1 items-center justify-center px-4 py-12 sm:px-6"
                >
                    <section
                        aria-labelledby="subscription-required-heading"
                        className="w-full max-w-lg"
                    >
                        <div className="mb-5 flex size-12 items-center justify-center rounded-xl border bg-muted/50 text-primary">
                            <LockKeyhole className="size-5" aria-hidden="true" />
                        </div>
                        <p className="mb-2 text-sm font-medium text-muted-foreground">
                            Subscription expired
                        </p>
                        <h1
                            id="subscription-required-heading"
                            className="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            Renew to browse freight
                        </h1>
                        <p className="mt-3 max-w-md text-sm leading-6 text-muted-foreground sm:text-base">
                            Your paid plan has ended. Freight listings, search
                            results, and listing details are unavailable until
                            you renew. Your own freight posts remain available
                            to manage.
                        </p>
                        <div className="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Button asChild className="min-h-11">
                                <Link href="/billing/plans">
                                    View plans
                                    <ArrowRight className="ml-1 size-4" aria-hidden="true" />
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href="/my-freight">Manage my freight</Link>
                            </Button>
                        </div>
                    </section>
                </main>
                <DiscoveryFooter />
            </div>
        </>
    );
}
