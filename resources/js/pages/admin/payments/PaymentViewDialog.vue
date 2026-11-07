<!-- resources/js/pages/admin/payments/PaymentViewDialog.vue -->
<script setup lang="ts">
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    CreditCard,
    Calendar,
    User,
    Mail,
    Phone,
    MapPin,
    Clock,
    Users,
    Receipt,
    Copy,
    Check,
    FileText,
    Sparkles,
} from '@lucide/vue';

export type ProofFile = {
    name: string;
    path: string;
    url: string;
    extension: string;
    is_image: boolean;
};

export interface PaymentDetails {
    id: number;
    booking_id: number;
    user_name: string | null;
    user_email: string | null;
    user_phone: string | null;
    package_name: string | null;
    destination: string | null;
    tour_date: string | null;
    number_of_guests: number;
    amount: number | string;
    payment_method: string;
    payment_status: 'pending' | 'paid' | 'partial' | 'failed' | 'refunded' | 'cancelled' | string;
    transaction_reference: string | null;
    proof_of_payment_url?: string | null;
    proof_of_payment_is_image?: boolean | null;
    proof_files?: ProofFile[];
    notes: string | null;
    paid_at: string | null;
    created_at: string | null;
    booking?: {
        id: number;
        booking_status: string;
        number_of_guests: number;
        phone_number: string | null;
        nationality: string | null;
        special_request: string | null;
        user?: {
            id: number;
            name: string;
            email: string;
        } | null;
        tour_date?: {
            id: number;
            tour_date: string;
            package?: {
                id: number;
                package_name: string;
                destination: string;
                price: string | number;
            } | null;
        } | null;
        pickup_schedule?: {
            id: number;
            pickup_time: string;
            pickup_location?: {
                id: number;
                name: string;
            } | null;
        } | null;
    } | null;
}

