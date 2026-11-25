/** Calendar dates are rendered locally, not parsed as UTC midnight. */
export function formatFreightDate(value: string | null | undefined): string {
    if (!value) return '—';

    const day = new Date(`${value.slice(0, 10)}T12:00:00`);
    if (Number.isNaN(day.getTime())) return '—';

    return day.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatPostedDate(value: string | null | undefined): string {
    if (!value) return '—';

    const timestamp = new Date(value);
    return Number.isNaN(timestamp.getTime())
        ? '—'
        : timestamp.toLocaleDateString(undefined, {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          });
}

/** The last advertised day is pickup day plus extra pickup days. */
export function pickupWindowEndsOn(
    pickupDate: string,
    extraDays: number,
): string | null {
    if (!pickupDate) return null;

    const date = new Date(`${pickupDate.slice(0, 10)}T12:00:00`);
    if (Number.isNaN(date.getTime())) return null;

    date.setDate(date.getDate() + Math.max(0, extraDays));
    return date.toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

type FreightLifecycleDates = {
    status: string;
    created_at?: string | null;
    published_at?: string | null;
    expires_on?: string | null;
    closed_at?: string | null;
    arrived_at?: string | null;
    closure_reason?: string | null;
};

/** Draft creation must never be presented as the approval/publication date. */
export function freightPublicationLabel(post: FreightLifecycleDates): string {
    return post.published_at
        ? `Posted ${formatPostedDate(post.published_at)}`
        : `Created ${formatPostedDate(post.created_at)}`;
}

/** A closed or delivered load no longer has an upcoming listing expiry. */
export function freightLifecycleLabel(post: FreightLifecycleDates): string {
    if (post.arrived_at) {
        return `Arrived ${formatPostedDate(post.arrived_at)}`;
    }

    if (post.status === 'closed') {
        const label =
            post.closure_reason === 'cancelled' ? 'Cancelled' : 'Closed';
        const day = formatPostedDate(post.closed_at);
        return day === '—' ? label : `${label} ${day}`;
    }

    if (post.status === 'expired') {
        return `Expired ${formatFreightDate(post.expires_on)}`;
    }

    if (post.status === 'active') {
        return `Expires ${formatFreightDate(post.expires_on)}`;
    }

    return `Pickup window ends ${formatFreightDate(post.expires_on)}`;
}
