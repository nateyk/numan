import { Head } from '@inertiajs/react';

interface Props {
    freight: {
        data: Array<Record<string, unknown>>;
        links: unknown[];
    };
    filters: Record<string, string>;
}

export default function DiscoverIndex({ freight }: Props) {
    return (
        <>
            <Head title="Discover Freight" />
            <div className="p-6">
                <h1 className="text-2xl font-bold">Discover Freight</h1>
                <p className="text-sm text-muted-foreground">{freight.data.length} results</p>
            </div>
        </>
    );
}
