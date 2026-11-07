
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, MapPin, Users } from '@lucide/vue';

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
};

defineProps<{ booking: BookingDetails }>();
</script>

<template>
    <Head :title="`Booking #${booking.id}`" />

    <main class="mx-auto max-w-4xl space-y-6 px-4 py-6 sm:px-6">
        <Link
            href="/operator/bookings"
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
        >
            <ArrowLeft class="size-4" />
            Back to My Bookings
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm text-slate-500">
                    Tour Operator Portal
                </p>

                <h1 class="mt-1 text-3xl font-bold">
                    Booking #{{ booking.id }}
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    Created {{ booking.created_at ?? '—' }}
                </p>
            </div>

            <span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold capitalize text-slate-900">
                {{ booking.status }}
            </span>
        </header>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="space-y-3 rounded-xl border bg-white p-5 shadow-sm dark:bg-slate-900">
                <h2 class="text-lg font-semibold">
                    Tour Information
                </h2>

                <p class="font-medium">
                    {{ booking.package }}
                </p>

                <p class="text-sm text-slate-500">
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

            <section class="space-y-3 rounded-xl border bg-white p-5 shadow-sm dark:bg-slate-900">
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

                <p class="text-sm text-slate-500">
                    Nationality: {{ booking.nationality || 'Not specified' }}
                </p>

                <div class="border-t pt-3 dark:border-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Special request
                    </p>

                    <p class="mt-1 whitespace-pre-wrap text-sm">
                        {{ booking.special_request || 'None' }}
                    </p>
                </div>
            </section>
        </div>

        <section class="flex flex-wrap items-center gap-3 rounded-xl border bg-white p-5 dark:bg-slate-900">
            <div class="flex-1">
                <h2 class="font-semibold">Trip Status & Attendance</h2>
                <p class="mt-1 text-sm text-slate-500">
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
