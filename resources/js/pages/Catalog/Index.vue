<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    formatSessionDay,
    formatTimeRange,
    localDayKey,
    priceLabel,
} from '@/lib/date';
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
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <div class="mb-8 flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Class catalog</h1>
                <nav
                    class="flex items-center gap-3 text-sm"
                    aria-label="Week navigation"
                >
                    <Link
                        :href="`/catalog?week=${week.prev}`"
                        class="rounded-md border border-border px-3 py-1.5 hover:bg-muted"
                        >‹ Prev week</Link
                    >
                    <span class="font-medium tabular-nums">{{
                        week.start
                    }}</span>
                    <Link
                        :href="`/catalog?week=${week.next}`"
                        class="rounded-md border border-border px-3 py-1.5 hover:bg-muted"
                        >Next week ›</Link
                    >
                </nav>
            </div>

            <p
                v-if="sessions.length === 0"
                class="rounded-lg border border-dashed border-border p-10 text-center text-muted-foreground"
            >
                No classes scheduled this week.
            </p>

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
                        <Link
                            :href="`/sessions/${session.id}`"
                            class="block rounded-xl border border-border p-4 transition hover:border-foreground/30 hover:bg-muted/40"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{
                                    session.class_type?.name
                                }}</span>
                                <span class="text-sm text-muted-foreground">
                                    {{
                                        priceLabel(
                                            session.class_type?.price_cents ??
                                                0,
                                        )
                                    }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{
                                    formatTimeRange(
                                        session.starts_at,
                                        session.ends_at,
                                    )
                                }}
                                · {{ session.instructor?.name }}
                            </p>
                            <p
                                class="mt-2 text-xs"
                                :class="
                                    session.spots_left === 0
                                        ? 'text-destructive'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{
                                    session.spots_left === 0
                                        ? 'Full — waitlist soon'
                                        : `${session.spots_left} of ${session.capacity} spots left`
                                }}
                            </p>
                        </Link>
                    </li>
                </ul>
            </section>
        </main>
    </div>
</template>
