<!-- resources/js/pages/admin/payments/Index.vue -->
<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import { Download, Eye } from '@lucide/vue';

import Heading from '@/components/Heading.vue';
import PaymentViewDialog from './PaymentViewDialog.vue';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

import {
    index,
    data,
} from '@/routes/admin/payments';


/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface Payment {
    id: number;
    booking_id: number;
    user_name: string | null;
    user_email: string | null;
    user_phone: string | null;
    package_name: string | null;
    destination: string | null;
    tour_date: string | null;
    number_of_guests: number;
    amount: number;
    payment_method: string;
    payment_status: 'pending' | 'paid' | 'partial' | 'failed' | 'refunded' | 'cancelled';
    transaction_reference: string | null;
    notes: string | null;
    paid_at: string | null;
    created_at: string | null;
    booking?: any;
}


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

/**
 * Payment currently being viewed.
 */
const viewingPayment = ref<Payment | null>(null);
const tableRef = ref();


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function formatCurrency(amount: number | string): string {
    const val = typeof amount === 'string' ? parseFloat(amount) : amount;
    return isNaN(val)
        ? '0.00'
        : val.toLocaleString('en-PH', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
}

function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getStatusBadgeClass(status: string): string {
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


/*
|--------------------------------------------------------------------------
| Table Configuration
|--------------------------------------------------------------------------
*/

const columns = [
    {
        key: 'id',
        label: 'ID',
        sortable: true,
        width: '70px',
    },
    {
        key: 'booking_id',
        label: 'Booking #',
        sortable: true,
        width: '110px',
    },
    {
        key: 'customer',
        label: 'Customer',
        sortable: false,
    },
    {
        key: 'package_name',
        label: 'Package',
        sortable: false,
    },
    {
        key: 'amount',
        label: 'Amount',
        sortable: true,
    },
    {
        key: 'payment_method',
        label: 'Method',
        sortable: true,
    },
    {
        key: 'payment_status',
        label: 'Status',
        sortable: true,
    },
    {
        key: 'paid_at',
        label: 'Paid At',
        sortable: true,
    },
    {
        key: 'actions',
        label: 'Actions',
        sortable: false,
        width: '80px',
    },
];


/*
|--------------------------------------------------------------------------
| View Actions
|--------------------------------------------------------------------------
*/

/**
 * Open view modal.
 */
const openView = (payment: Payment) => {
    viewingPayment.value = payment;
};

/**
 * Close view modal.
 */
const closeView = () => {
    viewingPayment.value = null;
};


/*
|--------------------------------------------------------------------------
| Page Options
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Payments',
            },
        ],
    },
});
</script>


<template>

    <Head title="Payments" />

    <div class="px-4 py-6">

        <!-- ==========================================================
             Page Header
        =========================================================== -->

        <div class="flex items-center justify-between">

            <Heading
                title="Payments"
                description="View-only payment monitoring. Refunded means the Operator recorded a simulated PayMongo Test Mode refund after reviewing a tourist request. Super Admin does not approve refunds here."
            />

            <Button as-child variant="outline" class="shrink-0">
                <a href="/admin/payments/export">
                    <Download class="h-4 w-4" />
                    Export
                </a>
            </Button>

        </div>


        <!-- ==========================================================
             Data Table
        =========================================================== -->

        <div class="mt-6">

            <SimpleTable
                ref="tableRef"
                :fetch-url="data().url"
                :columns="columns"
                searchable
                enable-cache
            >

                <!-- Booking ID Column -->
                <template v-slot:[`cell-booking_id`]="{ row }">
                    <div class="flex items-center gap-1.5">
                        <span class="font-mono text-xs font-semibold">
                            #BOOK-{{ row.booking_id }}
                        </span>
                    </div>
                </template>

                <!-- Customer Column -->
                <template v-slot:[`cell-customer`]="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium text-sm text-foreground">
                            {{ row.user_name }}
                        </span>
                        <span class="text-xs text-muted-foreground">
                            {{ row.user_email }}
                        </span>
                    </div>
                </template>

                <!-- Package Column -->
                <template v-slot:[`cell-package_name`]="{ row }">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium">
                            {{ row.package_name }}
                        </span>
                        <span v-if="row.destination && row.destination !== 'N/A'" class="text-xs text-muted-foreground">
                            {{ row.destination }}
                        </span>
                    </div>
                </template>

                <!-- Amount Column -->
                <template v-slot:[`cell-amount`]="{ row }">
                    <span class="font-semibold text-sm">
                        ₱{{ formatCurrency(row.amount) }}
                    </span>
                </template>

                <!-- Method Column -->
                <template v-slot:[`cell-payment_method`]="{ row }">
                    <Badge variant="outline" class="text-xs font-normal">
                        {{ row.payment_method }}
                    </Badge>
                </template>

                <!-- Status Column -->
                <template v-slot:[`cell-payment_status`]="{ row }">
                    <Badge
                        variant="outline"
                        :class="[
                            'capitalize font-semibold text-xs',
                            getStatusBadgeClass(row.payment_status),
                        ]"
                    >
                        {{ row.payment_status }}
                    </Badge>
                </template>

                <!-- Paid At Column -->
                <template v-slot:[`cell-paid_at`]="{ row }">
                    <span class="text-xs text-muted-foreground">
                        {{ formatDate(row.paid_at) }}
                    </span>
                </template>

                <!-- ==================================================
                     Actions Column
                =================================================== -->

                <template v-slot:[`cell-actions`]="{ row }">

                    <div class="inline-flex items-center gap-1.5">

                        <Button
                            variant="outline"
                            size="icon"
                            title="View payment details"
                            @click="openView(row)"
                        >
                            <Eye class="h-4 w-4" />
                        </Button>

                    </div>

                </template>

            </SimpleTable>

        </div>


        <!-- ==========================================================
             View Dialog
        =========================================================== -->

        <PaymentViewDialog
            :open="!!viewingPayment"
            :payment="viewingPayment"
            @update:open="
                (value) => {
                    if (!value) {
                        closeView();
                    }
                }
            "
        />

    </div>

</template>
