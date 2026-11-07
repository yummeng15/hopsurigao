<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import { Archive, Eye, Trash2 } from '@lucide/vue';

import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface ArchivedBooking {
    id: number;
    user?: {
        name: string;
        email: string;
    } | null;
    tour_date?: {
        tour_date: string;
        package?: {
            package_name: string;
            destination: string;
        } | null;
    } | null;
    pickup_schedule?: {
        pickup_time: string;
        pickup_location?: {
            name: string;
        } | null;
    } | null;
    number_of_guests: number;
    phone_number: string | null;
    nationality: string | null;
    booking_status: string;
    archived_at: string | null;
    created_at: string | null;
}

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const deleteTarget = ref<ArchivedBooking | null>(null);
const tableRef = ref();
const deleting = ref(false);

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
        width: '80px',
    },
    {
        key: 'customer',
        label: 'Customer',
        sortable: false,
    },
    {
        key: 'package',
        label: 'Tour Package',
        sortable: false,
    },
    {
        key: 'tour_date',
        label: 'Tour Date',
        sortable: false,
    },
    {
        key: 'number_of_guests',
        label: 'Guests',
        sortable: true,
        width: '90px',
    },
    {
        key: 'booking_status',
        label: 'Prior Status',
        sortable: true,
    },
    {
        key: 'archived_at',
        label: 'Archived Date',
        sortable: true,
    },
    {
        key: 'actions',
        label: 'Actions',
        sortable: false,
        width: '120px',
    },
];

/*
|--------------------------------------------------------------------------
| Delete Actions
|--------------------------------------------------------------------------
*/

const confirmDelete = (booking: ArchivedBooking) => {
    if (booking.booking_status === 'completed') return;
    deleteTarget.value = booking;
};

const performDelete = () => {
    if (!deleteTarget.value || deleting.value) {
        return;
    }

    const bookingId = deleteTarget.value.id;
    deleting.value = true;

    router.delete(`/admin/archived-bookings/${bookingId}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
            tableRef.value?.clearCache('current');
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
};

function formatDate(date: string | null): string {
    if (!date) return '—';

    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return date;

    return parsed.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatDateTime(date: string | null): string {
    if (!date) return '—';

    const parsed = new Date(date);
    if (Number.isNaN(parsed.getTime())) return date;

    return parsed.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

/*
|--------------------------------------------------------------------------
| Page Options
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: '/superadmin/dashboard',
                title: 'Dashboard',
            },
            {
                href: '/admin/archived-bookings',
                title: 'Archived Bookings',
            },
        ],
    },
});
</script>

<template>
    <Head title="Archived Bookings" />

    <div class="px-4 py-6">
        <!-- ==========================================================
             PAGE HEADER
        =========================================================== -->
        <Heading
            title="Archived Bookings"
            description="Manage customer-archived past bookings, inspect excursion history, or permanently delete records."
        />

        <!-- ==========================================================
             DATA TABLE
        =========================================================== -->
        <div class="mt-6">
            <SimpleTable
                ref="tableRef"
                fetch-url="/admin/archived-bookings/data"
                :columns="columns"
                searchable
                enable-cache
            >
                <!-- Customer -->
                <template v-slot:[`cell-customer`]="{ row }">
                    <div>
                        <p class="font-medium text-foreground">
                            {{ row.user?.name || '—' }}
                        </p>
                        <p v-if="row.user?.email" class="text-xs text-muted-foreground">
                            {{ row.user.email }}
                        </p>
                    </div>
                </template>

                <!-- Package -->
                <template v-slot:[`cell-package`]="{ row }">
                    <div>
                        <p class="font-medium text-foreground">
                            {{ row.tour_date?.package?.package_name || '—' }}
                        </p>
                        <p v-if="row.tour_date?.package?.destination" class="text-xs text-muted-foreground">
                            {{ row.tour_date.package.destination }}
                        </p>
                    </div>
                </template>

                <!-- Tour Date -->
                <template v-slot:[`cell-tour_date`]="{ row }">
                    <span class="text-sm font-medium">
                        {{ formatDate(row.tour_date?.tour_date) }}
                    </span>
                </template>

                <!-- Guests -->
                <template v-slot:[`cell-number_of_guests`]="{ row }">
                    <span class="font-semibold text-foreground">
                        {{ row.number_of_guests }}
                    </span>
                </template>

                <!-- Status -->
                <template v-slot:[`cell-booking_status`]="{ row }">
                    <Badge
                        :variant="
                            row.booking_status === 'completed'
                                ? 'secondary'
                                : row.booking_status === 'cancelled'
                                    ? 'destructive'
                                    : 'outline'
                        "
                    >
                        {{ row.booking_status }}
                    </Badge>
                </template>

                <!-- Archived Date -->
                <template v-slot:[`cell-archived_at`]="{ row }">
                    <span class="text-xs text-muted-foreground">
                        {{ formatDateTime(row.archived_at) }}
                    </span>
                </template>

                <!-- Actions -->
                <template v-slot:[`cell-actions`]="{ row }">
                    <div class="inline-flex items-center gap-1.5">
                        <Button as-child variant="outline" size="icon" class="h-8 w-8" title="View Booking Details">
                            <Link :href="`/admin/bookings/${row.id}`">
                                <Eye class="h-4 w-4" />
                            </Link>
                        </Button>

                        <Button
                            variant="destructive"
                            size="icon"
                            class="h-8 w-8"
                            :disabled="row.booking_status === 'completed'"
                            :class="row.booking_status === 'completed' ? 'opacity-40 cursor-not-allowed hover:bg-destructive' : ''"
                            :title="row.booking_status === 'completed' ? 'Completed bookings cannot be deleted (preserved for safety & audit)' : 'Permanently Delete'"
                            @click="row.booking_status !== 'completed' && confirmDelete(row)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </template>
            </SimpleTable>
        </div>

        <!-- ==========================================================
             PERMANENT DELETE CONFIRMATION MODAL
        =========================================================== -->
        <Dialog
            :open="!!deleteTarget"
            @update:open="(value) => {
                if (!value && !deleting) {
                    deleteTarget = null;
                }
            }"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-destructive/10 text-destructive mb-2">
                        <Trash2 class="h-6 w-6" />
                    </div>
                    <DialogTitle>Permanently Delete Archived Booking?</DialogTitle>
                    <DialogDescription class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm">
                        This action will permanently delete <strong>Archived Booking #{{ deleteTarget?.id }}</strong>
                        ({{ deleteTarget?.tour_date?.package?.package_name || 'Excursion' }}) and erase all related uploaded receipts from storage.
                        <br /><br />
                        <strong class="text-destructive">Warning: This action cannot be reversed.</strong>
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter class="mt-4 gap-2 sm:gap-0">
                    <Button variant="outline" :disabled="deleting" @click="deleteTarget = null">
                        Cancel
                    </Button>

                    <Button variant="destructive" :disabled="deleting" @click="performDelete">
                        <Trash2 v-if="!deleting" class="mr-2 h-4 w-4" />
                        <span>{{ deleting ? 'Deleting...' : 'Delete Permanently' }}</span>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>

