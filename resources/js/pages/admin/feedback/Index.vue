<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { MessageSquare } from '@lucide/vue';

import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/admin/booking-feedback';

type FeedbackItem = {
    id: number;
    booking_id: number;
    guest_name: string;
    guest_email: string | null;
    package_name: string;
    boat_name: string | null;
    tour_date: string | null;
    booking_status: string | null;
    enjoyment: 'enjoyed' | 'not_enjoyed';
    operator_service: 'good' | 'okay' | 'poor';
    overall_experience: 'great' | 'okay' | 'poor';
    comment: string | null;
    submitted_at: string | null;
};

defineProps<{
    feedback: FeedbackItem[];
    summary: {
        total: number;
        enjoyed: number;
        good_service: number;
        great: number;
        poor: number;
    };
}>();

const enjoymentLabel: Record<FeedbackItem['enjoyment'], string> = {
    enjoyed: 'Enjoyed the package',
    not_enjoyed: 'Did not enjoy',
};

const serviceLabel: Record<FeedbackItem['operator_service'], string> = {
    good: 'Good operator service',
    okay: 'Okay operator service',
    poor: 'Poor operator service',
};

const experienceLabel: Record<FeedbackItem['overall_experience'], string> = {
    great: 'Great overall experience',
    okay: 'Okay overall experience',
    poor: 'Poor overall experience',
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Feedback',
            },
        ],
    },
});
</script>

<template>
    <Head title="Tour feedback" />

    <div class="px-4 py-6">
        <Heading
            title="Tour feedback"
            description="What guests said about the package they booked and the tour operator's service — not about the website."
        />

        <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-2xl border bg-card/60 p-4">
                <p class="text-xs text-muted-foreground">Responses</p>
                <p class="mt-1 text-2xl font-bold">{{ summary.total }}</p>
            </div>
            <div class="rounded-2xl border bg-card/60 p-4">
                <p class="text-xs text-muted-foreground">Enjoyed package</p>
                <p class="mt-1 text-2xl font-bold">{{ summary.enjoyed }}</p>
            </div>
            <div class="rounded-2xl border bg-card/60 p-4">
                <p class="text-xs text-muted-foreground">Good service</p>
                <p class="mt-1 text-2xl font-bold">{{ summary.good_service }}</p>
            </div>
            <div class="rounded-2xl border bg-card/60 p-4">
                <p class="text-xs text-muted-foreground">Great / poor overall</p>
                <p class="mt-1 text-2xl font-bold">{{ summary.great }} / {{ summary.poor }}</p>
            </div>
        </div>

        <div v-if="feedback.length" class="mt-6 space-y-3">
            <article
                v-for="item in feedback"
                :key="item.id"
                class="rounded-2xl border bg-card/60 p-5"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-bold">{{ item.guest_name }}</h2>
                        <p class="text-sm text-muted-foreground">
                            Booking #{{ item.booking_id }}
                            <span v-if="item.tour_date"> · {{ item.tour_date }}</span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ item.package_name }}
                            <span v-if="item.boat_name"> · {{ item.boat_name }}</span>
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ item.submitted_at }}</p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <Badge :variant="item.enjoyment === 'enjoyed' ? 'default' : 'destructive'">
                        {{ enjoymentLabel[item.enjoyment] }}
                    </Badge>
                    <Badge :variant="item.operator_service === 'poor' ? 'destructive' : 'outline'">
                        {{ serviceLabel[item.operator_service] }}
                    </Badge>
                    <Badge :variant="item.overall_experience === 'great' ? 'default' : item.overall_experience === 'poor' ? 'destructive' : 'outline'">
                        {{ experienceLabel[item.overall_experience] }}
                    </Badge>
                </div>

                <p v-if="item.comment" class="mt-4 text-sm">{{ item.comment }}</p>
            </article>
        </div>

        <div v-else class="mt-6 rounded-3xl border border-dashed bg-card/40 p-12 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <MessageSquare class="h-7 w-7" />
            </div>
            <h2 class="mt-4 text-lg font-bold">No tour feedback yet</h2>
            <p class="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                Guest answers about their package and operator service will show up here.
            </p>
        </div>
    </div>
</template>
