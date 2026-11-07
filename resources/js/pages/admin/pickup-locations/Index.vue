<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import Heading from '@/components/Heading.vue';
import FormDialog from '@/components/crud/FormDialog.vue';

import Form, {
    type PickupLocationValues,
} from './Form.vue';

import {
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';

import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

import {
    index,
    store,
    update,
    destroy,
    data,
} from '@/routes/admin/pickup-locations';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

type PickupLocationRow = PickupLocationValues & {
    is_shared: boolean;
    scope_label: string;
    can_manage: boolean;
};

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const creating = ref(false);

const editingLocation = ref<PickupLocationValues | null>(null);

const deleteTarget = ref<PickupLocationRow | null>(null);

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
        key: 'name',
        label: 'Name',
        sortable: true,
    },
    {
        key: 'address',
        label: 'Address',
        sortable: false,
    },
    {
        key: 'scope',
        label: 'Ownership',
        sortable: false,
    },
    {
        key: 'status',
        label: 'Status',
        sortable: true,
    },
    {
        key: 'actions',
        label: 'Actions',
        sortable: false,
    },
];

/*
|--------------------------------------------------------------------------
| Convert DataTable Row to PickupLocationRow
|--------------------------------------------------------------------------
|
| DataTable provides Record<string, unknown>.
|
| We validate and convert its values before using them
| in the Edit and Delete functions.
|
*/

const normalizeLocationRow = (
    row: Record<string, unknown>
): PickupLocationRow | null => {
    const id = Number(row.id);

    if (
        !Number.isSafeInteger(id) ||
        id <= 0 ||
        typeof row.name !== 'string'
    ) {
        return null;
    }

    const latitude =
        typeof row.latitude === 'number' ||
        typeof row.latitude === 'string'
            ? row.latitude
            : null;

    const longitude =
        typeof row.longitude === 'number' ||
        typeof row.longitude === 'string'
            ? row.longitude
            : null;

    return {
        id,

        name: row.name,

        address:
            typeof row.address === 'string'
                ? row.address
                : null,

        latitude,

        longitude,

        description:
            typeof row.description === 'string'
                ? row.description
                : null,

        status:
            typeof row.status === 'string'
                ? row.status
                : 'active',

        is_shared: row.is_shared === true,

        scope_label:
            typeof row.scope_label === 'string'
                ? row.scope_label
                : 'Unknown',

        can_manage: row.can_manage === true,
    };
};

/*
|--------------------------------------------------------------------------
| Create Actions
|--------------------------------------------------------------------------
*/

const openCreate = () => {
    creating.value = true;
};

const closeCreate = () => {
    creating.value = false;
};

const handleCreateSuccess = () => {
    creating.value = false;

    tableRef.value?.clearCache('current');
};

/*
|--------------------------------------------------------------------------
| Edit Actions
|--------------------------------------------------------------------------
*/

const openEdit = (
    row: Record<string, unknown>
) => {
    const location = normalizeLocationRow(row);

    if (!location || !location.can_manage) {
        return;
    }

    editingLocation.value = location;
};

const closeEdit = () => {
    editingLocation.value = null;
};

const handleEditSuccess = () => {
    editingLocation.value = null;

    tableRef.value?.clearCache('current');
};

/*
|--------------------------------------------------------------------------
| Delete Actions
|--------------------------------------------------------------------------
*/

const confirmDelete = (
    row: Record<string, unknown>
) => {
    const location = normalizeLocationRow(row);

    if (!location || !location.can_manage) {
        return;
    }

    deleteTarget.value = location;
};

