
<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import {
    ArrowLeft,
    Banknote,
    CreditCard,
    Wallet,
} from '@lucide/vue';

type PaymentHistory = {
    id: number;
    amount: number;
    method: string;
    status: string;
    paid_at: string | null;
    reference: string | null;
};

type BookingPayment = {
    id: number;
    tourist: string;
    package: string;
    status: string;
    total: number;
    received: number;
    remaining: number;
    canCollect: boolean;
    payments: PaymentHistory[];
};

const props = defineProps<{
    booking: BookingPayment;
    collectionKey: string;
}>();

const page = usePage();

const form = useForm({
    amount: props.booking.remaining.toFixed(2),
    cash_received: false,
    collection_key: props.collectionKey,
});

function peso(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}

// The server issues a fresh submission reference after each redirect.
watch(
    () => props.collectionKey,
    (key) => {
        form.collection_key = key;
        form.amount = props.booking.remaining.toFixed(2);
        form.cash_received = false;
        form.clearErrors();
    }
);

function submitCash(): void {
    if (!props.booking.canCollect || form.processing) {
        return;
    }

    form.post(
        `/operator/payments/${props.booking.id}/collect-balance`,
        {
            preserveScroll: true,
        }
    );
}
</script>

<template>
    <Head :title="`Payment - Booking #${booking.id}`" />

    <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6">
        <Link
            href="/operator/payments"
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary"
        >
            <ArrowLeft class="size-4" />
            Back to My Payments
        </Link>

        <header>
            <div class="flex items-center gap-2 text-primary">
                <CreditCard class="size-5" />
                Tour Operator Payments
            </div>

            <h1 class="mt-2 text-3xl font-bold">
                Booking #{{ booking.id }}
            </h1>

            <p class="mt-2 text-sm text-slate-500">
                {{ booking.tourist }} · {{ booking.package }}
            </p>

            <p class="mt-1 text-sm capitalize">
                Booking status: <strong>{{ booking.status }}</strong>
            </p>

            <p
                v-if="page.props.errors?.refund"
                class="mt-3 rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700"
            >
                {{ page.props.errors.refund }}
            </p>
        </header>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-white p-5 dark:bg-slate-900">
                <p class="text-sm text-slate-500">
                    Total booking amount
                </p>

                <p class="mt-2 text-2xl font-bold">
                    {{ peso(booking.total) }}
                </p>
            </div>

            <div class="rounded-xl border bg-white p-5 dark:bg-slate-900">
                <p class="text-sm text-slate-500">
                    Amount received
                </p>

                <p class="mt-2 text-2xl font-bold">
                    {{ peso(booking.received) }}
                </p>
            </div>

            <div class="rounded-xl border bg-white p-5 dark:bg-slate-900">
                <p class="flex items-center gap-2 text-sm text-slate-500">
                    <Wallet class="size-4" />
                    Remaining balance
                </p>

                <p class="mt-2 text-2xl font-bold">
                    {{ peso(booking.remaining) }}
                </p>
            </div>
        </section>

        <section
            v-if="booking.canCollect"
            class="space-y-4 rounded-xl border bg-white p-5 shadow-sm dark:bg-slate-900"
        >
            <div class="flex items-center gap-2">
                <Banknote class="size-5 text-primary" />

                <h2 class="text-xl font-semibold">
                    Record Remaining Cash Balance
                </h2>
            </div>

            <p class="text-sm text-slate-500">
                Record only cash you have physically received
                from this tourist. The remaining balance will
                update automatically.
            </p>

            <form
                class="space-y-4"
                @submit.prevent="submitCash"
            >
                <label class="block max-w-xs space-y-1 text-sm font-medium">
                    Cash amount received (PHP)

                    <input
                        v-model="form.amount"
                        type="number"
                        required
                        min="0.01"
                        step="0.01"
                        :max="booking.remaining"
                        class="block w-full rounded-lg border bg-transparent p-3"
                    />
                </label>

                <label class="flex items-start gap-3 rounded-lg border p-3 text-sm">
                    <input
                        v-model="form.cash_received"
                        type="checkbox"
                        required
                        class="mt-1"
                    />

                    <span>
                        I confirm that I have physically
                        received this cash payment.
                    </span>
                </label>

                <div
                    v-if="Object.keys(form.errors).length"
                    role="alert"
                    class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-700"
                >
                    <p
                        v-for="(message, key) in form.errors"
                        :key="key"
                    >
                        {{ message }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || !form.cash_received"
                    class="rounded-lg bg-primary px-5 py-3 font-semibold text-primary-foreground disabled:opacity-50"
                >
                    {{
                        form.processing
                            ? 'Recording payment...'
                            : 'Confirm Cash Received'
                    }}
                </button>
            </form>
        </section>

        <section
            v-else
            class="rounded-xl border bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-900 dark:text-slate-300"
        >
            <p v-if="booking.remaining <= 0">
                This booking is fully paid. No additional
                cash collection is available.
            </p>

            <p v-else-if="booking.remaining > 0">
                Cash collection stays available only for an older verified GCash downpayment. New online reservations are paid in full and have no cash balance to collect.
            </p>
        </section>

        <section class="overflow-hidden rounded-xl border bg-white dark:bg-slate-900">
            <div class="border-b p-5 dark:border-slate-800">
                <h2 class="text-xl font-semibold">
                    Payment History
                </h2>
            </div>

            <p
                v-if="booking.payments.length === 0"
                class="p-5 text-sm text-slate-500"
            >
                No transactions have been recorded.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3">Transaction</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Paid at</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="payment in booking.payments"
                            :key="payment.id"
                            class="border-t dark:border-slate-800"
                        >
                            <td class="px-4 py-3">
                                #{{ payment.id }}
                            </td>

                            <td class="px-4 py-3 capitalize">
                                {{ payment.method }}
                            </td>

                            <td class="px-4 py-3 font-semibold">
                                {{ peso(payment.amount) }}
                            </td>

                            <td class="px-4 py-3 capitalize">
                                {{ payment.status }}
                            </td>

                            <td class="px-4 py-3">
                                {{ payment.paid_at ?? '—' }}
                            </td>

                            <td class="px-4 py-3 capitalize text-foreground/70">
                                {{ payment.status === 'refunded' ? 'Refunded (via Refund Management)' : '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Link
            :href="`/operator/bookings/${booking.id}`"
            class="inline-block text-sm font-semibold text-primary hover:underline"
        >
            View reservation details
        </Link>
    </main>
</template>
