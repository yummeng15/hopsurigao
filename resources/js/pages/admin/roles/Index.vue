<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';
import Heading from '@/components/Heading.vue';
import FormDialog from '@/components/crud/FormDialog.vue';
import Form from './Form.vue';

import {
    Pencil,
    Plus,
    Trash2,
    ShieldCheck,
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
} from '@/routes/admin/roles';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface RoleRow {
    id: number;
    name: string;
    description: string | null;
    users_count: number;
    is_protected: boolean;
    can_edit: boolean;
    can_delete: boolean;
}

/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const creating = ref(false);

const editingRole = ref<RoleRow | null>(null);

const deleteTarget = ref<RoleRow | null>(null);

const tableRef = ref();

const deleting = ref(false);

/*
|--------------------------------------------------------------------------
| Table Columns
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
        key: 'description',
        label: 'Description',
        sortable: true,
    },
    {
        key: 'users_count',
        label: 'Users',
        sortable: false,
    },
    {
        key: 'protection',
        label: 'Type',
        sortable: false,
    },
    {
        key: 'actions',
        label: 'Actions',
        sortable: false,
    },
];

/*
|--------------------------------------------------------------------------
| Normalize Table Row
|--------------------------------------------------------------------------
*/

const normalizeRole = (
    row: Record<string, unknown>
): RoleRow | null => {
    const id = Number(row.id);

    if (
        !Number.isSafeInteger(id) ||
        id <= 0 ||
        typeof row.name !== 'string'
    ) {
        return null;
    }

    return {
        id,

        name: row.name,

        description:
            typeof row.description === 'string'
                ? row.description
                : null,

        users_count:
            Number.isFinite(Number(row.users_count))
                ? Number(row.users_count)
                : 0,

        is_protected: row.is_protected === true,

        can_edit: row.can_edit === true,

        can_delete: row.can_delete === true,
    };
};

/*
|--------------------------------------------------------------------------
| Create
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
| Edit
|--------------------------------------------------------------------------
*/

const openEdit = (
    row: Record<string, unknown>
) => {
    const role = normalizeRole(row);

    if (!role || !role.can_edit) {
        return;
    }

    editingRole.value = role;
};

const closeEdit = () => {
    editingRole.value = null;
};

const handleEditSuccess = () => {
    editingRole.value = null;

    tableRef.value?.clearCache('current');
};

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/

const confirmDelete = (
    row: Record<string, unknown>
) => {
    const role = normalizeRole(row);

    if (!role || !role.can_delete) {
        return;
    }

    deleteTarget.value = role;
};

const performDelete = () => {
    if (!deleteTarget.value || deleting.value) {
        return;
    }

    deleting.value = true;

    router.delete(
        destroy(deleteTarget.value.id).url,
        {
            preserveScroll: true,

            onSuccess: () => {
                deleteTarget.value = null;

                tableRef.value?.clearCache('current');
            },

            onFinish: () => {
                deleting.value = false;
            },
        }
    );
};

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: index(),
                title: 'Roles',
            },
        ],
    },
});
</script>

<template>
    <Head title="Roles" />

    <div class="px-4 py-6">

        <!-- Header -->
        <div
            class="flex flex-wrap items-center justify-between gap-4"
        >
            <Heading
                title="Roles"
                description="View and manage system roles"
            />

            <Button @click="openCreate">
                <Plus class="mr-2 h-4 w-4" />
                New Role
            </Button>
        </div>

        <!-- Information -->
        <div
            class="mt-5 flex items-start gap-3 rounded-lg border bg-muted/30 p-4"
        >
            <ShieldCheck
                class="mt-0.5 h-5 w-5 shrink-0 text-primary"
            />

            <div class="text-sm">
                <p class="font-medium">
                    Protected System Roles
                </p>

                <p class="mt-1 text-muted-foreground">
                    Superadmin, Admin, and User are essential
                    system roles and cannot be edited or deleted.
                </p>
            </div>
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

                <!-- Type -->
                <template v-slot:[`cell-protection`]="{ row }">

                    <Badge
                        :variant="
                            row.is_protected
                                ? 'default'
                                : 'secondary'
                        "
                    >
                        {{
                            row.is_protected
                                ? 'Protected'
                                : 'Custom'
                        }}
                    </Badge>

                </template>

                <!-- Actions -->
                <template v-slot:[`cell-actions`]="{ row }">

                    <div
                        v-if="row.can_edit || row.can_delete"
                        class="inline-flex items-center gap-2"
                    >

                        <Button
                            v-if="row.can_edit"
                            variant="outline"
                            size="icon"
                            title="Edit role"
                            @click="openEdit(row)"
                        >
                            <Pencil class="h-4 w-4" />
                        </Button>

                        <Button
                            v-if="row.can_delete"
                            variant="destructive"
                            size="icon"
                            title="Delete role"
                            :disabled="deleting"
                            @click="confirmDelete(row)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>

                    </div>

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
            title="Create New Role"
            description="Add a custom role to the system."
            content-class="sm:max-w-2xl"
            @update:open="(value) => {
                if (!value) closeCreate();
            }"
        >

            <template #default="{ close }">

                <Form
                    :submit-action="store()"
                    submit-label="Create Role"
                    :on-cancel="close"
                    @success="handleCreateSuccess"
                />

            </template>

        </FormDialog>

        <!-- Edit Dialog -->
        <FormDialog
            :open="!!editingRole"
            title="Edit Role"
            :description="
                editingRole
                    ? `Update ${editingRole.name} details.`
                    : undefined
            "
            content-class="sm:max-w-2xl"
            @update:open="(value) => {
                if (!value) closeEdit();
            }"
        >

            <template #default="{ close }">

                <Form
                    v-if="editingRole"
                    :role="editingRole"
                    :submit-action="update(editingRole.id)"
                    submit-label="Save Changes"
                    :on-cancel="close"
                    @success="handleEditSuccess"
                />

            </template>

        </FormDialog>

        <!-- Delete Dialog -->
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
                        Delete role?
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