<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';

type RefundDetails = {
    id: number;
    booking_id: number;
    payment_id: number;
    tourist: string;
    tourist_email: string | null;
    package: string;
    destination: string | null;
    amount: number;
    payment_status: string | null;
    payment_method: string | null;
    category_label?: string;
    reason: string;
    status: string;
    status_label: string;
    rejection_reason: string | null;
    reviewed_by: string | null;
    created_at: string | null;
    reviewed_at: string | null;
    processed_at: string | null;
};

const props = defineProps<{
    refund: RefundDetails;
}>();

const page = usePage();
const approving = ref(false);
const rejectForm = useForm({
    rejection_reason: '',
});

const canDecide = computed(() => props.refund.status === 'pending');

function peso(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

function approve(): void {
    if (!canDecide.value || approving.value) {
        return;
    }

    if (
        !window.confirm(
            'Approve this refund? This records a simulated PayMongo Test Mode refund. No real money is transferred.',
        )
    ) {
        return;
    }

    approving.value = true;
    router.post(`/operator/refunds/${props.refund.id}/approve`, {}, {
        preserveScroll: true,
        onFinish: () => {
            approving.value = false;
        },
    });
}

function reject(): void {
    rejectForm.post(`/operator/refunds/${props.refund.id}/reject`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Refund #${refund.id}`" />

    <main class="mx-auto max-w-3xl space-y-6 px-4 py-6 text-foreground sm:px-6">
        <Link
            href="/operator/refunds"
            class="inline-flex items-center gap-2 text-sm font-semibold text-cyan-700 hover:underline dark:text-cyan-300"
        >
            <ArrowLeft class="size-4" />
            Back to Refund Management
        </Link>

        <header class="space-y-2">
            <h1 class="text-3xl font-bold tracking-tight">Refund request #{{ refund.id }}</h1>
            <p class="text-sm text-foreground/70">
                Booking #{{ refund.booking_id }} · Payment #{{ refund.payment_id }}
            </p>
        </header>

        <p
            v-if="page.props.flash?.success"
            class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-200"
        >
            {{ page.props.flash.success }}
        </p>

        <p
            v-if="page.props.errors?.refund"
            class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-300"
        >
            {{ page.props.errors.refund }}
        </p>

        <section class="space-y-4 rounded-3xl border border-border bg-card p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-xs text-foreground/65">Tourist</p>
                    <p class="font-semibold">{{ refund.tourist }}</p>
                    <p class="text-sm text-foreground/70">{{ refund.tourist_email }}</p>
                </div>
                <div>
                    <p class="text-xs text-foreground/65">Package</p>
                    <p class="font-semibold">{{ refund.package }}</p>
                    <p class="text-sm text-foreground/70">{{ refund.destination }}</p>
                </div>
                <div>
                    <p class="text-xs text-foreground/65">Payment amount</p>
                    <p class="font-semibold">{{ peso(refund.amount) }}</p>
                    <p class="text-sm capitalize text-foreground/70">
                        {{ refund.payment_method }} · {{ refund.payment_status }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-foreground/65">Refund status</p>
                    <p class="font-semibold">{{ refund.status_label }}</p>
                    <p class="text-sm text-foreground/70">Requested {{ refund.created_at }}</p>
                </div>
            </div>

            <div>
                <p class="text-xs text-foreground/65">Eligible reason</p>
                <p class="mt-1 text-sm font-medium">{{ refund.category_label || '—' }}</p>
            </div>

            <div>
                <p class="text-xs text-foreground/65">Tourist details</p>
                <p class="mt-1 whitespace-pre-wrap text-sm">{{ refund.reason }}</p>
            </div>

            <div v-if="refund.rejection_reason">
                <p class="text-xs text-foreground/65">Rejection reason</p>
                <p class="mt-1 whitespace-pre-wrap text-sm">{{ refund.rejection_reason }}</p>
            </div>

            <div v-if="refund.reviewed_by" class="text-sm text-foreground/70">
                Reviewed by {{ refund.reviewed_by }}
                <span v-if="refund.reviewed_at"> · {{ refund.reviewed_at }}</span>
            </div>
        </section>

        <section
            v-if="canDecide"
            class="space-y-4 rounded-3xl border border-border bg-card p-5 shadow-sm"
        >
            <h2 class="text-base font-semibold">Operator decision</h2>
            <p class="text-sm text-foreground/70">
                Approve records a simulated refund only. Reject requires a reason for the tourist.
            </p>

            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-xl bg-[#00FFFF] px-4 py-2 text-sm font-semibold text-cyan-950"
                    data-test="approve-refund"
                    @click="approve"
                >
                    Approve
                </button>
            </div>

            <form class="space-y-3" @submit.prevent="reject">
                <label class="block text-sm font-medium">
                    Rejection reason
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        required
                        minlength="5"
                        rows="3"
                        class="mt-1 w-full rounded-lg border border-border bg-card px-3 py-2 text-sm"
                        placeholder="Explain why this refund is rejected"
                        data-test="reject-reason"
                    />
                </label>
                <p
                    v-if="rejectForm.errors.rejection_reason"
                    class="text-sm text-rose-600"
                >
                    {{ rejectForm.errors.rejection_reason }}
                </p>
                <button
                    type="submit"
                    class="rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold"
                    :disabled="rejectForm.processing"
                    data-test="reject-refund"
                >
                    Reject
                </button>
            </form>
        </section>
    </main>
</template>
