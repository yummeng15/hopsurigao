<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    CalendarDays,
    CheckCircle2,
    MessageSquare,
    Sailboat,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { create as createFeedback } from '@/routes/user/bookings/feedback';
import { index as feedbackIndex } from '@/routes/user/feedback';
import { index as packagesIndex } from '@/routes/user/packages';

type FeedbackAnswers = {
    enjoyment: string;
    operator_service: string;
    overall_experience: string;
    comment: string | null;
};

type FeedbackBooking = {
    id: number;
    booking_status: string;
    package_name: string | null;
    destination: string | null;
    boat_name: string | null;
    tour_date: string | null;
    has_feedback: boolean;
    feedback: FeedbackAnswers | null;
};

const props = defineProps<{
    bookings: FeedbackBooking[];
}>();

const pending = computed(() => props.bookings.filter((booking) => !booking.has_feedback));
const submitted = computed(() => props.bookings.filter((booking) => booking.has_feedback));

function formatDate(date: string | null): string {
    if (!date) {
        return '—';
    }

    const parsed = new Date(`${date.slice(0, 10)}T00:00:00`);

    if (Number.isNaN(parsed.getTime())) {
        return date;
    }

    return parsed.toLocaleDateString(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Tour feedback" />

    <div class="mx-auto max-w-3xl space-y-6 px-5 py-8 lg:px-8">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <MessageSquare class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Tour feedback</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    After your tour date, rate the package and the tour operator's service — not the website.
                </p>
            </div>
        </div>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                Waiting for your review
            </h2>

            <div v-if="pending.length" class="space-y-3">
                <article
                    v-for="booking in pending"
                    :key="booking.id"
                    class="rounded-2xl border bg-card/60 p-4 shadow-xs backdrop-blur sm:p-5"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 space-y-1">
                            <p class="truncate text-base font-bold">
                                {{ booking.package_name ?? 'Tour package' }}
                            </p>
                            <p class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <span class="inline-flex items-center gap-1">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    {{ formatDate(booking.tour_date) }}
                                </span>
                                <span v-if="booking.destination">· {{ booking.destination }}</span>
                                <span v-if="booking.boat_name">· {{ booking.boat_name }}</span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Booking #{{ booking.id }}
                            </p>
                        </div>

                        <Button as-child class="rounded-xl">
                            <Link :href="createFeedback.url(booking.id)">
                                Leave feedback
                            </Link>
                        </Button>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-2xl border border-dashed bg-muted/20 p-8 text-center"
            >
                <Sailboat class="mx-auto h-8 w-8 text-primary" />
                <p class="mt-3 text-sm font-semibold">No tours waiting for feedback</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    After you finish a confirmed tour, it will show up here.
                </p>
                <Button as-child variant="outline" class="mt-4 rounded-xl">
                    <Link :href="packagesIndex()">Browse tours</Link>
                </Button>
            </div>
        </section>

        <section v-if="submitted.length" class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                Already submitted
            </h2>

            <article
                v-for="booking in submitted"
                :key="`done-${booking.id}`"
                class="rounded-2xl border bg-card/40 p-4 sm:p-5"
            >
                <div class="flex items-start gap-3">
                    <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                    <div class="min-w-0 space-y-1">
                        <p class="truncate text-sm font-bold">
                            {{ booking.package_name ?? 'Tour package' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatDate(booking.tour_date) }} · Booking #{{ booking.id }}
                        </p>
                        <p v-if="booking.feedback" class="text-xs text-muted-foreground">
                            Enjoyment: {{ booking.feedback.enjoyment }}
                            · Service: {{ booking.feedback.operator_service }}
                            · Overall: {{ booking.feedback.overall_experience }}
                        </p>
                        <p v-if="booking.feedback?.comment" class="pt-1 text-sm">
                            {{ booking.feedback.comment }}
                        </p>
                    </div>
                </div>
            </article>
        </section>

        <p class="text-center text-xs text-muted-foreground">
            <Link :href="feedbackIndex()" class="underline-offset-2 hover:underline">
                Refresh this list
            </Link>
        </p>
    </div>
</template>
