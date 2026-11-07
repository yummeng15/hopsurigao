<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import SimpleTable from '@/components/tables/DataTable.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/admin/refunds';

type RefundRequestRow = {
    id: number;
    booking_id: number;
    tourist: string;
    tourist_email: string | null;
    operator: string;
    package: string;
    amount: number;
    payment_status: string | null;
    reason: string;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    reviewed_by: string | null;
    created_at: string | null;
    reviewed_at: string | null;
};

const props = defineProps<{
    refundRequests?: RefundRequestRow[];
}>();

const columns = [
    { key: 'booking_id', label: 'Booking #', sortable: true, width: '110px' },
    { key: 'customer', label: 'Customer', sortable: false },
    { key: 'package_name', label: 'Package', sortable: false },
    { key: 'amount', label: 'Amount', sortable: true },
    { key: 'payment_method', label: 'Method', sortable: true },
    { key: 'payment_status', label: 'Payment status', sortable: true },
];

function formatCurrency(amount: number | string): string {
    const value = typeof amount === 'string' ? parseFloat(amount) : amount;

    return Number.isNaN(value)
        ? '0.00'
        : value.toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
}

function peso(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

function statusClass(value: string): string {
    if (value === 'pending') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (value === 'rejected') {
        return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }

    if (value === 'refunded_test' || value === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    return 'border-border bg-muted/40 text-foreground';
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Refund Oversight',
            },
        ],
    },
});
</script>

<template>
    <Head title="Refund Oversight" />

    <div class="px-4 py-6">
        <Heading
            title="Refund Oversight"
            description="View-only monitoring of refund requests and payment refund history. The Operator Admin approves or rejects refunds. This page does not change a payment."
        />

        <section class="mt-8 space-y-3">
            <Heading
                variant="small"
                title="Refund requests"
                description="All tourist refund requests across operators. Status is separate from payment status."
            />

            <p v-if="!props.refundRequests?.length" class="text-sm text-muted-foreground">
                No refund requests yet.
            </p>

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="border-b bg-muted/30 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3">Booking</th>
                            <th class="px-4 py-3">Tourist</th>
                            <th class="px-4 py-3">Operator</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Refund status</th>
                            <th class="px-4 py-3">Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in props.refundRequests"
                            :key="item.id"
                            class="border-b"
                        >
                            <td class="px-4 py-3 font-mono text-xs font-semibold">
                                #{{ item.booking_id }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ item.tourist }}</span>
                                    <span class="text-xs text-muted-foreground">{{ item.tourist_email }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ item.operator }}</td>
                            <td class="px-4 py-3">{{ item.package }}</td>
                            <td class="px-4 py-3 font-semibold">{{ peso(item.amount) }}</td>
                            <td class="max-w-[16rem] truncate px-4 py-3">{{ item.reason }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="statusClass(item.status)"
                                >
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 capitalize">{{ item.payment_status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="mt-10">
            <Heading
                variant="small"
                title="Payment refund history"
                description="Paid and refunded payments. The Operator Admin records a refund. This page does not change a payment."
            />

            <div class="mt-4">
                <SimpleTable
                    fetch-url="/admin/refunds/data"
                    :columns="columns"
                    searchable
                    enable-cache
                >
                    <template #cell-booking_id="{ row }">
                        <span class="font-mono text-xs font-semibold">#BOOK-{{ row.booking_id }}</span>
                    </template>

                    <template #cell-customer="{ row }">
                        <div class="flex flex-col">
                            <span class="text-sm font-medium text-foreground">{{ row.user_name }}</span>
                            <span class="text-xs text-muted-foreground">{{ row.user_email }}</span>
                        </div>
                    </template>

                    <template #cell-package_name="{ row }">
                        <div class="flex flex-col">
                            <span class="text-sm font-medium">{{ row.package_name }}</span>
                            <span v-if="row.destination" class="text-xs text-muted-foreground">{{ row.destination }}</span>
                        </div>
                    </template>

                    <template #cell-amount="{ row }">
                        <span class="text-sm font-semibold">₱{{ formatCurrency(row.amount) }}</span>
                    </template>

                    <template #cell-payment_method="{ row }">
                        <Badge variant="outline" class="text-xs font-normal capitalize">
                            {{ String(row.payment_method).replaceAll('_', ' ') }}
                        </Badge>
                    </template>

                    <template #cell-payment_status="{ row }">
                        <Badge
                            variant="outline"
                            class="text-xs font-semibold capitalize"
                            :class="row.payment_status === 'refunded'
                                ? 'border-purple-500/30 bg-purple-500/10 text-purple-600'
                                : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600'"
                        >
                            {{ row.payment_status }}
                        </Badge>
                    </template>
                </SimpleTable>
            </div>
        </div>
    </div>
</template>
