<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CalendarDays, MapPin, Phone, Users } from '@lucide/vue';

import MonthCalendar, { type CalendarMarker } from '@/components/bookings/MonthCalendar.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/admin/bookings';

type CalendarBooking = {
    id: number;
    customer: string | null;
    package_name: string | null;
    destination: string | null;
    guests: number;
    status: string;
    pickup: string | null;
    phone: string;
};

type BookedDay = {
    date: string;
    bookings_count: number;
    guests: number;
    bookings: CalendarBooking[];
};

const props = defineProps<{
    today: string;
    days: BookedDay[];
}>();

const selectedDate = ref(props.today);

const markers = computed<Record<string, CalendarMarker>>(() => {
    return Object.fromEntries(
        props.days.map((day) => [
            day.date,
            {
                tone: 'booked' as const,
                count: day.bookings_count,
            },
        ]),
    );
});

const daysByDate = computed(() => {
    return new Map(props.days.map((day) => [day.date, day]));
});

const selectedDay = computed(() => daysByDate.value.get(selectedDate.value) ?? null);

const weekStrip = computed(() => {
    const start = parseDate(props.today);

    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        const key = toKey(date);
        const booked = daysByDate.value.get(key);

        return {
            date: key,
            label: index === 0 ? 'Today' : index === 1 ? 'Tomorrow' : weekday(date),
            dayNumber: date.getDate(),
            bookings_count: booked?.bookings_count ?? 0,
            guests: booked?.guests ?? 0,
        };
    });
});

function parseDate(value: string): Date {
    return new Date(`${value.slice(0, 10)}T00:00:00`);
}

function toKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function weekday(date: Date): string {
    return date.toLocaleDateString('en-US', { weekday: 'short' });
}

function formatDate(date: string): string {
    return parseDate(date).toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

function relativeLabel(date: string): string {
    const start = parseDate(props.today).getTime();
    const target = parseDate(date).getTime();
    const diff = Math.round((target - start) / 86400000);

    if (diff === 0) {
        return 'Today';
    }

    if (diff === 1) {
        return 'Tomorrow';
    }

    if (diff > 1) {
        return `In ${diff} days`;
    }

    return formatDate(date);
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'confirmed') {
        return 'default';
    }

    if (status === 'completed') {
        return 'secondary';
    }

    return 'outline';
}

function selectDate(date: string): void {
    selectedDate.value = date;
}

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Bookings',
            },
            {
                href: '/admin/bookings/calendar',
                title: 'Booking Calendar',
            },
        ],
    },
});
</script>

<template>
    <Head title="Booking Calendar" />

    <div class="px-4 py-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <Heading
                title="Booking Calendar"
                description="See which days are booked, starting today and continuing through the days that follow."
            />

            <Button as-child variant="outline" class="shrink-0">
                <Link :href="index()">
                    <CalendarDays class="h-4 w-4" />
                    All bookings
                </Link>
            </Button>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
            <button
                v-for="day in weekStrip"
                :key="day.date"
                type="button"
                class="rounded-2xl border px-3 py-3 text-left transition hover:border-primary/40"
                :class="selectedDate === day.date ? 'border-primary bg-primary/10' : 'bg-card'"
                @click="selectDate(day.date)"
            >
                <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ day.label }}
                </p>
                <p class="mt-1 text-lg font-semibold leading-none">{{ day.dayNumber }}</p>
                <p class="mt-2 text-xs" :class="day.bookings_count > 0 ? 'font-medium text-primary' : 'text-muted-foreground'">
                    {{ day.bookings_count > 0 ? `${day.bookings_count} booked` : 'Open' }}
                </p>
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <div class="space-y-3">
                <MonthCalendar
                    :markers="markers"
                    :selected="selectedDate"
                    :initial-date="today"
                    :today="today"
                    allow-empty
                    @select="selectDate"
                />

                <div class="flex items-center gap-2 px-1 text-xs text-muted-foreground">
                    <span class="h-2.5 w-2.5 rounded-full bg-primary" />
                    Highlighted dates have active bookings. The number is the booking count.
                </div>
            </div>

            <section class="rounded-2xl border bg-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary">
                            {{ relativeLabel(selectedDate) }}
                        </p>
                        <h3 class="text-lg font-semibold tracking-tight">{{ formatDate(selectedDate) }}</h3>
                    </div>

                    <p v-if="selectedDay" class="text-sm text-muted-foreground">
                        {{ selectedDay.bookings_count }} booking{{ selectedDay.bookings_count === 1 ? '' : 's' }}
                        · {{ selectedDay.guests }} guest{{ selectedDay.guests === 1 ? '' : 's' }}
                    </p>
                </div>

                <div v-if="selectedDay" class="mt-5 space-y-3">
                    <article
                        v-for="booking in selectedDay.bookings"
                        :key="booking.id"
                        class="rounded-xl border bg-background p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ booking.customer || 'Guest' }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ booking.package_name || 'Package' }}
                                    <span v-if="booking.destination"> · {{ booking.destination }}</span>
                                </p>
                            </div>

                            <Badge :variant="statusVariant(booking.status)" class="capitalize">
                                {{ booking.status }}
                            </Badge>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground">
                            <span class="inline-flex items-center gap-1.5">
                                <Users class="h-3.5 w-3.5" />
                                {{ booking.guests }} guest{{ booking.guests === 1 ? '' : 's' }}
                            </span>
                            <span v-if="booking.pickup" class="inline-flex items-center gap-1.5">
                                <MapPin class="h-3.5 w-3.5" />
                                {{ booking.pickup }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <Phone class="h-3.5 w-3.5" />
                                {{ booking.phone }}
                            </span>
                        </div>

                        <Button as-child variant="outline" size="sm" class="mt-4">
                            <Link :href="show(booking.id).url">View booking</Link>
                        </Button>
                    </article>
                </div>

                <div v-else class="mt-5 rounded-xl border border-dashed px-4 py-10 text-center">
                    <p class="text-sm font-medium">No bookings on this day</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Pick another highlighted date to see who is booked.
                    </p>
                </div>
            </section>
        </div>

        <section class="mt-8">
            <h3 class="text-base font-semibold tracking-tight">Booked days in order</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Active pending and confirmed bookings, from the nearest day onward.
            </p>

            <div v-if="days.length" class="mt-4 divide-y overflow-hidden rounded-2xl border bg-card">
                <button
                    v-for="day in days"
                    :key="day.date"
                    type="button"
                    class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition hover:bg-muted/50"
                    :class="selectedDate === day.date ? 'bg-primary/5' : ''"
                    @click="selectDate(day.date)"
                >
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary">
                            {{ relativeLabel(day.date) }}
                        </p>
                        <p class="text-sm font-medium">{{ formatDate(day.date) }}</p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ day.bookings_count }} booking{{ day.bookings_count === 1 ? '' : 's' }}
                        · {{ day.guests }} guest{{ day.guests === 1 ? '' : 's' }}
                    </p>
                </button>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed px-4 py-10 text-center">
                <p class="text-sm font-medium">No upcoming bookings yet</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    New reservations will show up here in date order.
                </p>
            </div>
        </section>
    </div>
</template>
