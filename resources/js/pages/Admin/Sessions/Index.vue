<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ConfirmDialog from '@/components/booking/ConfirmDialog.vue';
import EmptyState from '@/components/booking/EmptyState.vue';
import OccupancyBar from '@/components/booking/OccupancyBar.vue';
import WeekSwitcher from '@/components/booking/WeekSwitcher.vue';
import { formatSessionDay, formatTime } from '@/lib/date';
import type { WeekNav } from '@/types/booking';

type Row = {
    id: number;
    name: string | null;
    instructor: string | null;
    starts_at: string;
    status: string;
    booked: number;
    capacity: number;
    waiting: number;
};

defineProps<{
    sessions: Row[];
    week: WeekNav;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sessions', href: '/admin/sessions' }],
    },
});

const cancelling = ref<Row | null>(null);
const reason = ref('');

function confirmCancel() {
    if (cancelling.value === null) {
        return;
    }

    router.post(
        `/admin/sessions/${cancelling.value.id}/cancel`,
        { reason: reason.value || 'Cancelled by the studio' },
        {
            preserveScroll: true,
            onFinish: () => {
                cancelling.value = null;
                reason.value = '';
            },
        },
    );
}

function changeCapacity(session: Row) {
    const value = window.prompt(
        `New capacity for ${session.name} (currently ${session.capacity}, ${session.booked} booked):`,
        String(session.capacity),
    );

    if (value === null) {
        return;
    }

    router.patch(
        `/admin/sessions/${session.id}/capacity`,
        { capacity: Number(value) },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Sessions" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Sessions</h1>
            <WeekSwitcher :week="week" base-url="/admin/sessions" />
        </div>

        <EmptyState v-if="sessions.length === 0">
            No sessions this week.
        </EmptyState>

        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-muted-foreground">
                    <th class="py-2 pr-4 font-medium">When</th>
                    <th class="py-2 pr-4 font-medium">Class</th>
                    <th class="py-2 pr-4 font-medium">Instructor</th>
                    <th class="py-2 pr-4 font-medium">Occupancy</th>
                    <th class="py-2 pr-4 font-medium">Waiting</th>
                    <th class="py-2 font-medium">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="session in sessions"
                    :key="session.id"
                    class="border-b border-border/60"
                    :class="session.status === 'cancelled' ? 'opacity-50' : ''"
                >
                    <td class="py-2 pr-4 whitespace-nowrap">
                        {{ formatSessionDay(session.starts_at) }}
                        {{ formatTime(session.starts_at) }}
                    </td>
                    <td class="py-2 pr-4 font-medium">
                        {{ session.name }}
                        <span
                            v-if="session.status === 'cancelled'"
                            class="ml-1 text-xs text-destructive"
                            >cancelled</span
                        >
                    </td>
                    <td class="py-2 pr-4">{{ session.instructor }}</td>
                    <td class="py-2 pr-4">
                        <OccupancyBar
                            :booked="session.booked"
                            :capacity="session.capacity"
                        />
                    </td>
                    <td class="py-2 pr-4 tabular-nums">
                        {{ session.waiting }}
                    </td>
                    <td class="py-2 text-right whitespace-nowrap">
                        <template v-if="session.status === 'scheduled'">
                            <button
                                type="button"
                                class="mr-3 underline-offset-4 hover:underline"
                                @click="changeCapacity(session)"
                            >
                                Capacity
                            </button>
                            <button
                                type="button"
                                class="text-destructive underline-offset-4 hover:underline"
                                @click="cancelling = session"
                            >
                                Cancel
                            </button>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>

        <ConfirmDialog
            :open="cancelling !== null"
            title="Cancel this session?"
            :description="`Everyone booked into ${cancelling?.name ?? ''} will be notified; paid bookings are refunded automatically.`"
            confirm-label="Cancel session"
            destructive
            @confirm="confirmCancel"
            @cancel="cancelling = null"
        >
            <label class="mt-4 block text-sm font-medium" for="cancel-reason">
                Reason (sent to attendees)
            </label>
            <input
                id="cancel-reason"
                v-model="reason"
                type="text"
                class="mt-1 w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                placeholder="Instructor unavailable"
            />
        </ConfirmDialog>
    </div>
</template>
