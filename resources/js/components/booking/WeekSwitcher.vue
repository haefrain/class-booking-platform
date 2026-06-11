<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { WeekNav } from '@/types/booking';

defineProps<{
    week: WeekNav;
    baseUrl: string;
}>();
</script>

<template>
    <nav class="flex items-center gap-3 text-sm" aria-label="Week navigation">
        <Link
            :href="`${baseUrl}?week=${week.prev}`"
            preserve-scroll
            class="rounded-md border border-border px-3 py-1.5 hover:bg-muted"
        >
            ‹ Prev week
        </Link>
        <span class="font-medium tabular-nums">{{ week.start }}</span>
        <Link
            :href="`${baseUrl}?week=${week.next}`"
            preserve-scroll
            class="rounded-md border border-border px-3 py-1.5 hover:bg-muted"
        >
            Next week ›
        </Link>
    </nav>
</template>
