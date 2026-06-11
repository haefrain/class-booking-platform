<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import CapacityBadge from '@/components/booking/CapacityBadge.vue';
import { formatTimeRange, priceLabel } from '@/lib/date';
import type { SessionSummary } from '@/types/booking';

defineProps<{ session: SessionSummary }>();
</script>

<template>
    <Link
        :href="`/sessions/${session.id}`"
        prefetch
        cache-for="30s"
        class="block rounded-xl border border-border p-4 transition hover:border-foreground/30 hover:bg-muted/40"
    >
        <div class="flex items-center justify-between">
            <span class="font-medium">{{ session.class_type?.name }}</span>
            <span class="text-sm text-muted-foreground">
                {{ priceLabel(session.class_type?.price_cents ?? 0) }}
            </span>
        </div>
        <p class="mt-1 text-sm text-muted-foreground">
            {{ formatTimeRange(session.starts_at, session.ends_at) }}
            · {{ session.instructor?.name }}
        </p>
        <p class="mt-2">
            <CapacityBadge
                :spots-left="session.spots_left"
                :capacity="session.capacity"
            />
        </p>
    </Link>
</template>
