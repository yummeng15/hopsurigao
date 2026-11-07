<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { RotateCcw, Search } from '@lucide/vue';
import { ref } from 'vue';

type RefundRow = {
    id: number;
    booking_id: number;
    tourist: string;
    package: string;
    amount: number;
    category_label?: string;
    reason: string;
    status: string;
    status_label: string;
    payment_status: string | null;
    created_at: string | null;
};

type Paginated = {
    data: RefundRow[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    refunds: Paginated;
    filters: {
        search: string;
        status: string;
    };
}>();

const search = ref(props.filters.search);
const status = ref(props.filters.status);

function applyFilters(): void {
    router.get(
        '/operator/refunds',
        {
            search: search.value.trim(),
            status: status.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
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
</script>

<template>
    <Head title="Refund Management" />

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 text-foreground sm:px-6">
        <header class="space-y-2">
            <div class="flex items-center gap-2 text-sm font-semibold text-cyan-700 dark:text-cyan-300">
                <RotateCcw class="size-4" />
                Refund Management
            </div>
            <h1 class="text-3xl font-bold tracking-tight">Refund requests</h1>
            <p class="text-sm text-foreground/70">
                Review tourist refund requests for your packages. Approving records a simulated
                PayMongo Test Mode refund. No real money is transferred.
            </p>
        </header>

        <form
            class="flex flex-wrap items-end gap-3 rounded-3xl border border-border bg-card p-4 shadow-sm"
            @submit.prevent="applyFilters"
        >
            <label class="min-w-[14rem] flex-1 text-sm font-medium">
                Search
                <span class="relative mt-1 block">
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-foreground/50" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Booking, tourist, package"
                        class="w-full rounded-lg border border-border bg-card py-2 pr-3 pl-9 text-sm"
                    />
                </span>
            </label>

            <label class="text-sm font-medium">
                Status
                <select
                    v-model="status"
                    class="mt-1 block rounded-lg border border-border bg-card px-3 py-2 text-sm"
                >
                    <option value="">All</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="refunded_test">Refunded (Test)</option>
                </select>
            </label>

            <button
                type="submit"
                class="rounded-xl bg-[#00FFFF] px-4 py-2 text-sm font-semibold text-cyan-950"
            >
                Filter
            </button>
        </form>

        <section class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
            <p
                v-if="refunds.data.length === 0"
                class="p-8 text-center text-sm text-foreground/70"
            >
                No refund requests match this filter.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-border text-foreground/65">
                        <tr>
                            <th class="px-4 py-3">Booking</th>
                            <th class="px-4 py-3">Tourist</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Eligible reason</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3">Refund status</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in refunds.data"
                            :key="item.id"
                            class="border-b border-border/70"
                        >
                            <td class="px-4 py-3 font-mono text-xs font-semibold">
                                #{{ item.booking_id }}
                            </td>
                            <td class="px-4 py-3">{{ item.tourist }}</td>
                            <td class="px-4 py-3">{{ item.package }}</td>
                            <td class="px-4 py-3 font-semibold">{{ peso(item.amount) }}</td>
                            <td class="px-4 py-3 text-foreground/80">
                                {{ item.category_label || '—' }}
                            </td>
                            <td class="max-w-[16rem] truncate px-4 py-3 text-foreground/80">
                                {{ item.reason }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                    :class="statusClass(item.status)"
                                >
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 capitalize">{{ item.payment_status }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/operator/refunds/${item.id}`"
                                    class="text-sm font-semibold text-cyan-700 hover:underline dark:text-cyan-300"
                                >
                                    View Details
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="refunds.last_page > 1"
                class="flex items-center justify-between border-t border-border px-4 py-3 text-sm"
            >
                <span class="text-foreground/70">
                    Page {{ refunds.current_page }} of {{ refunds.last_page }}
                </span>
                <div class="flex gap-2">
                    <Link
                        v-if="refunds.prev_page_url"
                        :href="refunds.prev_page_url"
                        class="rounded-lg border border-border px-3 py-1.5 font-semibold"
                        preserve-scroll
                    >
                        Prev
                    </Link>
                    <Link
                        v-if="refunds.next_page_url"
                        :href="refunds.next_page_url"
                        class="rounded-lg border border-border px-3 py-1.5 font-semibold"
                        preserve-scroll
                    >
                        Next
                    </Link>
                </div>
            </div>
        </section>
    </main>
</template>
