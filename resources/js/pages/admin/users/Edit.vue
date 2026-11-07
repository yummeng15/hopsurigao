<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import Form from './Form.vue';
import { index, update } from '@/routes/admin/users';

const props = defineProps<{
    user: { id: number; name: string; email: string; role_id: number | null };
    roles: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'Edit' },
        ],
    },
});
</script>

<template>
    <Head title="Edit User" />
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
                    <Heading title="Edit User" description="Update user details" />
                </CardHeader>
                <CardContent>
                    <Form
                        :roles="roles"
                        :user="user"
                        :submit-action="update(user.id)"
                        :cancel-href="index().url"
                        submit-label="Save Changes"
                    />
                </CardContent>
            </Card>
        </div>
    </div>
</template>