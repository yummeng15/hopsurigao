
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight, Search } from '@lucide/vue';
import { ref } from 'vue';

type BookingRow = {
    id: number;
    tourist: string;
    package: string;
    tour_date: string | null;
    guests: number;
    status: string;
    payment_status: string;
    payment_label: string;
    amount_paid: number;
    total_amount: number;
};

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

type BookingsPagination = {
    data: BookingRow[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    bookings: BookingsPagination;
    filters: { status: string; search: string; date: string };
}>();

const status = ref(props.filters.status);
const search = ref(props.filters.search);
const tourDate = ref(props.filters.date);

const fieldClass =
    'block w-full rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground placeholder:text-foreground/50';

function applyFilters(): void {
    const query: Record<string, string> = {};

    if (status.value) query.status = status.value;
    if (search.value.trim()) query.search = search.value.trim();
    if (tourDate.value) query.date = tourDate.value;

    router.get('/operator/bookings', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters(): void {
    status.value = '';
    search.value = '';
    tourDate.value = '';

    router.get('/operator/bookings', {}, { replace: true });
}
</script>

<template>
    <Head title="Operator Bookings" />

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 text-foreground sm:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-primary">
                    Tour Operator Portal
                </p>

                <h1 class="mt-1 text-3xl font-bold text-foreground">My Bookings</h1>

                <p class="mt-2 text-sm text-foreground/70">
                    View the reservations belonging to your tour business.
                </p>
            </div>

            <Link
                href="/operator/bookings/calendar"
                class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground hover:bg-muted"
            >
                <CalendarDays class="size-4" />
                Booking Calendar
            </Link>
        </header>

        <form
            class="grid gap-3 rounded-3xl border border-border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-[1fr_180px_170px_auto_auto]"
            @submit.prevent="applyFilters"
        >
            <label class="space-y-1 text-xs font-medium text-foreground">
                Search tourist

                <input
                    v-model="search"
                    type="search"
                    maxlength="100"
                    placeholder="Customer name"
                    :class="fieldClass"
                />
            </label>

            <label class="space-y-1 text-xs font-medium text-foreground">
                Status

                <select v-model="status" :class="fieldClass">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </label>

            <label class="space-y-1 text-xs font-medium text-foreground">
                Tour date

                <input v-model="tourDate" type="date" :class="fieldClass" />
            </label>

            <button
                type="submit"
                class="inline-flex items-center justify-center gap-2 self-end rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90"
            >
                <Search class="size-4" />
                Search
            </button>

            <button
                type="button"
                class="self-end rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground hover:bg-muted"
                @click="clearFilters"
            >
                Clear
            </button>
        </form>

        <section class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
            <div class="border-b border-border px-5 py-4">
                <h2 class="font-semibold text-foreground">Reservations ({{ bookings.total }})</h2>
            </div>

            <p
                v-if="bookings.data.length === 0"
                class="px-5 py-12 text-center text-sm text-foreground/70"
            >
                No reservations match these filters.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm text-foreground">
                    <thead class="border-b border-border text-foreground/65">
                        <tr>
                            <th scope="col" class="px-5 py-3">Reference</th>
                            <th scope="col" class="px-5 py-3">Customer</th>
                            <th scope="col" class="px-5 py-3">Package</th>
                            <th scope="col" class="px-5 py-3">Tour date</th>
                            <th scope="col" class="px-5 py-3">Guests</th>
                            <th scope="col" class="px-5 py-3">Status</th>
                            <th scope="col" class="px-5 py-3">Payment Status</th>
                            <th scope="col" class="px-5 py-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="booking in bookings.data"
                            :key="booking.id"
                            class="border-t border-border"
                        >
                            <td class="px-5 py-4 font-medium">
                                #{{ booking.id }}
                            </td>

                            <td class="px-5 py-4">
                                {{ booking.tourist }}
                            </td>

                            <td class="px-5 py-4">
                                {{ booking.package }}
                            </td>

                            <td class="px-5 py-4">
                                {{ booking.tour_date ?? '—' }}
                            </td>

                            <td class="px-5 py-4">
                                {{ booking.guests }}
                            </td>

                            <td class="px-5 py-4">
                                <span class="rounded-full border border-border bg-muted px-2 py-1 text-xs font-medium capitalize text-foreground">
                                    {{ booking.status }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full border px-2 py-1 text-xs font-semibold uppercase tracking-wide"
                                    :class="paymentBadgeClass(booking.payment_status)"
                                >
                                    {{ booking.payment_label }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    :href="`/operator/bookings/${booking.id}`"
                                    class="font-semibold text-primary hover:underline"
                                >
                                    View details
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="bookings.last_page > 1"
                class="flex items-center justify-between gap-3 border-t border-border px-5 py-4"
            >
                <p class="text-xs text-foreground/70">
                    Page {{ bookings.current_page }} of {{ bookings.last_page }}
                </p>

                <div class="flex gap-2">
                    <Link
                        v-if="bookings.prev_page_url"
                        :href="bookings.prev_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground hover:bg-muted"
                    >
                        <ChevronLeft class="size-4" />
                        Previous
                    </Link>

                    <Link
                        v-if="bookings.next_page_url"
                        :href="bookings.next_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground hover:bg-muted"
                    >
                        Next
                        <ChevronRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>

        <p class="text-xs text-foreground/70">
            This new screen is read-only. Booking changes, payment collection and QR verification remain in their existing modules until those actions are audited.
        </p>
    </main>
</template>
