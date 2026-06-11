<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { formatSessionDay, formatTimeRange, priceLabel } from '@/lib/date';
import { login } from '@/routes';
import type { SessionSummary } from '@/types/booking';

const props = defineProps<{
    session: SessionSummary;
    viewer: {
        cta: string;
        booking_id: number | null;
        waitlist_entry_id: number | null;
        cancellable_until: string | null;
    };
}>();

const page = usePage();
const busy = ref(false);

function submit(
    method: 'post' | 'delete',
    url: string,
    data: Record<string, string> = {},
) {
    busy.value = true;
    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        onFinish: () => (busy.value = false),
    });
}

function book() {
    submit('post', `/sessions/${props.session.id}/bookings`, {
        idempotency_key: crypto.randomUUID(),
    });
}

function cancelBooking() {
    if (
        window.confirm('Cancel your booking? Your seat goes to the waitlist.')
    ) {
        submit('delete', `/bookings/${props.viewer.booking_id}`);
    }
}
</script>

<template>
    <Head :title="session.class_type?.name ?? 'Session'" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <Link
                href="/catalog"
                class="text-sm underline-offset-4 hover:underline"
            >
                ‹ Catalog
            </Link>
            <span class="text-lg font-semibold">{{ page.props.name }}</span>
        </header>

        <main class="mx-auto max-w-2xl px-6 py-12">
            <p
                v-if="$page.props.stripeTestMode"
                class="mb-6 rounded-md border border-amber-400/50 bg-amber-400/10 px-3 py-2 text-xs text-amber-700"
            >
                Stripe test mode — use card 4242 4242 4242 4242.
            </p>
            <p class="text-sm tracking-wide text-muted-foreground uppercase">
                {{ formatSessionDay(session.starts_at) }}
            </p>
            <h1 class="mt-1 text-3xl font-semibold">
                {{ session.class_type?.name }}
            </h1>
            <p class="mt-2 text-muted-foreground">
                {{ formatTimeRange(session.starts_at, session.ends_at) }}
                · {{ session.instructor?.name }} ·
                {{ priceLabel(session.class_type?.price_cents ?? 0) }}
            </p>

            <p
                class="mt-6 text-sm"
                :class="session.spots_left === 0 ? 'text-destructive' : ''"
            >
                {{ session.spots_left }} of {{ session.capacity }} spots left
            </p>

            <p
                v-if="$page.props.errors.domain"
                class="mt-4 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            >
                {{ $page.props.errors.domain }}
            </p>

            <div class="mt-8 flex items-center gap-3">
                <Link
                    v-if="viewer.cta === 'login'"
                    :href="login()"
                    class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-medium text-primary-foreground hover:opacity-90"
                >
                    Log in to book
                </Link>

                <button
                    v-else-if="viewer.cta === 'book'"
                    type="button"
                    class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    :disabled="busy"
                    :aria-busy="busy"
                    @click="book"
                >
                    Book this class
                </button>

                <button
                    v-else-if="viewer.cta === 'pay'"
                    type="button"
                    class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    :disabled="busy"
                    @click="
                        submit('post', `/bookings/${viewer.booking_id}/pay`)
                    "
                >
                    Complete payment
                </button>

                <button
                    v-else-if="viewer.cta === 'join_waitlist'"
                    type="button"
                    class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
                    :disabled="busy"
                    @click="submit('post', `/sessions/${session.id}/waitlist`)"
                >
                    Join the waitlist
                </button>

                <button
                    v-else-if="viewer.cta === 'leave_waitlist'"
                    type="button"
                    class="inline-flex rounded-lg border border-border px-5 py-2.5 font-medium hover:bg-muted disabled:opacity-50"
                    :disabled="busy"
                    @click="
                        submit(
                            'delete',
                            `/waitlist/${viewer.waitlist_entry_id}`,
                        )
                    "
                >
                    Leave the waitlist
                </button>

                <button
                    v-else-if="viewer.cta === 'cancel'"
                    type="button"
                    class="inline-flex rounded-lg border border-destructive px-5 py-2.5 font-medium text-destructive hover:bg-destructive/10 disabled:opacity-50"
                    :disabled="busy"
                    @click="cancelBooking"
                >
                    Cancel my booking
                </button>

                <span
                    v-else
                    class="inline-flex rounded-lg border border-border px-5 py-2.5 text-muted-foreground"
                >
                    Booking unavailable
                </span>
            </div>
        </main>
    </div>
</template>
