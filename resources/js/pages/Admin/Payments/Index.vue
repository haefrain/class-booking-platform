<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { priceLabel } from '@/lib/date';

type Row = {
    id: number;
    user: string | null;
    class: string | null;
    amount_cents: number;
    amount_refunded_cents: number | null;
    currency: string;
    status: string;
    flag: string | null;
    refund_requested_at: string | null;
    paid_at: string | null;
};

defineProps<{ payments: Row[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payments', href: '/admin/payments' }],
    },
});
</script>

<template>
    <Head title="Payments" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <h1 class="text-xl font-semibold">Payments</h1>

        <p
            v-if="payments.length === 0"
            class="rounded-lg border border-dashed border-border p-8 text-center text-muted-foreground"
        >
            No payments yet.
        </p>

        <table v-else class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-muted-foreground">
                    <th class="py-2 pr-4 font-medium">Member</th>
                    <th class="py-2 pr-4 font-medium">Class</th>
                    <th class="py-2 pr-4 font-medium">Amount</th>
                    <th class="py-2 pr-4 font-medium">Status</th>
                    <th class="py-2 pr-4 font-medium">Flag</th>
                    <th class="py-2 font-medium">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="payment in payments"
                    :key="payment.id"
                    class="border-b border-border/60"
                >
                    <td class="py-2 pr-4">{{ payment.user }}</td>
                    <td class="py-2 pr-4">{{ payment.class }}</td>
                    <td class="py-2 pr-4 tabular-nums">
                        {{ priceLabel(payment.amount_cents, payment.currency) }}
                        <span
                            v-if="payment.amount_refunded_cents"
                            class="text-muted-foreground"
                        >
                            (−{{
                                priceLabel(
                                    payment.amount_refunded_cents,
                                    payment.currency,
                                )
                            }})
                        </span>
                    </td>
                    <td class="py-2 pr-4 capitalize">
                        {{ payment.status.replace('_', ' ') }}
                    </td>
                    <td class="py-2 pr-4">
                        <span
                            v-if="payment.flag"
                            class="rounded bg-amber-400/20 px-1.5 py-0.5 text-xs text-amber-700"
                        >
                            {{ payment.flag.replace('_', ' ') }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <button
                            v-if="payment.status === 'refund_failed'"
                            type="button"
                            class="text-sm underline-offset-4 hover:underline"
                            @click="
                                router.post(
                                    `/admin/payments/${payment.id}/retry-refund`,
                                )
                            "
                        >
                            Retry refund
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
