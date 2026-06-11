<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import EmptyState from '@/components/booking/EmptyState.vue';
import SessionCard from '@/components/booking/SessionCard.vue';
import WeekSwitcher from '@/components/booking/WeekSwitcher.vue';
import { formatSessionDay, localDayKey } from '@/lib/date';
import { login } from '@/routes';
import type { SessionSummary, WeekNav } from '@/types/booking';

const props = defineProps<{
    sessions: SessionSummary[];
    week: WeekNav;
}>();

const page = usePage();

const byDay = computed(() => {
    const groups = new Map<string, SessionSummary[]>();

    for (const session of props.sessions) {
        const key = localDayKey(session.starts_at);
        groups.set(key, [...(groups.get(key) ?? []), session]);
    }

    return [...groups.entries()];
});
</script>

<template>
    <Head title="Class catalog" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <span class="text-lg font-semibold">{{ page.props.name }}</span>
            <nav>
                <Link
                    v-if="!page.props.auth.user"
                    :href="login()"
                    class="text-sm font-medium underline-offset-4 hover:underline"
                >
                    Log in
                </Link>
                <Link
                    v-else
                    href="/my/bookings"
                    class="text-sm font-medium underline-offset-4 hover:underline"
                >
                    My bookings
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <div class="mb-8 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Class catalog</h1>
                <WeekSwitcher :week="week" base-url="/catalog" />
            </div>

            <EmptyState v-if="sessions.length === 0">
                No classes scheduled this week.
            </EmptyState>

            <section
                v-for="[day, daySessions] in byDay"
                :key="day"
                class="mb-8"
            >
                <h2
                    class="mb-3 text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ formatSessionDay(daySessions[0].starts_at) }}
                </h2>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <li v-for="session in daySessions" :key="session.id">
                        <SessionCard :session="session" />
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
