<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ScheduleForm from '@/components/admin/ScheduleForm.vue';
import type { ScheduleOption } from '@/components/admin/ScheduleForm.vue';

defineProps<{
    classTypes: ScheduleOption[];
    instructors: ScheduleOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Schedules', href: '/admin/schedules' },
            { title: 'New', href: '/admin/schedules/create' },
        ],
    },
});
</script>

<template>
    <Head title="New schedule" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">New weekly schedule</h1>
        <p class="text-sm text-muted-foreground">
            Sessions are generated immediately for the next 8 weeks.
        </p>
        <ScheduleForm
            :class-types="classTypes"
            :instructors="instructors"
            action="/admin/schedules"
            method="post"
            submit-label="Create schedule"
        />
    </div>
</template>
