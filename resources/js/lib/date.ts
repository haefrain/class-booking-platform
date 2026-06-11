/**
 * All wire times are ISO-8601 UTC; rendering happens in the viewer's
 * timezone via native Intl — no date library.
 */

export function formatSessionDay(isoUtc: string): string {
    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date(isoUtc));
}

export function formatTime(isoUtc: string): string {
    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(isoUtc));
}

export function formatTimeRange(
    startIsoUtc: string,
    endIsoUtc: string,
): string {
    return `${formatTime(startIsoUtc)} – ${formatTime(endIsoUtc)}`;
}

/** Local YYYY-MM-DD key, for grouping sessions by viewer-local day. */
export function localDayKey(isoUtc: string): string {
    const d = new Date(isoUtc);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export function priceLabel(priceCents: number, currency = 'USD'): string {
    if (priceCents === 0) {
        return 'Free';
    }

    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(priceCents / 100);
}
