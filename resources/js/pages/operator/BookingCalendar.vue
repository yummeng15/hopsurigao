
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

type DaySummary = {
    date: string;
    bookings: number;
    guests: number;
};

const props = defineProps<{
    month: string;
    monthLabel: string;
    previousMonth: string;
    nextMonth: string;
    days: DaySummary[];
}>();

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

type CalendarCell = {
    date: string;
    day: number;
    summary: DaySummary | undefined;
} | null;

const cells = computed<CalendarCell[]>(() => {
    const [year, month] = props.month.split('-').map(Number);

    const firstWeekday = new Date(
        Date.UTC(year, month - 1, 1)
    ).getUTCDay();

    const daysInMonth = new Date(
        Date.UTC(year, month, 0)
    ).getUTCDate();

    const lookup = new Map(
        props.days.map((row) => [row.date, row])
    );

    const result: CalendarCell[] = Array.from(
        { length: firstWeekday },
        () => null
    );

    for (let day = 1; day <= daysInMonth; day++) {
        const date = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

        result.push({
            date,
            day,
            summary: lookup.get(date),
        });
    }

    return result;
});

const monthlyBookings = computed(() =>
    props.days.reduce((sum, item) => sum + item.bookings, 0)
);

const monthlyGuests = computed(() =>
    props.days.reduce((sum, item) => sum + item.guests, 0)
);
</script>

<template>
    <Head title="Operator Booking Calendar" />

    <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 text-foreground sm:px-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-primary">
                    Tour Operator Portal
                </p>

                <h1 class="mt-1 text-3xl font-bold text-foreground">
                    Booking Calendar
                </h1>

                <p class="mt-2 text-sm text-foreground/70">
                    Only your business's reservations appear here.
                </p>
            </div>

            <Link
                href="/operator/bookings"
                class="rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground hover:bg-muted"
            >
                All Bookings
            </Link>
        </header>

        <section class="rounded-3xl border border-border bg-card p-4 shadow-sm sm:p-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <CalendarDays class="size-5 text-primary" />

                    <h2 class="text-xl font-semibold text-foreground">
                        {{ monthLabel }}
                    </h2>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="`/operator/bookings/calendar?month=${previousMonth}`"
                        aria-label="Previous month"
                        class="rounded-lg border border-border bg-card p-2 text-foreground hover:bg-muted"
                    >
                        <ChevronLeft class="size-5" />
                    </Link>

                    <Link
                        :href="`/operator/bookings/calendar?month=${nextMonth}`"
                        aria-label="Next month"
                        class="rounded-lg border border-border bg-card p-2 text-foreground hover:bg-muted"
                    >
                        <ChevronRight class="size-5" />
                    </Link>
                </div>
            </div>

            <p class="mb-4 text-sm text-foreground/70">
                {{ monthlyBookings }} bookings · {{ monthlyGuests }} guests
                (pending, confirmed and completed)
            </p>

            <div class="overflow-x-auto">
                <div class="min-w-[560px]">
                    <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-foreground/65">
                        <div
                            v-for="label in weekDays"
                            :key="label"
                            class="py-2"
                        >
                            {{ label }}
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-1">
                        <div
                            v-for="(cell, index) in cells"
                            :key="index"
                            class="min-h-24 rounded-lg border border-border p-2 text-sm"
                            :class="cell
                                ? 'bg-card text-foreground'
                                : 'bg-muted/40 text-foreground/40'"
                        >
                            <template v-if="cell">
                                <span class="font-semibold">
                                    {{ cell.day }}
                                </span>

                                <Link
                                    v-if="cell.summary"
                                    :href="`/operator/bookings?date=${cell.date}`"
                                    class="mt-2 block rounded-md border border-primary/30 bg-primary/15 px-2 py-2 text-xs text-primary hover:bg-primary/25"
                                >
                                    {{ cell.summary.bookings }} booking(s)

                                    <span class="mt-1 block opacity-80">
                                        {{ cell.summary.guests }} guest(s)
                                    </span>
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <p class="text-xs text-foreground/70">
            Cancelled bookings are excluded from the calendar.
            Select a booked day to review its reservations.
        </p>
    </main>
</template>
