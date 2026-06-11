<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { priceLabel } from '@/lib/date';

type Row = {
    id: number;
    name: string;
    slug: string;
    duration_minutes: number;
    default_capacity: number;
    price_cents: number;
    cancellation_deadline_hours: number;
    is_active: boolean;
};

defineProps<{ classTypes: Row[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Class types', href: '/admin/class-types' }],
    },
});
</script>

<template>
    <Head title="Class types" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Class types</h1>
            <Link
                href="/admin/class-types/create"
                class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90"
            >
                New class type
            </Link>
        </div>

        <p
            v-if="classTypes.length === 0"
            class="rounded-lg border border-dashed border-border p-10 text-center text-muted-foreground"
        >
            No class types yet.
        </p>

        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-muted-foreground">
                    <th class="py-2 pr-4 font-medium">Name</th>
                    <th class="py-2 pr-4 font-medium">Duration</th>
                    <th class="py-2 pr-4 font-medium">Capacity</th>
                    <th class="py-2 pr-4 font-medium">Price</th>
                    <th class="py-2 pr-4 font-medium">Cancel ≤</th>
                    <th class="py-2 pr-4 font-medium">Status</th>
                    <th class="py-2 font-medium">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="type in classTypes"
                    :key="type.id"
                    class="border-b border-border/60"
                >
                    <td class="py-2 pr-4 font-medium">{{ type.name }}</td>
                    <td class="py-2 pr-4">{{ type.duration_minutes }} min</td>
                    <td class="py-2 pr-4">{{ type.default_capacity }}</td>
                    <td class="py-2 pr-4">
                        {{ priceLabel(type.price_cents) }}
                    </td>
                    <td class="py-2 pr-4">
                        {{ type.cancellation_deadline_hours }}h before
                    </td>
                    <td class="py-2 pr-4">
                        <span
                            :class="
                                type.is_active
                                    ? 'text-emerald-600'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ type.is_active ? 'Active' : 'Archived' }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <Link
                            :href="`/admin/class-types/${type.id}/edit`"
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
