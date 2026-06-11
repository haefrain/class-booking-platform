<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { login } from '@/routes';

const page = usePage();
</script>

<template>
    <Head title="Class catalog" />

    <div class="min-h-screen bg-background text-foreground">
        <header
            class="flex items-center justify-between border-b border-border px-6 py-4"
        >
            <span class="text-lg font-semibold">{{ page.props.name }}</span>
            <nav>
                <Link
                    v-if="!page.props.auth.user"
                    :href="login()"
                    class="text-sm font-medium underline-offset-4 hover:underline"
                >
                    Log in
                </Link>
            </nav>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-12">
            <h1 class="text-2xl font-semibold">Class catalog</h1>
            <p class="mt-2 text-muted-foreground">
                Upcoming classes will appear here once schedules are published.
            </p>
        </main>
    </div>
</template>
