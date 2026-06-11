<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    booked: number;
    capacity: number;
}>();

const percent = computed(() =>
    props.capacity === 0
        ? 0
        : Math.round((props.booked / props.capacity) * 100),
);
</script>

<template>
    <div class="flex items-center gap-2">
        <div
            class="h-2 w-28 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="props.booked"
            :aria-valuemin="0"
            :aria-valuemax="props.capacity"
            :aria-label="`${props.booked} of ${props.capacity} seats booked`"
        >
            <div
                class="h-full rounded-full transition-all"
                :class="percent >= 100 ? 'bg-destructive' : 'bg-primary'"
                :style="{ width: `${Math.min(100, percent)}%` }"
            />
        </div>
        <span class="text-xs text-muted-foreground tabular-nums">
            {{ props.booked }}/{{ props.capacity }}
        </span>
    </div>
</template>
