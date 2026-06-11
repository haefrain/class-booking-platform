<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';
import { formatSessionDay, formatTimeRange } from '@/lib/date';
import type { SessionSummary } from '@/types/booking';

const props = defineProps<{
    booking: {
        id: number;
        status: string;
        source: string;
        price_cents: number;
    };
    session: SessionSummary;
}>();

// The redirect back from Checkout never confirms by itself — only the
// webhook/reconciliation does. Poll briefly while the booking is pending.
let timer: ReturnType<typeof setInterval> | undefined;
let polls = 0;

onMounted(() => {
    if (props.booking.status !== 'pending_payment') {
        return;
    }

    timer = setInterval(() => {
        polls += 1;
        if (polls > 20) {
            clearInterval(timer);

            return;
        }
        router.reload({ only: ['booking'] });
    }, 3000);
});

onUnmounted(() => clearInterval(timer));
</script>

<template>
    <Head title="Booking confirmation" />

    <div class="min-h-screen bg-background text-foreground">
        <main class="mx-auto max-w-xl px-6 py-16 text-center">
            <template v-if="booking.status === 'confirmed'">
                <p class="text-5xl">🎉</p>
                <h1 class="mt-4 text-2xl font-semibold">You're booked!</h1>
            </template>
            <template v-else-if="booking.status === 'pending_payment'">
                <p class="text-5xl">⏳</p>
                <h1 class="mt-4 text-2xl font-semibold">
                    Confirming your payment…
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    This page refreshes automatically. If it takes more than a
                    minute, your bank may still be processing — we hold your
                    seat until the payment deadline.
                </p>
            </template>
            <template v-else>
                <h1 class="mt-4 text-2xl font-semibold">
                    Booking {{ booking.status.replace('_', ' ') }}
                </h1>
            </template>

            <p class="mt-3 text-muted-foreground">
                {{ session.class_type?.name }} ·
                {{ formatSessionDay(session.starts_at) }} ·
                {{ formatTimeRange(session.starts_at, session.ends_at) }}
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                with {{ session.instructor?.name }}
            </p>

            <div class="mt-10 flex justify-center gap-4">
                <Link
                    href="/my/bookings"
                    class="rounded-lg bg-primary px-5 py-2.5 font-medium text-primary-foreground hover:opacity-90"
                >
                    My bookings
                </Link>
                <Link
                    href="/catalog"
                    class="rounded-lg border border-border px-5 py-2.5 font-medium hover:bg-muted"
                >
                    Back to catalog
                </Link>
            </div>
        </main>
    </div>
</template>
