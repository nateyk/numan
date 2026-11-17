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
