
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BadgeCheck,
    CircleAlert,
    Wallet,
} from '@lucide/vue';

type Booking = {
    id: number;
    customer_name: string;
    tour_name: string;
    tour_date: string | null;
    guests: number;
    booking_status: string;
    amount_received: number;
    remaining_balance: number;
};

defineProps<{
    booking: Booking;
}>();

const php = (value: number) =>
    new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
</script>

<template>
    <Head title="QR Booking Verification" />

    <main class="mx-auto max-w-3xl space-y-5 px-4 py-6 sm:px-6">
        <Link
            href="/operator/qr-scanner"
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary"
        >
            <ArrowLeft class="size-4" />
            Scan another QR
        </Link>

        <section class="rounded-xl border bg-white p-6 shadow-sm dark:bg-slate-900">
            <div class="flex items-center gap-2 text-emerald-700">
                <BadgeCheck class="size-7" />

                <strong>
                    QR token matched an operator-owned booking
                </strong>
            </div>

            <h1 class="mt-4 text-2xl font-bold">
                Booking #{{ booking.id }}
            </h1>

            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">Guest</dt>

                    <dd class="font-semibold">
                        {{ booking.customer_name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-slate-500">Package</dt>

                    <dd class="font-semibold">
                        {{ booking.tour_name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-slate-500">Tour date</dt>

                    <dd class="font-semibold">
                        {{ booking.tour_date ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-slate-500">Guests</dt>

                    <dd class="font-semibold">
                        {{ booking.guests }}
                    </dd>
                </div>

                <div>
                    <dt class="text-slate-500">
                        Booking status
                    </dt>

                    <dd class="font-semibold capitalize">
                        {{ booking.booking_status }}
                    </dd>
                </div>

                <div>
                    <dt class="text-slate-500">
                        Amount received
                    </dt>

                    <dd class="font-semibold">
                        {{ php(booking.amount_received) }}
                    </dd>
                </div>
            </dl>

            <div class="mt-5 rounded-lg border p-4">
                <p class="flex items-center gap-2 font-semibold">
                    <Wallet class="size-4" />
                    Remaining balance
                </p>

                <p class="mt-1 text-2xl font-bold">
                    {{ php(booking.remaining_balance) }}
                </p>

                <p class="mt-1 text-xs text-slate-500">
                    A balance shown here is informational.
                    This screen does not collect or record payments.
                </p>
            </div>

            <p
                v-if="
                    booking.booking_status !== 'confirmed'
                    && booking.booking_status !== 'completed'
                "
                class="mt-4 flex gap-2 text-sm text-amber-800"
            >
                <CircleAlert class="size-4 shrink-0" />

                The QR belongs to a booking that is not confirmed.
                Do not treat this as authorization to board.
            </p>

            <Link
                :href="`/operator/bookings/${booking.id}`"
                class="mt-5 inline-block rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"
            >
                View booking details
            </Link>
        </section>

        <p class="text-xs text-slate-500">
            Verification is read-only and does not mark
            a passenger as checked in or record a cash payment.
        </p>
    </main>
</template>
