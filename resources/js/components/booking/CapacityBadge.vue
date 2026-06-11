<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    spotsLeft: number;
    capacity: number;
}>();

// Text + colour, never colour alone (a11y).
const tone = computed(() => {
    if (props.spotsLeft === 0) {
        return {
            label: 'Full',
            class: 'border-destructive/40 bg-destructive/10 text-destructive',
        };
    }

    if (props.spotsLeft <= Math.max(1, Math.floor(props.capacity * 0.2))) {
        return {
            label: `${props.spotsLeft} left`,
            class: 'border-amber-400/50 bg-amber-400/10 text-amber-700',
        };
    }

    return {
        label: `${props.spotsLeft} of ${props.capacity} free`,
        class: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700',
    };
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium tabular-nums"
        :class="tone.class"
    >
        {{ tone.label }}
    </span>
</template>
