<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { formatSessionDay, formatTimeRange } from '@/lib/date';
import { sessions } from '@/routes/instructor';
import type { SessionSummary } from '@/types/booking';

defineProps<{ sessions: SessionSummary[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My sessions', href: sessions() }],
    },
});
</script>

<template>
    <Head title="My sessions" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">My sessions</h1>

        <p
            v-if="$props.sessions.length === 0"
            class="rounded-lg border border-dashed border-border p-8 text-center text-muted-foreground"
        >
            No upcoming sessions.
        </p>

        <ul v-else class="space-y-3">
            <li v-for="session in $props.sessions" :key="session.id">
                <Link
                    :href="`/instructor/sessions/${session.id}`"
                    class="flex items-center justify-between rounded-xl border border-border p-4 hover:bg-muted/40"
                >
                    <div>
                        <p class="font-medium">
                            {{ session.class_type?.name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatSessionDay(session.starts_at) }} ·
                            {{
                                formatTimeRange(
                                    session.starts_at,
                                    session.ends_at,
                                )
                            }}
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground tabular-nums">
                        {{ session.capacity - session.spots_left }}/{{
                            session.capacity
                        }}
                    </span>
                </Link>
            </li>
        </ul>
    </div>
</template>
