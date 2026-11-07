<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import { Pencil, Plus, Trash2 } from '@lucide/vue';

import Heading from '@/components/Heading.vue';
import FormDialog from '@/components/crud/FormDialog.vue';
import Form from './Form.vue';

import { Button } from '@/components/ui/button';

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
} from '@/routes/admin/permissions';


/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface Permission {
    id: number;
    label: string;
    name: string;
    group: string;
    description: string | null;
}


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/


const creating = ref(false);


const editingPermission = ref<Permission | null>(null);


const deleteTarget = ref<Permission | null>(null);

/**
 * SimpleTable reference.
 */
const tableRef = ref();

/**
 * Prevent duplicate delete requests.
 */
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
    { key: 'label', label: 'Label', sortable: true },
    {
        key: 'name',
        label: 'Name',
        sortable: true,
    },
    { key: 'group', label: 'Group', sortable: true },
    {
        key: 'description',
        label: 'Description',
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
| Create Actions
|--------------------------------------------------------------------------
*/

/**
 * Open create modal.
 */
const openCreate = () => {
    creating.value = true;
};

/**
 * Close create modal.
 */
const closeCreate = () => {
    creating.value = false;
};

/**
 * Called after successful creation.
 */
const handleCreateSuccess = () => {
    creating.value = false;

    // Refresh SimpleTable data
    tableRef.value?.clearCache('current');
};


/*
|--------------------------------------------------------------------------
| Edit Actions
|--------------------------------------------------------------------------
*/

/**
 * Open edit modal.
 */
const openEdit = (permission: Permission) => {
    editingPermission.value = permission;
};

/**
 * Close edit modal.
 */
const closeEdit = () => {
    editingPermission.value = null;
};

/**
 * Called after successful update.
 */
const handleEditSuccess = () => {
    editingPermission.value = null;

    // Refresh SimpleTable data
    tableRef.value?.clearCache('current');
};


/*
|--------------------------------------------------------------------------
| Delete Actions
|--------------------------------------------------------------------------
*/

/**
 * Open delete confirmation dialog.
 */
const confirmDelete = (permission: Permission) => {
    deleteTarget.value = permission;
};


const performDelete = () => {
    if (!deleteTarget.value || deleting.value) {
        return;
    }

    const permissionId = deleteTarget.value.id;

    deleting.value = true;

    router.delete(destroy(permissionId).url, {
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
| Page Options
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Permissions',
            },
        ],
    },
});
</script>


<template>

    <Head title="Permissions" />

    <div class="px-4 py-6">

        <!-- ==========================================================
             Page Header
        =========================================================== -->

        <div class="flex items-center justify-between">

            <Heading title="Permissions" description="Manage permissions" />

            <Button @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                New Permission
            </Button>

        </div>


        <!-- ==========================================================
             Data Table
        =========================================================== -->

        <div class="mt-6">

            <SimpleTable ref="tableRef" :fetch-url="data().url" :columns="columns" searchable enable-cache>

                <!-- ==================================================
                     Actions Column
                =================================================== -->

                <template v-slot:[`cell-actions`]="{ row }">

                    <div class="inline-flex items-center gap-2">

                        <!-- Edit -->
                        <Button variant="outline" size="icon" title="Edit permission" @click="openEdit(row)">
                            <Pencil class="h-4 w-4" />
                        </Button>


                        <!-- Delete -->
                        <Button variant="destructive" size="icon" title="Delete permission" :disabled="deleting"
                            @click="confirmDelete(row)">
                            <Trash2 class="h-4 w-4" />
                        </Button>

                    </div>

                </template>

            </SimpleTable>

        </div>


        <!-- ==========================================================
             Create Dialog
        =========================================================== -->

        <FormDialog :open="creating" title="Create New Permission" content-class="sm:max-w-2xl"
            description="Add a new permission to the system." @update:open="
                (value) => {
                    if (!value) {
                        closeCreate();
                    }
                }
            ">

            <template #default="{ close }">

                <Form :submit-action="store()" submit-label="Create Permission" :on-cancel="close"
                    @success="handleCreateSuccess" />

            </template>

        </FormDialog>


        <!-- ==========================================================
             Edit Dialog
        =========================================================== -->

        <FormDialog :open="!!editingPermission" title="Edit Permission" content-class="sm:max-w-2xl" :description="editingPermission
                ? `Update ${editingPermission.name} details.`
                : undefined
            " @update:open="
                (value) => {
                    if (!value) {
                        closeEdit();
                    }
                }
            ">

            <template #default="{ close }">

                <Form v-if="editingPermission" :permission="editingPermission" :submit-action="update(editingPermission.id)"
                    submit-label="Save Changes" :on-cancel="close" @success="handleEditSuccess" />

            </template>

        </FormDialog>


        <!-- ==========================================================
             Delete Confirmation
        =========================================================== -->

        <Dialog :open="!!deleteTarget" @update:open="
            (value) => {
                if (!value && !deleting) {
                    deleteTarget = null;
                }
            }
        ">

            <DialogContent>

                <DialogHeader>

                    <DialogTitle>
                        Delete permission?
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

                    <!-- Cancel -->
                    <Button variant="outline" :disabled="deleting" @click="deleteTarget = null">
                        Cancel
                    </Button>


                    <!-- Delete -->
                    <Button variant="destructive" :disabled="deleting" @click="performDelete">

                        <Trash2 v-if="!deleting" class="mr-2 h-4 w-4" />

                        {{ deleting ? 'Deleting...' : 'Delete' }}

                    </Button>

                </DialogFooter>

            </DialogContent>

        </Dialog>

    </div>

</template>