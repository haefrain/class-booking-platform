<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { formatSessionDay, formatTimeRange } from '@/lib/date';
import type { SessionSummary } from '@/types/booking';

defineProps<{
    session: SessionSummary;
    roster: {
        id: number;
        name: string | null;
        status: string;
        source: string;
    }[];
    waitlist: { id: number; name: string | null }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'My sessions', href: '/instructor/sessions' },
            { title: 'Roster', href: '' },
        ],
    },
});
</script>

<template>
    <Head title="Roster" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <div>
            <h1 class="text-xl font-semibold">
                {{ session.class_type?.name }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ formatSessionDay(session.starts_at) }} ·
                {{ formatTimeRange(session.starts_at, session.ends_at) }} ·
                {{ session.capacity - session.spots_left }}/{{
                    session.capacity
                }}
                booked
            </p>
        </div>

        <section>
            <h2
                class="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Attendees
            </h2>
            <p v-if="roster.length === 0" class="text-sm text-muted-foreground">
                No bookings yet.
            </p>
            <table v-else class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-muted-foreground">
                        <th class="py-2 pr-4 font-medium">Name</th>
                        <th class="py-2 pr-4 font-medium">Status</th>
                        <th class="py-2 font-medium">Source</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in roster"
                        :key="row.id"
                        class="border-b border-border/60"
                    >
                        <td class="py-2 pr-4">{{ row.name }}</td>
                        <td class="py-2 pr-4 capitalize">
                            {{ row.status.replace('_', ' ') }}
                        </td>
                        <td class="py-2 capitalize">{{ row.source }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section>
            <h2
                class="mb-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Waitlist ({{ waitlist.length }})
            </h2>
            <ol v-if="waitlist.length" class="list-decimal pl-5 text-sm">
                <li v-for="entry in waitlist" :key="entry.id" class="py-0.5">
                    {{ entry.name }}
                </li>
            </ol>
            <p v-else class="text-sm text-muted-foreground">Empty.</p>
        </section>

        <Link
            href="/instructor/sessions"
            class="text-sm underline-offset-4 hover:underline"
        >
            ‹ Back to my sessions
        </Link>
    </div>
</template>
