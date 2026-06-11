<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    destructive?: boolean;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();

const dialog = ref<HTMLDialogElement | null>(null);

// Native <dialog> gives us the focus trap and Escape handling for free.
watch(
    () => props.open,
    async (open) => {
        await nextTick();

        if (open) {
            dialog.value?.showModal();
        } else {
            dialog.value?.close();
        }
    },
);
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-full max-w-md rounded-xl border border-border bg-background p-6 text-foreground shadow-lg backdrop:bg-black/40"
        @cancel.prevent="emit('cancel')"
        @close="emit('cancel')"
    >
        <h2 class="text-lg font-semibold">{{ title }}</h2>
        <p class="mt-2 text-sm text-muted-foreground">{{ description }}</p>

        <slot />

        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                class="rounded-lg border border-border px-4 py-2 text-sm hover:bg-muted"
                @click="emit('cancel')"
            >
                Keep it
            </button>
            <button
                type="button"
                class="rounded-lg px-4 py-2 text-sm font-medium"
                :class="
                    destructive
                        ? 'bg-destructive text-white hover:opacity-90'
                        : 'bg-primary text-primary-foreground hover:opacity-90'
                "
                @click="emit('confirm')"
            >
                {{ confirmLabel ?? 'Confirm' }}
            </button>
        </div>
    </dialog>
</template>
