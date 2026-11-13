<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, MapPin, Users } from '@lucide/vue';

type PaymentRow = {
    id: number;
    amount: number;
    payment_method: string | null;
    payment_status: string;
    gateway: string | null;
    transaction_reference: string | null;
    paid_at: string | null;
    channel_label: string;
};

type PaymentSummary = {
    status: string;
    label: string;
    total_amount: number;
    amount_paid: number;
    remaining_balance: number;
    payment_method: string | null;
    payment_gateway: string | null;
    transaction_reference: string | null;
    paid_at: string | null;
    payments: PaymentRow[];
};

type BookingDetails = {
    id: number;
    customer_name: string;
    customer_email: string;
    contact_number: string;
    nationality: string;
    special_request: string;
    package: string;
    destination: string;
    tour_date: string | null;
    pickup_location: string;
    pickup_time: string | null;
    guests: number;
    status: string;
    created_at: string | null;
    payment: PaymentSummary;
};

defineProps<{ booking: BookingDetails }>();

function money(value: number): string {
    return `₱${Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function paymentBadgeClass(status: string): string {
    switch (status) {
        case 'paid':
            return 'border-emerald-500/40 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        case 'unpaid':
            return 'border-red-500/40 bg-red-500/15 text-red-700 dark:text-red-300';
        case 'partial':
            return 'border-amber-500/40 bg-amber-500/15 text-amber-800 dark:text-amber-200';
        case 'pending':
            return 'border-sky-500/40 bg-sky-500/15 text-sky-700 dark:text-sky-300';
        case 'refunded':
            return 'border-violet-500/40 bg-violet-500/15 text-violet-700 dark:text-violet-300';
        case 'failed':
            return 'border-rose-500/40 bg-rose-500/15 text-rose-700 dark:text-rose-300';
        default:
            return 'border-border bg-muted text-foreground';
    }
}
</script>

<template>
    <Head :title="`Booking #${booking.id}`" />

    <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 text-foreground sm:px-6">
        <Link
            href="/operator/bookings"
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
        >
            <ArrowLeft class="size-4" />
            Back to My Bookings
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm text-muted-foreground">
                    Tour Operator Portal
                </p>

                <h1 class="mt-1 text-3xl font-bold">
                    Booking #{{ booking.id }}
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    Created {{ booking.created_at ?? '—' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="rounded-full border border-border bg-muted px-3 py-1.5 text-sm font-semibold capitalize text-foreground">
                    {{ booking.status }}
                </span>
                <span
                    class="rounded-full border px-3 py-1.5 text-sm font-semibold uppercase tracking-wide"
                    :class="paymentBadgeClass(booking.payment.status)"
                >
                    {{ booking.payment.label }}
                </span>
            </div>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="space-y-3 rounded-xl border border-border bg-card p-5 shadow-sm">
                <h2 class="text-lg font-semibold">
                    Tour Information
                </h2>

                <p class="font-medium">
                    {{ booking.package }}
                </p>

                <p class="text-sm text-muted-foreground">
                    {{ booking.destination }}
                </p>

                <p class="flex items-center gap-2 text-sm">
                    <CalendarDays class="size-4 text-primary" />
                    {{ booking.tour_date ?? 'Not available' }}
                </p>

                <p class="flex items-center gap-2 text-sm">
                    <Users class="size-4 text-primary" />
                    {{ booking.guests }} guest(s)
                </p>

                <p class="flex items-center gap-2 text-sm">
                    <MapPin class="size-4 text-primary" />
                    {{ booking.pickup_location }} ·
                    {{ booking.pickup_time ?? 'Time unavailable' }}
                </p>
            </section>

            <section class="space-y-3 rounded-xl border border-border bg-card p-5 shadow-sm">
                <h2 class="text-lg font-semibold">
                    Customer Information
                </h2>

                <p class="font-medium">
                    {{ booking.customer_name }}
                </p>

                <p class="break-all text-sm">
                    {{ booking.customer_email || 'No email' }}
                </p>

                <p class="text-sm">
                    {{ booking.contact_number || 'No phone number' }}
                </p>

                <p class="text-sm text-muted-foreground">
                    Nationality: {{ booking.nationality || 'Not specified' }}
                </p>

                <div class="border-t border-border pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        Special request
                    </p>

                    <p class="mt-1 whitespace-pre-wrap text-sm">
                        {{ booking.special_request || 'None' }}
                    </p>
                </div>
            </section>
        </div>

        <section class="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold">Payment Information</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Based on verified payment records only. Booking status alone does not mark a reservation as paid.
                    </p>
                </div>
                <span
                    class="rounded-full border px-3 py-1.5 text-sm font-semibold uppercase tracking-wide"
                    :class="paymentBadgeClass(booking.payment.status)"
                >
                    {{ booking.payment.label }}
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-border p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Total booking amount</p>
                    <p class="mt-1 text-lg font-bold">{{ money(booking.payment.total_amount) }}</p>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Amount paid</p>
                    <p class="mt-1 text-lg font-bold">{{ money(booking.payment.amount_paid) }}</p>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Payment method</p>
                    <p class="mt-1 text-sm font-semibold">
                        {{ booking.payment.payment_method || '—' }}
                        <span v-if="booking.payment.payment_gateway" class="text-muted-foreground">
                            · {{ booking.payment.payment_gateway }}
                        </span>
                    </p>
                </div>
                <div class="rounded-xl border border-border p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Transaction reference</p>
                    <p class="mt-1 break-all text-sm font-semibold">
                        {{ booking.payment.transaction_reference || '—' }}
                    </p>
                </div>
                <div class="rounded-xl border border-border p-3 sm:col-span-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Payment date</p>
                    <p class="mt-1 text-sm font-semibold">{{ booking.payment.paid_at || '—' }}</p>
                </div>
            </div>

            <div v-if="booking.payment.payments.length" class="overflow-x-auto rounded-xl border border-border">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-border text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2">Channel</th>
                            <th class="px-4 py-2">Amount</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Reference</th>
                            <th class="px-4 py-2">Paid at</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="payment in booking.payment.payments"
                            :key="payment.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-3">{{ payment.channel_label }}</td>
                            <td class="px-4 py-3 font-medium">{{ money(payment.amount) }}</td>
                            <td class="px-4 py-3 capitalize">{{ payment.payment_status }}</td>
                            <td class="px-4 py-3 break-all">{{ payment.transaction_reference || '—' }}</td>
                            <td class="px-4 py-3">{{ payment.paid_at || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No payment records are linked to this booking yet.
            </p>
        </section>

        <section class="flex flex-wrap items-center gap-3 rounded-xl border border-border bg-card p-5">
            <div class="flex-1">
                <h2 class="font-semibold">Trip Status & Attendance</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Record QR check-in, complete a trip, or report a no-show.
                    Payments are managed separately.
                </p>
            </div>

            <Link
                :href="`/operator/bookings/${booking.id}/trip`"
                class="inline-flex rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90"
            >
                Manage Trip
            </Link>
        </section>
    </main>
</template>
