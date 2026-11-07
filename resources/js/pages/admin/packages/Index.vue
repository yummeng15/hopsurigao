<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import { Eye } from '@lucide/vue';

import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import PackageViewDialog from '@/pages/admin/packages/PackageViewDialog.vue';

import {
    index,
    data,
} from '@/routes/admin/packages';


/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface Package {
    id: number;
    package_name: string;
    destination: string;
    price: string;
    status: string;
    tour_dates_count: number;
}


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const tableRef = ref();
const viewingId = ref<number | null>(null);
const viewOpen = ref(false);


/*
|--------------------------------------------------------------------------
| Table Configuration
|--------------------------------------------------------------------------
*/

const columns = [
    { key: 'id', label: 'ID', sortable: true, width: '80px' },
    { key: 'package_name', label: 'Package', sortable: true },
    { key: 'destination', label: 'Destination', sortable: true },
    { key: 'price', label: 'Price', sortable: true },
    { key: 'tour_dates_count', label: 'Tour dates', sortable: false },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'operator_name', label: 'Tour Operator', sortable: false },
    { key: 'assignment_status', label: 'Assignment', sortable: false },
    { key: 'actions', label: 'Actions', sortable: false },
];


/*
|--------------------------------------------------------------------------
| View Actions
|--------------------------------------------------------------------------
*/

const openView = (pkg: Package) => {
    viewingId.value = pkg.id;
    viewOpen.value = true;
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
                title: 'Packages',
            },
        ],
    },
});
</script>


<template>

    <Head title="Packages" />

    <div class="px-4 py-6">

        <!-- ==========================================================
             Page Header
        =========================================================== -->

        <div>
            <Heading
                title="Packages"
                description="View and monitor packages. Super Admin oversight is view only."
            />
        </div>


        <!-- ==========================================================
             Data Table
        =========================================================== -->

        <div class="mt-6">

            <SimpleTable ref="tableRef" :fetch-url="data().url" :columns="columns" searchable enable-cache>

                <!-- Price Column -->
                <template v-slot:[`cell-price`]="{ row }">
                    ₱{{ row.price }}
                </template>

                <!-- Status Column -->
                <template v-slot:[`cell-status`]="{ row }">
                    <Badge :variant="row.status === 'active' ? 'default' : 'secondary'">
                        {{ row.status }}
                    </Badge>
                </template>

                <!-- Tour Operator Column -->
                <template v-slot:[`cell-operator_name`]="{ row }">
                    <span class="text-sm text-foreground">
                        {{ row.operator_name || '—' }}
                    </span>
                </template>

                <!-- Assignment Column -->
                <template v-slot:[`cell-assignment_status`]="{ row }">
                    <Badge :variant="row.assignment_status === 'Assigned' ? 'default' : 'secondary'">
                        {{ row.assignment_status || 'Unassigned' }}
                    </Badge>
                </template>

                <!-- Actions Column -->
                <template v-slot:[`cell-actions`]="{ row }">

                    <div class="inline-flex items-center gap-2">
                        <Button variant="outline" size="icon" title="View package" @click="openView(row)">
                            <Eye class="h-4 w-4" />
                        </Button>
                    </div>

                </template>

            </SimpleTable>

        </div>


        <!-- ==========================================================
             View Dialog
        =========================================================== -->

        <PackageViewDialog
            :open="viewOpen"
            :package-id="viewingId"
            @update:open="(v) => (viewOpen = v)"
        />

    </div>
</template>