const performDelete = () => {
    if (!deleteTarget.value || deleting.value) {
        return;
    }

    const locationId = deleteTarget.value.id;

    deleting.value = true;

    router.delete(destroy(locationId).url, {
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

/*
|--------------------------------------------------------------------------
| Page Layout
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Pickup Locations',
            },
        ],
    },
});
</script>

<template>
    <Head title="Pickup Locations" />

    <div class="px-4 py-6">

        <!-- Page Header -->
        <div
            class="flex flex-wrap items-center justify-between gap-4"
        >
            <Heading
                title="Pickup Locations"
                description="Manage pickup points for your tour schedules"
            />

            <Button @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                New Location
            </Button>
        </div>

        <!-- Information -->
        <div
            class="mt-5 rounded-lg border bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
        >
            Shared locations are available to all operators.
            You can edit or delete only the locations you manage.
        </div>

        <!-- Data Table -->
        <div class="mt-6">

            <SimpleTable
                ref="tableRef"
                :fetch-url="data().url"
                :columns="columns"
                searchable
                enable-cache
            >

                <!-- Ownership Column -->
                <template v-slot:[`cell-scope`]="{ row }">

                    <Badge
                        :variant="
                            row.is_shared
                                ? 'secondary'
                                : 'outline'
                        "
                    >
                        {{ row.scope_label }}
                    </Badge>

                </template>

                <!-- Status Column -->
                <template v-slot:[`cell-status`]="{ row }">

                    <Badge
                        :variant="
                            row.status === 'active'
                                ? 'default'
                                : 'secondary'
                        "
                    >
                        {{ row.status }}
                    </Badge>

                </template>

                <!-- Actions Column -->
                <template v-slot:[`cell-actions`]="{ row }">

                    <div
                        v-if="row.can_manage"
                        class="inline-flex items-center gap-2"
                    >

                        <!-- Edit Button -->
                        <Button
                            variant="outline"
                            size="icon"
                            title="Edit location"
                            @click="openEdit(row)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>

                        <!-- Delete Button -->
                        <Button
                            variant="destructive"
                            size="icon"
                            title="Delete location"
                            :disabled="deleting"
                            @click="confirmDelete(row)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>

                    </div>

                    <!-- Read Only -->
                    <span
                        v-else
                        class="text-sm text-muted-foreground"
                    >
                        View only
                    </span>

                </template>

            </SimpleTable>

        </div>

        <!-- Create Dialog -->
        <FormDialog
            :open="creating"
            title="New Pickup Location"
            description="Add a new pickup point."
            content-class="sm:max-w-2xl"
            @update:open="(value) => {
                if (!value) {
                    closeCreate();
                }
            }"
        >

            <template #default="{ close }">

                <Form
                    :submit-action="store()"
                    submit-label="Create Location"
                    :on-cancel="close"
                    @success="handleCreateSuccess"
                />

            </template>

        </FormDialog>

        <!-- Edit Dialog -->
        <FormDialog
            :open="!!editingLocation"
            title="Edit Pickup Location"
            content-class="sm:max-w-2xl"
            :description="
                editingLocation
                    ? `Update ${editingLocation.name} details.`
                    : undefined
            "
            @update:open="(value) => {
                if (!value) {
                    closeEdit();
                }
            }"
        >

            <template #default="{ close }">

                <Form
                    v-if="editingLocation"
                    :pickup-location="editingLocation"
                    :submit-action="update(editingLocation.id)"
                    submit-label="Save Changes"
                    :on-cancel="close"
                    @success="handleEditSuccess"
                />

            </template>

        </FormDialog>

        <!-- Delete Confirmation -->
        <Dialog
            :open="!!deleteTarget"
            @update:open="(value) => {
                if (!value && !deleting) {
                    deleteTarget = null;
                }
            }"
        >

            <DialogContent>

                <DialogHeader>

                    <DialogTitle>
                        Delete pickup location?
                    </DialogTitle>

                    <DialogDescription>
                        This will permanently delete
                        <strong>
                            {{ deleteTarget?.name }}
                        </strong>.
                        This action cannot be undone.
                    </DialogDescription>

                </DialogHeader>

                <DialogFooter>

                    <Button
                        variant="outline"
                        :disabled="deleting"
                        @click="deleteTarget = null"
                    >
                        Cancel
                    </Button>

                    <Button
                        variant="destructive"
                        :disabled="deleting"
                        @click="performDelete"
                    >

                        <Trash2
                            v-if="!deleting"
                            class="mr-2 h-4 w-4"
                        />

                        {{
                            deleting
                                ? 'Deleting...'
                                : 'Delete'
                        }}

                    </Button>

                </DialogFooter>

            </DialogContent>

        </Dialog>

    </div>
</template>