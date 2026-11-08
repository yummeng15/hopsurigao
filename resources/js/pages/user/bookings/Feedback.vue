<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, MessageSquare } from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { store as storeFeedback } from '@/routes/user/bookings/feedback';
import { index as feedbackIndex } from '@/routes/user/feedback';

type FeedbackBooking = {
    id: number;
    package_name: string | null;
    destination: string | null;
    tour_date: string | null;
};

const props = defineProps<{
    booking: FeedbackBooking;
}>();

const form = useForm({
    enjoyment: '',
    operator_service: '',
    overall_experience: '',
    comment: '',
});

function sendFeedback(): void {
    form.post(storeFeedback.url(props.booking.id));
}
</script>

<template>
    <Head title="Tour feedback" />

    <div class="tourist-page">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
        </div>

        <div class="mx-auto max-w-3xl px-5 py-8 lg:px-8">
            <div class="mb-6">
                <Link
                    :href="feedbackIndex()"
                    class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground transition hover:text-foreground"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span>Back to Feedback</span>
                </Link>
            </div>

            <div class="overflow-hidden rounded-3xl border bg-background/80 shadow-xs backdrop-blur">
                <div class="space-y-6 p-5 sm:p-8">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <MessageSquare class="h-5 w-5" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight">How was your tour?</h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Tell us if you enjoyed the package and how the tour operator treated you. This is not about the website.
                            </p>
                            <p v-if="props.booking.package_name" class="mt-2 text-sm font-medium text-foreground">
                                {{ props.booking.package_name }}
                                <span v-if="props.booking.destination" class="font-normal text-muted-foreground">
                                    · {{ props.booking.destination }}
                                </span>
                            </p>
                            <p v-if="props.booking.tour_date" class="mt-1 text-xs text-muted-foreground">
                                Tour date: {{ props.booking.tour_date }}
                            </p>
                        </div>
                    </div>

                    <form class="space-y-6" @submit.prevent="sendFeedback">
                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold">Did you enjoy this tour package?</legend>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" class="rounded-xl" :variant="form.enjoyment === 'enjoyed' ? 'default' : 'outline'" @click="form.enjoyment = 'enjoyed'">
                                    Enjoyed it
                                </Button>
                                <Button type="button" class="rounded-xl" :variant="form.enjoyment === 'not_enjoyed' ? 'default' : 'outline'" @click="form.enjoyment = 'not_enjoyed'">
                                    Did not enjoy
                                </Button>
                            </div>
                            <p v-if="form.errors.enjoyment" class="text-sm text-destructive">{{ form.errors.enjoyment }}</p>
                        </fieldset>

                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold">How was the tour operator's service?</legend>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" class="rounded-xl" :variant="form.operator_service === 'good' ? 'default' : 'outline'" @click="form.operator_service = 'good'">
                                    Good
                                </Button>
                                <Button type="button" class="rounded-xl" :variant="form.operator_service === 'okay' ? 'default' : 'outline'" @click="form.operator_service = 'okay'">
                                    Okay
                                </Button>
                                <Button type="button" class="rounded-xl" :variant="form.operator_service === 'poor' ? 'default' : 'outline'" @click="form.operator_service = 'poor'">
                                    Poor
                                </Button>
                            </div>
                            <p v-if="form.errors.operator_service" class="text-sm text-destructive">{{ form.errors.operator_service }}</p>
                        </fieldset>

                        <fieldset class="space-y-2">
                            <legend class="text-sm font-semibold">Overall tour experience?</legend>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" class="rounded-xl" :variant="form.overall_experience === 'great' ? 'default' : 'outline'" @click="form.overall_experience = 'great'">
                                    Great
                                </Button>
                                <Button type="button" class="rounded-xl" :variant="form.overall_experience === 'okay' ? 'default' : 'outline'" @click="form.overall_experience = 'okay'">
                                    Okay
                                </Button>
                                <Button type="button" class="rounded-xl" :variant="form.overall_experience === 'poor' ? 'default' : 'outline'" @click="form.overall_experience = 'poor'">
                                    Poor
                                </Button>
                            </div>
                            <p v-if="form.errors.overall_experience" class="text-sm text-destructive">{{ form.errors.overall_experience }}</p>
                        </fieldset>

                        <div class="space-y-2">
                            <label class="text-sm font-semibold" for="booking-feedback-comment">
                                Anything else about the tour? <span class="font-normal text-muted-foreground">(optional)</span>
                            </label>
                            <Textarea
                                id="booking-feedback-comment"
                                v-model="form.comment"
                                class="min-h-24"
                                placeholder="What you liked about the package, boat crew, pickup, or anything to improve."
                            />
                            <p v-if="form.errors.comment" class="text-sm text-destructive">{{ form.errors.comment }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Button type="submit" class="rounded-xl" :disabled="form.processing">
                                {{ form.processing ? 'Sending...' : 'Send feedback' }}
                            </Button>
                            <Button as-child variant="outline" class="rounded-xl">
                                <Link :href="feedbackIndex()">Cancel</Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
