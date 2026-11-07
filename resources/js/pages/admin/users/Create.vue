//resources/js/pages/admin/users/Create.vue
<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import Form from './Form.vue';
import { index, store } from '@/routes/admin/users';

defineProps<{
    roles: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'Create' },
        ],
    },
});
</script>

<template>
    <Head title="Create User" />
    <div class="px-4 py-6">
        <div class="max-w-xl">
            <Button as-child variant="ghost" size="sm" class="mb-4 -ml-2">
                <Link :href="index()">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Back to Users
                </Link>
            </Button>

            <Card>
                <CardHeader>
                    <Heading title="Create User" description="Add a new user to the system" />
                </CardHeader>
                <CardContent>
                    <Form
                        :roles="roles"
                        :submit-action="store()"
                        :cancel-href="index().url"
                        submit-label="Create User"
                    />
                </CardContent>
            </Card>
        </div>
    </div>
</template>