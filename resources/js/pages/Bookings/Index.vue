<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { formatSessionDay, formatTimeRange } from '@/lib/date';
import type { SessionSummary } from '@/types/booking';

type BookingRow = {
    id: number;
    status: string;
    source: string;
    cancellation_kind: string | null;
    session: SessionSummary;
};

defineProps<{
    tab: string;
    bookings: BookingRow[];
    waitlist: { id: number; session: SessionSummary }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My bookings', href: '/my/bookings' }],
    },
});

const TABS = ['upcoming', 'past', 'waitlist'];

function cancelBooking(id: number) {
    if (window.confirm('Cancel this booking?')) {
        router.delete(`/bookings/${id}`);
    }
}
</script>

<template>
    <Head title="My bookings" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">My bookings</h1>

        <nav class="flex gap-2" aria-label="Booking tabs">
            <Link
                v-for="name in TABS"
                :key="name"
                :href="`/my/bookings?tab=${name}`"
                class="rounded-md px-3 py-1.5 text-sm capitalize"
                :class="
                    tab === name
                        ? 'bg-primary text-primary-foreground'
                        : 'border border-border hover:bg-muted'
                "
            >
                {{ name }}
            </Link>
        </nav>

        <template v-if="tab === 'waitlist'">
            <p
                v-if="waitlist.length === 0"
                class="rounded-lg border border-dashed border-border p-8 text-center text-muted-foreground"
            >
                You're not waiting on any class.
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="entry in waitlist"
                    :key="entry.id"
                    class="flex items-center justify-between rounded-xl border border-border p-4"
                >
                    <div>
                        <p class="font-medium">
                            {{ entry.session.class_type?.name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatSessionDay(entry.session.starts_at) }} ·
                            {{
                                formatTimeRange(
                                    entry.session.starts_at,
                                    entry.session.ends_at,
                                )
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-sm text-destructive underline-offset-4 hover:underline"
                        @click="router.delete(`/waitlist/${entry.id}`)"
                    >
                        Leave
                    </button>
                </li>
            </ul>
        </template>

        <template v-else>
            <p
                v-if="bookings.length === 0"
                class="rounded-lg border border-dashed border-border p-8 text-center text-muted-foreground"
            >
                Nothing here yet —
                <Link href="/catalog" class="underline">browse the catalog</Link
                >.
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="booking in bookings"
                    :key="booking.id"
                    class="flex items-center justify-between rounded-xl border border-border p-4"
                >
                    <div>
                        <p class="font-medium">
                            {{ booking.session.class_type?.name }}
                            <span
                                v-if="booking.source === 'waitlist'"
                                class="ml-2 rounded bg-muted px-1.5 py-0.5 text-xs"
                                >from waitlist</span
                            >
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatSessionDay(booking.session.starts_at) }} ·
                            {{
                                formatTimeRange(
                                    booking.session.starts_at,
                                    booking.session.ends_at,
                                )
                            }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-muted-foreground capitalize">
                            {{ booking.status.replace('_', ' ') }}
                        </span>
                        <button
                            v-if="
                                tab === 'upcoming' &&
                                booking.status === 'confirmed'
                            "
                            type="button"
                            class="text-sm text-destructive underline-offset-4 hover:underline"
                            @click="cancelBooking(booking.id)"
                        >
                            Cancel
                        </button>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>
