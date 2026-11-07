<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import SimpleTable from '@/components/tables/DataTable.vue';

import Heading from '@/components/Heading.vue';

import {
    index,
    data,
} from '@/routes/admin/users';


/*
|--------------------------------------------------------------------------
| State
|--------------------------------------------------------------------------
*/

const tableRef = ref();


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
        key: 'email',
        label: 'Email',
        sortable: true,
    },
    {
        key: 'role_name',
        label: 'Role',
        sortable: false,
    },
];


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
                title: 'Users',
            },
        ],
    },
});
</script>


<template>
    <Head title="Users" />

    <div class="px-4 py-6">
        <div>
            <Heading
                title="Users"
                description="View and monitor user accounts. Super Admin oversight is view only."
            />
        </div>

        <div class="mt-6">
            <SimpleTable ref="tableRef" :fetch-url="data().url" :columns="columns" searchable enable-cache />
        </div>
    </div>
</template>