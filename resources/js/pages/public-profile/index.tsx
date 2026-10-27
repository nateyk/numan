import { Head } from '@inertiajs/react';

/**
 * Placeholder shell for the public profile management page.
 *
 * The sections (identity, capabilities, transport, verification) are built in
 * the frontend task; this keeps the backend route and its Inertia component
 * name resolvable in the meantime.
 */
export default function PublicProfileIndex() {
    return (
        <>
            <Head title="My profile" />
            <div className="p-4">
                <h1 className="text-2xl font-semibold">My profile</h1>
            </div>
        </>
    );
}
