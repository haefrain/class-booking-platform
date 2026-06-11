<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ClassTypeForm from '@/components/admin/ClassTypeForm.vue';
import type { ClassTypeFormData } from '@/components/admin/ClassTypeForm.vue';

const props = defineProps<{
    classType: ClassTypeFormData & { id: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Class types', href: '/admin/class-types' },
            { title: 'Edit', href: '' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit · ${props.classType.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Edit class type</h1>
        <ClassTypeForm
            :initial="props.classType"
            :action="`/admin/class-types/${props.classType.id}`"
            method="put"
            submit-label="Save changes"
        />
    </div>
</template>
