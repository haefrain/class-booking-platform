<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

export type ClassTypeFormData = {
    name: string;
    description: string | null;
    duration_minutes: number;
    default_capacity: number;
    price_cents: number;
    cancellation_deadline_hours: number;
    is_active: boolean;
};

const props = defineProps<{
    initial?: Partial<ClassTypeFormData>;
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
}>();

const form = useForm<ClassTypeFormData>({
    name: props.initial?.name ?? '',
    description: props.initial?.description ?? null,
    duration_minutes: props.initial?.duration_minutes ?? 60,
    default_capacity: props.initial?.default_capacity ?? 10,
    price_cents: props.initial?.price_cents ?? 0,
    cancellation_deadline_hours:
        props.initial?.cancellation_deadline_hours ?? 24,
    is_active: props.initial?.is_active ?? true,
});

function submit() {
    form.submit(props.method, props.action, { preserveScroll: true });
}
</script>

<template>
    <form class="max-w-xl space-y-5" @submit.prevent="submit">
        <div>
            <label class="mb-1 block text-sm font-medium" for="name"
                >Name</label
            >
            <input
                id="name"
                v-model="form.name"
                type="text"
                class="w-full rounded-md border border-border bg-background px-3 py-2"
                required
            />
            <p v-if="form.errors.name" class="mt-1 text-sm text-destructive">
                {{ form.errors.name }}
            </p>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium" for="description"
                >Description</label
            >
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full rounded-md border border-border bg-background px-3 py-2"
            />
            <p
                v-if="form.errors.description"
                class="mt-1 text-sm text-destructive"
            >
                {{ form.errors.description }}
            </p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium" for="duration"
                    >Duration (minutes)</label
                >
                <input
                    id="duration"
                    v-model.number="form.duration_minutes"
                    type="number"
                    min="15"
                    max="240"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                />
                <p
                    v-if="form.errors.duration_minutes"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.duration_minutes }}
                </p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="capacity"
                    >Default capacity</label
                >
                <input
                    id="capacity"
                    v-model.number="form.default_capacity"
                    type="number"
                    min="1"
                    max="200"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                />
                <p
                    v-if="form.errors.default_capacity"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.default_capacity }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium" for="price"
                    >Price (cents, 0 = free)</label
                >
                <input
                    id="price"
                    v-model.number="form.price_cents"
                    type="number"
                    min="0"
                    max="100000"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                />
                <p
                    v-if="form.errors.price_cents"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.price_cents }}
                </p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="deadline"
                    >Cancellation deadline (hours)</label
                >
                <input
                    id="deadline"
                    v-model.number="form.cancellation_deadline_hours"
                    type="number"
                    min="0"
                    max="168"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                />
                <p
                    v-if="form.errors.cancellation_deadline_hours"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.cancellation_deadline_hours }}
                </p>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="form.is_active"
                type="checkbox"
                class="rounded border-border"
            />
            Active (visible for new schedules)
        </label>

        <button
            type="submit"
            class="rounded-lg bg-primary px-5 py-2.5 font-medium text-primary-foreground hover:opacity-90 disabled:opacity-50"
            :disabled="form.processing"
            :aria-busy="form.processing"
        >
            {{ submitLabel }}
        </button>
    </form>
</template>
