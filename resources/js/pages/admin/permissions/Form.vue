<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';

type FormValues = {
    id?: number;
    name: string;
    label: string;
    group: string;
    description: string | null;
};

defineProps<{
    permission?: FormValues;
    submitAction: {
        url: string;
        method: 'post' | 'put';
    };
    submitLabel: string;
    cancelHref?: string;
    onCancel?: () => void;
}>();

const emit = defineEmits<{
    success: [];
}>();
</script>

<template>
    <Form :action="submitAction.url" :method="submitAction.method" class="flex flex-col gap-6"
        v-slot="{ errors, processing }" @success="emit('success')">
        <div class="grid grid-cols-1 gap-6">
            <div class="grid gap-2">
                <Label for="label">Label</Label>
                <Input id="label" name="label" :default-value="permission?.label" required
                    placeholder="e.g. Manage Users" />
                <InputError :message="errors.label" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" :default-value="permission?.name" required
                    placeholder="e.g. users.manage" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="group">Group</Label>
                <Input id="group" name="group" :default-value="permission?.group" required placeholder="e.g. Users" />
                <InputError :message="errors.group" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Description</Label>
                <Textarea id="description" name="description" :default-value="permission?.description ?? ''"
                    placeholder="Optional description" />
                <InputError :message="errors.description" />
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <Button v-if="cancelHref" as-child variant="outline" type="button" :disabled="processing">
                <Link :href="cancelHref">
                    Cancel
                </Link>
            </Button>

            <Button v-else-if="onCancel" variant="outline" type="button" :disabled="processing" @click="onCancel">
                Cancel
            </Button>

            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />

                {{ submitLabel }}
            </Button>
        </div>
    </Form>
</template>