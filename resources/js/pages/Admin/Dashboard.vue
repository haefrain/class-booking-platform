<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes/admin';

defineProps<{
    scheduler: {
        last_heartbeat: string | null;
        healthy: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Admin',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Admin" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Admin dashboard</h1>
            <span
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs"
                :class="
                    scheduler.healthy
                        ? 'border-emerald-500/40 text-emerald-600'
                        : 'border-destructive/40 text-destructive'
                "
                :title="scheduler.last_heartbeat ?? 'never'"
            >
                <span
                    class="size-2 rounded-full"
                    :class="
                        scheduler.healthy ? 'bg-emerald-500' : 'bg-destructive'
                    "
                />
                {{
                    scheduler.healthy
                        ? 'Scheduler running'
                        : 'Scheduler stale — reminders and sweeps are down'
                }}
            </span>
        </div>
        <p class="text-muted-foreground">
            Occupancy and KPIs land with the UI milestone.
        </p>
    </div>
</template>
