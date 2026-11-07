
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowLeft, CalendarDays, ShieldCheck, WalletCards } from '@lucide/vue';

type OperatorRow = {
    id: number;
    name: string;
    active: boolean;
    admins: number;
    packages: number;
    bookings: number;
    confirmed_bookings: number;
    payment_count: number;
    gross_received: number;
    cash_received: number;
    gcash_received: number;
    other_received: number;
};

type Totals = {
    operators: number;
    active_operators: number;
    bookings: number;
    gross_received: number;
    cash_received: number;
    gcash_received: number;
    other_received: number;
    unassigned_received: number;
};

const props = defineProps<{
    month: string;
    monthLabel: string;
    operators: OperatorRow[];
    totals: Totals;
}>();

const month = ref(props.month);
const search = ref('');
const monthInput = ref<HTMLInputElement | null>(null);

const fieldClass =
    'mt-1 block w-full min-w-[12rem] cursor-pointer rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground scheme-light dark:scheme-dark';

watch(() => props.month, (value) => { month.value = value; });

const visibleOperators = computed(() => props.operators.filter((item) =>
    item.name.toLowerCase().includes(search.value.trim().toLowerCase())
));

function loadMonth(): void {
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month.value)) return;

    router.get('/superadmin/operator-finance', { month: month.value }, {
        preserveScroll: true,
        replace: true,
    });
}

function openMonthPicker(): void {
    const input = monthInput.value;

    if (!input) {
        return;
    }

    input.focus();

    if (typeof input.showPicker === 'function') {
        try {
            input.showPicker();
        } catch {
            // Browser may block showPicker outside a direct user gesture.
        }
    }
}

function onMonthPicked(): void {
    loadMonth();
}

function shiftMonth(delta: number): void {
    const [yearPart, monthPart] = month.value.split('-').map(Number);
    const next = new Date(yearPart, monthPart - 1 + delta, 1);
    const y = next.getFullYear();
    const m = String(next.getMonth() + 1).padStart(2, '0');

    month.value = `${y}-${m}`;
    loadMonth();
}

function pesos(value: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}
</script>

