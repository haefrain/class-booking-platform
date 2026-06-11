<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

export type ScheduleOption = { id: number; name: string };

export type ScheduleFormData = {
    class_type_id: number | null;
    instructor_id: number | null;
    weekday: number;
    start_time: string;
    duration_minutes: number | null;
    capacity: number | null;
    starts_on: string;
    ends_on: string | null;
    is_active: boolean;
};

const props = defineProps<{
    classTypes: ScheduleOption[];
    instructors: ScheduleOption[];
    initial?: Partial<ScheduleFormData>;
    action: string;
    method: 'post' | 'put';
    submitLabel: string;
}>();

const WEEKDAYS = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];

const form = useForm<ScheduleFormData>({
    class_type_id: props.initial?.class_type_id ?? null,
    instructor_id: props.initial?.instructor_id ?? null,
    weekday: props.initial?.weekday ?? 0,
    start_time: props.initial?.start_time ?? '09:00',
    duration_minutes: props.initial?.duration_minutes ?? null,
    capacity: props.initial?.capacity ?? null,
    starts_on:
        props.initial?.starts_on ?? new Date().toISOString().slice(0, 10),
    ends_on: props.initial?.ends_on ?? null,
    is_active: props.initial?.is_active ?? true,
});

const dstWarning = () =>
    form.start_time >= '02:00' && form.start_time < '03:00';

function submit() {
    form.submit(props.method, props.action, { preserveScroll: true });
}
</script>

<template>
    <form class="max-w-xl space-y-5" @submit.prevent="submit">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium" for="class_type"
                    >Class type</label
                >
                <select
                    id="class_type"
                    v-model="form.class_type_id"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                    required
                >
                    <option
                        v-for="type in classTypes"
                        :key="type.id"
                        :value="type.id"
                    >
                        {{ type.name }}
                    </option>
                </select>
                <p
                    v-if="form.errors.class_type_id"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.class_type_id }}
                </p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="instructor"
                    >Instructor</label
                >
                <select
                    id="instructor"
                    v-model="form.instructor_id"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                    required
                >
                    <option
                        v-for="instructor in instructors"
                        :key="instructor.id"
                        :value="instructor.id"
                    >
                        {{ instructor.name }}
                    </option>
                </select>
                <p
                    v-if="form.errors.instructor_id"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.instructor_id }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium" for="weekday"
                    >Weekday</label
                >
                <select
                    id="weekday"
                    v-model.number="form.weekday"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                >
                    <option
                        v-for="(day, index) in WEEKDAYS"
                        :key="day"
                        :value="index"
                    >
                        {{ day }}
                    </option>
                </select>
                <p
                    v-if="form.errors.weekday"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.weekday }}
                </p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="start_time">
                    Start time — Academy time ({{ $page.props.name }})
                </label>
                <input
                    id="start_time"
                    v-model="form.start_time"
                    type="time"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                    required
                />
                <p v-if="dstWarning()" class="mt-1 text-sm text-amber-600">
                    02:00–03:00 may not exist on DST transition days; the
                    session shifts forward on those dates.
                </p>
                <p
                    v-if="form.errors.start_time"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.start_time }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label
                    class="mb-1 block text-sm font-medium"
                    for="sched_duration"
                    >Duration override (min)</label
                >
                <input
                    id="sched_duration"
                    v-model.number="form.duration_minutes"
                    type="number"
                    min="15"
                    max="240"
                    placeholder="inherit"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                />
            </div>
            <div>
                <label
                    class="mb-1 block text-sm font-medium"
                    for="sched_capacity"
                    >Capacity override</label
                >
                <input
                    id="sched_capacity"
                    v-model.number="form.capacity"
                    type="number"
                    min="1"
                    max="200"
                    placeholder="inherit"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                />
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-sm font-medium" for="starts_on"
                    >Starts on</label
                >
                <input
                    id="starts_on"
                    v-model="form.starts_on"
                    type="date"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                    required
                />
                <p
                    v-if="form.errors.starts_on"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.starts_on }}
                </p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="ends_on"
                    >Ends on (optional)</label
                >
                <input
                    id="ends_on"
                    v-model="form.ends_on"
                    type="date"
                    class="w-full rounded-md border border-border bg-background px-3 py-2"
                />
                <p
                    v-if="form.errors.ends_on"
                    class="mt-1 text-sm text-destructive"
                >
                    {{ form.errors.ends_on }}
                </p>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input
                v-model="form.is_active"
                type="checkbox"
                class="rounded border-border"
            />
            Active (generates sessions)
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
