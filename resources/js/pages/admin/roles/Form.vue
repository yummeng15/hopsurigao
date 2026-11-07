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
    description: string | null;
};

defineProps<{
    role?: FormValues;
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
    <Form
        :action="submitAction.url"
        :method="submitAction.method"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
        @success="emit('success')"
    >
        <div class="grid grid-cols-1 gap-6">
            <!-- Name -->
            <div class="grid gap-2">
                <Label for="name">
                    Name
                </Label>

                <Input
                    id="name"
                    name="name"
                    :default-value="role?.name"
                    required
                    placeholder="e.g. Admin"
                />

                <InputError :message="errors.name" />
            </div>

            <!-- Description -->
            <div class="grid gap-2">
                <Label for="description">
                    Description
                </Label>

                <Textarea
                    id="description"
                    name="description"
                    :default-value="role?.description ?? ''"
                    placeholder="Optional description"
                />

                <InputError :message="errors.description" />
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <Button
                v-if="cancelHref"
                as-child
                variant="outline"
                type="button"
                :disabled="processing"
            >
                <Link :href="cancelHref">
                    Cancel
                </Link>
            </Button>

            <Button
                v-else-if="onCancel"
                variant="outline"
                type="button"
                :disabled="processing"
                @click="onCancel"
            >
                Cancel
            </Button>

            <Button
                type="submit"
                :disabled="processing"
            >
                <Spinner v-if="processing" />

                {{ submitLabel }}
            </Button>
        </div>
    </Form>
</template>