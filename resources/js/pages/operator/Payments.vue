<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CreditCard,
    Search,
} from '@lucide/vue';
import { ref } from 'vue';

type PaymentRow = {
    id: number;
    booking_id: number;
    tourist: string;
    package: string;
    amount: number;
    method: string;
    status: string;
    paid_at: string | null;
};

type PaginatedPayments = {
    data: PaymentRow[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    payments: PaginatedPayments;
    filters: {
        search: string;
        status: string;
    };
}>();

const search = ref(props.filters.search);
const status = ref(props.filters.status);

const fieldClass =
    'block w-full rounded-lg border border-border bg-card p-2 text-sm text-foreground placeholder:text-foreground/50';

function applyFilters(): void {
    router.get('/operator/payments', {
        search: search.value.trim(),
        status: status.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters(): void {
    search.value = '';
    status.value = '';
    router.get('/operator/payments', {}, {
        replace: true,
    });
}

function peso(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

</script>

<template>
    <Head title="Operator Payments" />

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 text-foreground sm:px-6">
        <header class="space-y-2">
            <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                <CreditCard class="size-5" />
                Tour Operator Portal
            </div>

            <h1 class="text-3xl font-bold text-foreground">
                My Payments
            </h1>

            <p class="text-sm text-foreground/70">
                View GCash, cash, and other payment transactions for reservations
                belonging to your tour business. Tourist refund requests are reviewed
                in Refund Management only.
            </p>
        </header>

        <form
            class="flex flex-wrap items-end gap-3 rounded-3xl border border-border bg-card p-4 shadow-sm"
            @submit.prevent="applyFilters"
        >
            <label class="min-w-48 flex-1 space-y-1 text-sm text-foreground">
                Search booking or tourist

                <input
                    v-model="search"
                    type="search"
                    maxlength="100"
                    placeholder="Booking ID, tourist, or package"
                    :class="fieldClass"
                />
            </label>

            <label class="space-y-1 text-sm text-foreground">
                Payment status

                <select
                    v-model="status"
                    :class="fieldClass"
                >
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="refunded">Refunded</option>
                </select>
            </label>

            <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 font-semibold text-primary-foreground"
            >
                <Search class="size-4" />
                Search
            </button>

            <button
                type="button"
                class="rounded-xl border border-border bg-card px-4 py-2 text-foreground hover:bg-muted"
                @click="clearFilters"
            >
                Clear
            </button>
        </form>

        <section class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
            <div class="border-b border-border px-5 py-4">
                <h2 class="font-semibold text-foreground">
                    Payment transactions ({{ payments.total }})
                </h2>
            </div>

            <p
                v-if="payments.data.length === 0"
                class="p-10 text-center text-sm text-foreground/70"
            >
                No transactions found for your filters.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm text-foreground">
                    <thead class="border-b border-border text-foreground/65">
                        <tr>
                            <th class="px-4 py-3">Booking</th>
                            <th class="px-4 py-3">Tourist</th>
                            <th class="px-4 py-3">Package</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="payment in payments.data"
                            :key="payment.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-3 font-semibold">
                                #{{ payment.booking_id }}
                            </td>

                            <td class="px-4 py-3">
                                {{ payment.tourist }}
                            </td>

                            <td class="px-4 py-3">
                                {{ payment.package }}
                            </td>

                            <td class="px-4 py-3 font-semibold">
                                {{ peso(payment.amount) }}
                            </td>

                            <td class="px-4 py-3 capitalize">
                                {{ payment.method }}
                            </td>

                            <td class="px-4 py-3 capitalize">
                                {{ payment.status }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex flex-wrap items-center gap-3">
                                    <Link
                                        :href="`/operator/payments/${payment.booking_id}`"
                                        class="font-semibold text-primary hover:underline"
                                    >
                                        View payment
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="payments.last_page > 1"
                class="flex items-center justify-between border-t border-border p-4"
            >
                <p class="text-sm text-foreground/70">
                    Page {{ payments.current_page }}
                    of {{ payments.last_page }}
                </p>

                <div class="flex gap-2">
                    <Link
                        v-if="payments.prev_page_url"
                        :href="payments.prev_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground hover:bg-muted"
                    >
                        <ArrowLeft class="size-4" />
                        Previous
                    </Link>

                    <Link
                        v-if="payments.next_page_url"
                        :href="payments.next_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground hover:bg-muted"
                    >
                        Next
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>
    </main>
</template>
