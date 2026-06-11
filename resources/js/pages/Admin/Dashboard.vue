<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes/admin';

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
        <h1 class="text-xl font-semibold">Admin dashboard</h1>
        <p class="text-muted-foreground">
            Occupancy, class types and schedules will appear here.
        </p>
    </div>
</template>
