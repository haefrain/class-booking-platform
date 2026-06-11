import { describe, expect, it } from 'vitest';
import { formatTimeRange, localDayKey, priceLabel } from '@/lib/date';

// TZ is pinned to America/Bogota (UTC-5) in vitest.config.ts.

describe('localDayKey', () => {
    it('groups by the viewer-local day, not the UTC day', () => {
        // 02:00Z is 21:00 the PREVIOUS day in Bogota.
        expect(localDayKey('2026-06-18T02:00:00Z')).toBe('2026-06-17');
        expect(localDayKey('2026-06-18T14:00:00Z')).toBe('2026-06-18');
    });
});

describe('formatTimeRange', () => {
    it('renders the UTC instant in the viewer timezone', () => {
        const range = formatTimeRange(
            '2026-06-17T14:00:00Z',
            '2026-06-17T15:00:00Z',
        );

        // 14:00Z = 09:00 in Bogota.
        expect(range).toContain('09:00');
        expect(range).toContain('10:00');
        expect(range).toContain('–');
    });
});

describe('priceLabel', () => {
    it('labels zero cents as free', () => {
        expect(priceLabel(0)).toBe('Free');
    });

    it('formats cents as currency', () => {
        expect(priceLabel(1850)).toMatch(/18[.,]50/);
    });
});
