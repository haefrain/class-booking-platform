<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import OccupancyBar from '@/components/booking/OccupancyBar.vue';
import { formatSessionDay, formatTime, priceLabel } from '@/lib/date';
import { dashboard } from '@/routes/admin';

defineProps<{
    scheduler: {
        last_heartbeat: string | null;
        healthy: boolean;
    };
    kpis: {
        sessions_next_7d: number;
        confirmed_next_7d: number;
        waiting_now: number;
        collected_cents: number;
    };
    occupancy?: {
        id: number;
        name: string | null;
        starts_at: string;
        booked: number;
        capacity: number;
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Admin dashboard</h1>
            <span
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs"
                :class="
                    scheduler.healthy
                        ? 'border-emerald-500/40 text-emerald-600'
                        : 'border-destructive/40 text-destructive'
                "
                :title="scheduler.last_heartbeat ?? 'never'"
            >
                <span
                    class="size-2 rounded-full"
                    :class="
                        scheduler.healthy ? 'bg-emerald-500' : 'bg-destructive'
                    "
                />
                {{
                    scheduler.healthy
                        ? 'Scheduler running'
                        : 'Scheduler stale — reminders and sweeps are down'
                }}
            </span>
        </div>

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border p-4">
                <dt class="text-sm text-muted-foreground">
                    Sessions · next 7 days
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ kpis.sessions_next_7d }}
                </dd>
            </div>
            <div class="rounded-xl border border-border p-4">
                <dt class="text-sm text-muted-foreground">
                    Confirmed bookings
                </dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ kpis.confirmed_next_7d }}
                </dd>
            </div>
            <div class="rounded-xl border border-border p-4">
                <dt class="text-sm text-muted-foreground">Waiting right now</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ kpis.waiting_now }}
                </dd>
            </div>
            <div class="rounded-xl border border-border p-4">
                <dt class="text-sm text-muted-foreground">Collected</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ priceLabel(kpis.collected_cents) }}
                </dd>
            </div>
        </dl>

        <section>
            <h2
                class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Fill rate · next 10 sessions
            </h2>

            <Deferred data="occupancy">
                <template #fallback>
                    <ul
                        class="space-y-2"
                        aria-busy="true"
                        aria-label="Loading occupancy"
                    >
                        <li
                            v-for="i in 4"
                            :key="i"
                            class="h-10 animate-pulse rounded-lg bg-muted"
                        />
                    </ul>
                </template>

                <ul class="space-y-2">
                    <li
                        v-for="session in occupancy"
                        :key="session.id"
                        class="flex items-center justify-between rounded-lg border border-border px-4 py-2"
                    >
                        <span class="text-sm">
                            {{ session.name }}
                            <span class="text-muted-foreground">
                                · {{ formatSessionDay(session.starts_at) }}
                                {{ formatTime(session.starts_at) }}
                            </span>
                        </span>
                        <OccupancyBar
                            :booked="session.booked"
                            :capacity="session.capacity"
                        />
                    </li>
                </ul>
            </Deferred>
        </section>
    </div>
</template>