<template>
    <Head title="Operator Financial Overview" />

    <main class="mx-auto max-w-7xl space-y-6 px-4 py-6 text-foreground sm:px-6">
        <header class="space-y-3">
            <Link
                href="/superadmin/dashboard"
                class="inline-flex items-center gap-2 text-sm font-semibold text-cyan-700 dark:text-cyan-300 hover:underline"
            >
                <ArrowLeft class="size-4" /> Back to dashboard
            </Link>

            <div class="flex items-center gap-2 text-sm font-semibold text-cyan-700 dark:text-cyan-300">
                <ShieldCheck class="size-5" /> Super Admin Only
            </div>

            <h1 class="text-3xl font-bold text-foreground">Operator Financial Overview</h1>

            <p class="text-sm text-foreground/70">
                Compare operator bookings and recorded payment collections. This is a
                read-only reconciliation view, not a commission or payout release screen.
            </p>
        </header>

        <form
            class="flex flex-wrap items-end gap-3 rounded-3xl border border-border bg-card p-4 shadow-sm"
            @submit.prevent="loadMonth"
        >
            <label class="text-sm font-medium text-foreground">
                Payment / booking month

                <span class="relative mt-1 block">
                    <input
                        ref="monthInput"
                        v-model="month"
                        type="month"
                        required
                        data-test="operator-finance-month"
                        aria-label="Payment or booking month"
                        :class="fieldClass"
                        @click="openMonthPicker"
                        @keydown.enter.prevent="openMonthPicker"
                        @change="onMonthPicked"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-foreground/70 hover:text-foreground"
                        aria-label="Open month calendar"
                        data-test="operator-finance-month-calendar"
                        @click="openMonthPicker"
                    >
                        <CalendarDays class="size-4" />
                    </button>
                </span>
            </label>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-xl border border-border bg-card px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted"
                    data-test="operator-finance-prev-month"
                    @click="shiftMonth(-1)"
                >
                    Prev
                </button>
                <button
                    type="button"
                    class="rounded-xl border border-border bg-card px-3 py-2 text-sm font-semibold text-foreground hover:bg-muted"
                    data-test="operator-finance-next-month"
                    @click="shiftMonth(1)"
                >
                    Next
                </button>
            </div>

            <button
                type="submit"
                class="rounded-xl bg-[#00FFFF] px-4 py-2 font-semibold text-cyan-950"
            >
                <CalendarDays class="mr-1 inline size-4" /> View month
            </button>

            <span class="self-center text-sm text-foreground/70">
                {{ monthLabel }}
            </span>
        </form>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-border bg-card p-4 shadow-sm">
                <p class="text-xs text-foreground/70">Operators (active)</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ totals.operators }} ({{ totals.active_operators }})
                </p>
            </div>

            <div class="rounded-3xl border border-border bg-card p-4 shadow-sm">
                <p class="text-xs text-foreground/70">Bookings created this month</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ totals.bookings }}
                </p>
            </div>

            <div class="rounded-3xl border border-border bg-card p-4 shadow-sm">
                <p class="text-xs text-foreground/70">Recorded collections this month</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ pesos(totals.gross_received) }}
                </p>
            </div>

            <div class="rounded-3xl border border-border bg-card p-4 shadow-sm">
                <p class="text-xs text-foreground/70">Payments on unassigned packages</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ pesos(totals.unassigned_received) }}
                </p>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-3xl border border-border bg-card p-4 shadow-sm">
                <p class="text-xs text-foreground/70">PayMongo GCash</p>
                <p class="mt-1 text-xl font-semibold text-foreground">
                    {{ pesos(totals.gcash_received) }}
                </p>
            </div>

            <div class="rounded-3xl border border-border bg-card p-4 shadow-sm">
                <p class="text-xs text-foreground/70">Cash collected</p>
                <p class="mt-1 text-xl font-semibold text-foreground">
                    {{ pesos(totals.cash_received) }}
                </p>
            </div>

            <div class="rounded-3xl border border-border bg-card p-4 shadow-sm">
                <p class="text-xs text-foreground/70">Other methods / unmatched gateway</p>
                <p class="mt-1 text-xl font-semibold text-foreground">
                    {{ pesos(totals.other_received) }}
                </p>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-4">
                <div class="flex items-center gap-2 text-foreground">
                    <WalletCards class="size-5 text-cyan-700 dark:text-cyan-300" />
                    <h2 class="font-semibold">Operator breakdown</h2>
                </div>

                <input
                    v-model="search"
                    type="search"
                    placeholder="Search operator"
                    aria-label="Search operator"
                    class="rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground placeholder:text-foreground/50"
                />
            </div>

            <p
                v-if="visibleOperators.length === 0"
                class="p-8 text-center text-sm text-foreground/70"
            >
                No operators match this search.
            </p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm text-foreground">
                    <thead class="border-b border-border text-foreground/65">
                        <tr>
                            <th class="px-4 py-3">Operator</th>
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3">Packages</th>
                            <th class="px-4 py-3">Bookings</th>
                            <th class="px-4 py-3">GCash</th>
                            <th class="px-4 py-3">Cash</th>
                            <th class="px-4 py-3">Other</th>
                            <th class="px-4 py-3">Total received</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in visibleOperators"
                            :key="item.id"
                            class="border-t border-border"
                        >
                            <td class="px-4 py-3">
                                <p class="font-semibold text-foreground">{{ item.name }}</p>

                                <p
                                    class="text-xs font-medium"
                                    :class="item.active
                                        ? 'text-emerald-700 dark:text-emerald-300'
                                        : 'text-foreground/70'"
                                >
                                    {{ item.active ? 'Active' : 'Inactive' }}
                                </p>
                            </td>

                            <td class="px-4 py-3 text-foreground">
                                {{ item.admins }}
                            </td>

                            <td class="px-4 py-3 text-foreground">
                                {{ item.packages }}
                            </td>

                            <td class="px-4 py-3 text-foreground">
                                {{ item.bookings }}

                                <p class="text-xs text-foreground/70">
                                    {{ item.confirmed_bookings }} confirmed/completed
                                </p>
                            </td>

                            <td class="px-4 py-3 text-foreground">
                                {{ pesos(item.gcash_received) }}
                            </td>

                            <td class="px-4 py-3 text-foreground">
                                {{ pesos(item.cash_received) }}
                            </td>

                            <td class="px-4 py-3 text-foreground">
                                {{ pesos(item.other_received) }}
                            </td>

                            <td class="px-4 py-3 font-semibold text-foreground">
                                {{ pesos(item.gross_received) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs leading-relaxed text-foreground/70">
            Reporting basis: bookings are counted by creation date. Collections include
            payment records marked paid or partial with a paid_at date in the selected month.
            Amounts are gross recorded collections, not net earnings, commission owed,
            cash remitted, payout-ready funds, or proof of bank settlement. Refunds and
            missing payment timestamps need separate reconciliation.
        </p>
    </main>
</template>
