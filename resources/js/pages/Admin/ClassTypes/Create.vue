<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClassTypeForm from '@/components/admin/ClassTypeForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Class types', href: '/admin/class-types' },
            { title: 'New', href: '/admin/class-types/create' },
        ],
    },
});
</script>

<template>
    <Head title="New class type" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">New class type</h1>
        <ClassTypeForm
            action="/admin/class-types"
            method="post"
            submit-label="Create class type"
        />
    </div>
</template>
