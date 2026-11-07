
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarDays,
    ClipboardList,
    CreditCard,
    PlusCircle,
    QrCode,
    Ship,
    Wrench,
} from '@lucide/vue';

interface DashboardStats {
    today_tours: number;
    today_bookings: number;
    pending_bookings: number;
    boats_under_maintenance: number;
}

interface TodayBooking {
    id: number;
    tourist: string;
    package: string;
    guests: number;
    status: string;
}

defineProps<{
    stats: DashboardStats;
    todayBookings: TodayBooking[];
}>();

const shortcuts = [
    {
        title: 'All Bookings',
        href: '/operator/bookings',
        icon: ClipboardList,
    },
    {
        title: 'Create Walk-in',
        href: '/operator/walk-ins/create',
        icon: PlusCircle,
    },
    {
        title: 'Scan QR Ticket',
        href: '/operator/qr-scanner',
        icon: QrCode,
    },
    {
        title: 'View Calendar',
        href: '/operator/bookings/calendar',
        icon: CalendarDays,
    },
    {
        title: 'Payments',
        href: '/operator/payments',
        icon: CreditCard,
    },
    {
        title: 'Boat Maintenance',
        href: '/operator/operations/boats',
        icon: Wrench,
    },
];
</script>

<template>
    <Head title="Operator Dashboard" />

    <main class="mx-auto w-full max-w-7xl space-y-7 px-4 py-6 sm:px-6 lg:px-8">
        <header class="space-y-2">
            <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                <Ship class="size-5" />
                Tour operations portal
            </div>

            <h1 class="text-3xl font-bold tracking-tight">
                Operator Dashboard
            </h1>

            <p class="text-sm text-muted-foreground">
                Manage today's tours, reservations, payments,
                and boat operations from one place.
            </p>
        </header>

        <section
            aria-label="Daily overview"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div class="rounded-2xl border bg-card p-5 shadow-sm">
                <CalendarDays class="mb-3 size-6 text-primary" />

                <p class="text-sm text-muted-foreground">
                    Tour schedules today
                </p>

                <p class="mt-1 text-3xl font-bold">
                    {{ stats.today_tours }}
                </p>
            </div>

            <div class="rounded-2xl border bg-card p-5 shadow-sm">
                <CalendarCheck class="mb-3 size-6 text-primary" />

                <p class="text-sm text-muted-foreground">
                    Confirmed/completed bookings today
                </p>

                <p class="mt-1 text-3xl font-bold">
                    {{ stats.today_bookings }}
                </p>
            </div>

            <div class="rounded-2xl border bg-card p-5 shadow-sm">
                <ClipboardList class="mb-3 size-6 text-amber-600" />

                <p class="text-sm text-muted-foreground">
                    Pending bookings
                </p>

                <p class="mt-1 text-3xl font-bold">
                    {{ stats.pending_bookings }}
                </p>
            </div>

            <div class="rounded-2xl border bg-card p-5 shadow-sm">
                <Wrench class="mb-3 size-6 text-rose-600" />

                <p class="text-sm text-muted-foreground">
                    Boats under maintenance
                </p>

                <p class="mt-1 text-3xl font-bold">
                    {{ stats.boats_under_maintenance }}
                </p>
            </div>
        </section>

        <section
            aria-labelledby="shortcuts-heading"
            class="space-y-3"
        >
            <h2 id="shortcuts-heading" class="text-xl font-semibold">
                Quick actions
            </h2>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="shortcut in shortcuts"
                    :key="shortcut.href"
                    :href="shortcut.href"
                    class="flex items-center gap-3 rounded-xl border bg-card p-4 font-medium transition hover:border-primary hover:bg-primary/10"
                >
                    <component
                        :is="shortcut.icon"
                        class="size-5 shrink-0 text-primary"
                    />

                    {{ shortcut.title }}
                </Link>
            </div>
        </section>

        <section
            aria-labelledby="today-heading"
            class="rounded-2xl border bg-card p-5 shadow-sm"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 id="today-heading" class="text-xl font-semibold">
                    Today's reservations
                </h2>

                <Link
                    href="/operator/bookings"
                    class="text-sm font-semibold text-primary hover:underline"
                >
                    View all bookings
                </Link>
            </div>

            <p
                v-if="todayBookings.length === 0"
                class="rounded-lg bg-muted/50 px-4 py-8 text-center text-sm text-muted-foreground"
            >
                No bookings found for today.
            </p>

            <div v-else class="space-y-2">
                <Link
                    v-for="booking in todayBookings"
                    :key="booking.id"
                    :href="`/operator/bookings/${booking.id}`"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4 transition hover:bg-muted/40"
                >
                    <div>
                        <p class="font-semibold">
                            {{ booking.tourist }}
                        </p>

                        <p class="text-xs text-muted-foreground">
                            {{ booking.package }} ·
                            {{ booking.guests }} guest(s) ·
                            #{{ booking.id }}
                        </p>
                    </div>

                    <span class="rounded-full bg-muted px-3 py-1 text-xs font-medium capitalize">
                        {{ booking.status }}
                    </span>
                </Link>
            </div>
        </section>

        <p class="text-xs text-muted-foreground">
            Dashboard statistics and reservations are
            limited to your assigned tour operator.
        </p>
    </main>
</template>
