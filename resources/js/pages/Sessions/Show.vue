<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { formatSessionDay, formatTimeRange, priceLabel } from '@/lib/date';
import { login } from '@/routes';
import type { SessionSummary, ViewerCta } from '@/types/booking';

defineProps<{
    session: SessionSummary;
    viewer: { cta: ViewerCta };
}>();

const page = usePage();
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

            <div class="mt-8">
                <Link
                    v-if="viewer.cta === 'login'"
                    :href="login()"
                    class="inline-flex rounded-lg bg-primary px-5 py-2.5 font-medium text-primary-foreground hover:opacity-90"
                >
                    Log in to book
                </Link>
                <span
                    v-else
                    class="inline-flex rounded-lg border border-border px-5 py-2.5 text-muted-foreground"
                >
                    Booking opens soon
                </span>
            </div>
        </main>
    </div>
</template>
