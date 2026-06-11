<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ScheduleForm from '@/components/admin/ScheduleForm.vue';
import type {
    ScheduleFormData,
    ScheduleOption,
} from '@/components/admin/ScheduleForm.vue';

const props = defineProps<{
    classTypes: ScheduleOption[];
    instructors: ScheduleOption[];
    schedule: ScheduleFormData & { id: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Schedules', href: '/admin/schedules' },
            { title: 'Edit', href: '' },
        ],
    },
});
</script>

<template>
    <Head title="Edit schedule" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Edit schedule</h1>
        <p class="text-sm text-muted-foreground">
            Existing sessions keep their times — use Regenerate on the list to
            apply slot changes to future sessions.
        </p>
        <ScheduleForm
            :class-types="props.classTypes"
            :instructors="props.instructors"
            :initial="props.schedule"
            :action="`/admin/schedules/${props.schedule.id}`"
            method="put"
            submit-label="Save changes"
        />
    </div>
</template>
