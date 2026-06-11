<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { sessions } from '@/routes/instructor';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My sessions',
                href: sessions(),
            },
        ],
    },
});
</script>

<template>
    <Head title="My sessions" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">My sessions</h1>
        <p class="text-muted-foreground">
            Your upcoming sessions and rosters will appear here.
        </p>
    </div>
</template>