const props = defineProps<{
    open: boolean;
    payment: PaymentDetails | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const copied = ref(false);

function copyToClipboard(text: string | null | undefined) {
    if (!text) return;
    navigator.clipboard.writeText(text);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
}

function formatCurrency(val: number | string | undefined | null): string {
    if (val === undefined || val === null) return '0.00';
    const num = typeof val === 'string' ? parseFloat(val) : val;
    return isNaN(num)
        ? '0.00'
        : num.toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
}

function formatDateTime(dateStr: string | null | undefined): string {
    if (!dateStr) return 'Not available';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return 'Not available';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getStatusBadgeClass(status: string | undefined): string {
    switch (status) {
        case 'paid':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
        case 'partial':
            return 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/30';
        case 'pending':
            return 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30';
        case 'failed':
            return 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/30';
        case 'refunded':
            return 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/30';
        case 'cancelled':
            return 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/30';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(value) => emit('update:open', value)"
    >
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
            <!-- Header -->
            <DialogHeader>
                <div class="flex items-center justify-between gap-4">
                    <DialogTitle class="text-xl font-bold">
                        Payment Details
                    </DialogTitle>
                    <Badge variant="outline" class="font-mono text-xs">
                        #PAY-{{ payment?.id }}
                    </Badge>
                </div>
                <DialogDescription>
                    Transaction and booking information for this payment.
                </DialogDescription>
            </DialogHeader>

            <div v-if="payment" class="space-y-6 pt-2">
                <!-- Amount & Status Highlight Card -->
                <div class="rounded-xl border bg-muted/40 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Total Amount
                            </p>
                            <h2 class="text-3xl font-extrabold tracking-tight text-foreground">
                                ₱{{ formatCurrency(payment.amount) }}
                            </h2>
                        </div>
                        <div class="flex flex-col items-end gap-1.5">
                            <Badge
                                variant="outline"
                                :class="[
                                    'px-3 py-1 text-sm font-semibold capitalize',
                                    getStatusBadgeClass(payment.payment_status),
                                ]"
                            >
                                {{ payment.payment_status }}
                            </Badge>
                            <span class="text-xs text-muted-foreground flex items-center gap-1">
                                <CreditCard class="h-3 w-3" />
                                {{ payment.payment_method }}
                            </span>
                        </div>
                    </div>

                    <!-- Transaction Reference -->
                    <div
                        v-if="payment.transaction_reference"
                        class="mt-4 flex items-center justify-between rounded-lg border bg-background/80 px-3.5 py-2"
                    >
                        <div class="flex items-center gap-2">
                            <Receipt class="h-4 w-4 text-muted-foreground" />
                            <span class="text-xs text-muted-foreground">Reference:</span>
                            <code class="text-xs font-mono font-bold text-foreground">
                                {{ payment.transaction_reference }}
                            </code>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            @click="copyToClipboard(payment.transaction_reference)"
                        >
                            <Check v-if="copied" class="mr-1 h-3.5 w-3.5 text-emerald-500" />
                            <Copy v-else class="mr-1 h-3.5 w-3.5 text-muted-foreground" />
                            {{ copied ? 'Copied' : 'Copy' }}
                        </Button>
                    </div>
                </div>

                <Separator />

                <!-- Payment Metadata Grid -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
                        <CreditCard class="h-4 w-4 text-primary" />
                        Transaction Information
                    </h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">Payment Method</p>
                            <p class="mt-0.5 text-sm font-medium">{{ payment.payment_method }}</p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">Paid At</p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ payment.paid_at ? formatDateTime(payment.paid_at) : 'Not paid yet' }}
                            </p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">Created At</p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ formatDateTime(payment.created_at) }}
                            </p>
                        </div>
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground">Booking Reference</p>
                            <p class="mt-0.5 text-sm font-medium font-mono">
                                #BOOKING-{{ payment.booking_id }}
                            </p>
                        </div>
                    </div>

                    <!-- Notes if any -->
                    <div v-if="payment.notes" class="rounded-lg border bg-muted/20 p-3">
                        <p class="text-xs text-muted-foreground flex items-center gap-1.5 mb-1">
                            <FileText class="h-3.5 w-3.5" />
                            Notes & Remarks
                        </p>
                        <p class="text-sm whitespace-pre-line text-foreground">
                            {{ payment.notes }}
                        </p>
                    </div>
                </div>

                <Separator />

                <!-- Associated Booking Details -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-foreground flex items-center gap-2">
                        <Sparkles class="h-4 w-4 text-primary" />
                        Associated Booking & Customer
                    </h3>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <!-- Customer Name -->
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground flex items-center gap-1">
                                <User class="h-3.5 w-3.5" />
                                Customer
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ payment.user_name || payment.booking?.user?.name || 'Guest Customer' }}
                            </p>
                        </div>

                        <!-- Email -->
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground flex items-center gap-1">
                                <Mail class="h-3.5 w-3.5" />
                                Email
                            </p>
                            <p class="mt-0.5 text-sm font-medium truncate">
                                {{ payment.user_email || payment.booking?.user?.email || '-' }}
                            </p>
                        </div>

                        <!-- Phone -->
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground flex items-center gap-1">
                                <Phone class="h-3.5 w-3.5" />
                                Phone Number
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ payment.user_phone || payment.booking?.phone_number || '-' }}
                            </p>
                        </div>

                        <!-- Guests Count -->
                        <div class="rounded-lg border p-3">
                            <p class="text-xs text-muted-foreground flex items-center gap-1">
                                <Users class="h-3.5 w-3.5" />
                                Number of Guests
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ payment.number_of_guests || payment.booking?.number_of_guests || 1 }} guest(s)
                            </p>
                        </div>

                        <!-- Package -->
                        <div class="rounded-lg border p-3 sm:col-span-2">
                            <p class="text-xs text-muted-foreground flex items-center gap-1">
                                <MapPin class="h-3.5 w-3.5" />
                                Package & Tour Date
                            </p>
                            <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-foreground">
                                    {{ payment.package_name || payment.booking?.tour_date?.package?.package_name || 'N/A' }}
                                    <span v-if="payment.destination || payment.booking?.tour_date?.package?.destination" class="text-xs font-normal text-muted-foreground">
                                        ({{ payment.destination || payment.booking?.tour_date?.package?.destination }})
                                    </span>
                                </span>
                                <Badge variant="secondary" class="text-xs flex items-center gap-1">
                                    <Calendar class="h-3 w-3" />
                                    {{ formatDate(payment.tour_date || payment.booking?.tour_date?.tour_date) }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Pickup Schedule if available -->
                        <div
                            v-if="payment.booking?.pickup_schedule"
                            class="rounded-lg border p-3 sm:col-span-2"
                        >
                            <p class="text-xs text-muted-foreground flex items-center gap-1">
                                <Clock class="h-3.5 w-3.5" />
                                Pickup Schedule
                            </p>
                            <p class="mt-0.5 text-sm font-medium">
                                {{ payment.booking.pickup_schedule.pickup_location?.name || 'Pickup Point' }}
                                at {{ payment.booking.pickup_schedule.pickup_time }}
                            </p>
                        </div>

                        <!-- Special Request if available -->
                        <div
                            v-if="payment.booking?.special_request"
                            class="rounded-lg border bg-muted/20 p-3 sm:col-span-2"
                        >
                            <p class="text-xs text-muted-foreground">Special Request</p>
                            <p class="mt-0.5 text-sm italic text-muted-foreground">
                                "{{ payment.booking.special_request }}"
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <DialogFooter class="flex items-center gap-2 pt-2">
                <div class="flex items-center gap-2 ml-auto">
                    <Button
                        variant="secondary"
                        type="button"
                        @click="emit('update:open', false)"
                    >
                        Close
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
