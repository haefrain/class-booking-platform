<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

type Row = {
    id: number;
    weekday: number;
    start_time: string;
    duration_minutes: number | null;
    capacity: number | null;
    starts_on: string;
    ends_on: string | null;
    is_active: boolean;
    class_type: { id: number | null; name: string | null };
    instructor: { id: number | null; name: string | null };
};

defineProps<{ schedules: Row[] }>();

const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function regenerate(id: number) {
    if (
        confirm(
            'Regenerate future sessions? Sessions keep their current times until you do this.',
        )
    ) {
        router.post(
            `/admin/schedules/${id}/regenerate`,
            {},
            { preserveScroll: true },
        );
    }
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Schedules', href: '/admin/schedules' }],
    },
});
</script>

<template>
    <Head title="Schedules" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Weekly schedules</h1>
            <Link
                href="/admin/schedules/create"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                New schedule
            </Link>
        </div>

        <p
            v-if="schedules.length === 0"
            class="rounded-lg border border-dashed border-border p-10 text-center text-muted-foreground"
        >
            No schedules yet — create one and sessions appear in the catalog
            immediately.
        </p>

        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-muted-foreground">
                    <th class="py-2 pr-4 font-medium">Class</th>
                    <th class="py-2 pr-4 font-medium">Slot (academy time)</th>
                    <th class="py-2 pr-4 font-medium">Instructor</th>
                    <th class="py-2 pr-4 font-medium">Window</th>
                    <th class="py-2 pr-4 font-medium">Status</th>
                    <th class="py-2 font-medium">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="schedule in schedules"
                    :key="schedule.id"
                    class="border-b border-border/60"
                >
                    <td class="py-2 pr-4 font-medium">
                        {{ schedule.class_type.name }}
                    </td>
                    <td class="py-2 pr-4">
                        {{ WEEKDAYS[schedule.weekday] }}
                        {{ schedule.start_time }}
                    </td>
                    <td class="py-2 pr-4">{{ schedule.instructor.name }}</td>
                    <td class="py-2 pr-4">
                        {{ schedule.starts_on }} →
                        {{ schedule.ends_on ?? 'open-ended' }}
                    </td>
                    <td class="py-2 pr-4">
                        <span
                            :class="
                                schedule.is_active
                                    ? 'text-emerald-600'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ schedule.is_active ? 'Active' : 'Paused' }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <button
                            type="button"
                            class="mr-3 underline-offset-4 hover:underline"
                            @click="regenerate(schedule.id)"
                        >
                            Regenerate
                        </button>
                        <Link
                            :href="`/admin/schedules/${schedule.id}/edit`"
                            class="underline-offset-4 hover:underline"
                        >
                            Edit
                        </Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
