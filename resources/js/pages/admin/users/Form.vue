//resources/js/pages/admin/users/Form.vue
<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { ref, watch } from 'vue';
import type { AcceptableValue } from 'reka-ui';

type RoleOption = { id: number; name: string };
type UserFormValues = { id?: number; name: string; email: string; role_id: number | string | null };

const props = defineProps<{
    roles: RoleOption[];
    user?: UserFormValues;
    submitAction: { url: string; method: 'post' | 'put' };
    submitLabel: string;
    cancelHref?: string;
    onCancel?: () => void;
}>();

const emit = defineEmits<{ success: [] }>();

// Track selected role separately
const selectedRoleId = ref<string | undefined>(
    props.user?.role_id ? String(props.user.role_id) : undefined
);

// Watch for changes to user prop (when editing different users)
watch(() => props.user, (newUser) => {
    selectedRoleId.value = newUser?.role_id ? String(newUser.role_id) : undefined;
}, { immediate: true });

// Handle role change - accepts AcceptableValue type
const handleRoleChange = (value: AcceptableValue) => {
    // Convert AcceptableValue to string or undefined
    if (value === null || value === undefined) {
        selectedRoleId.value = undefined;
    } else {
        selectedRoleId.value = String(value);
    }
};
</script>

<template>
    <Form :action="submitAction.url" :method="submitAction.method" class="flex flex-col gap-6"
        v-slot="{ errors, processing }" @success="emit('success')">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-1">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input id="name" name="name" :default-value="user?.name" required autocomplete="name"
                    placeholder="Full name" />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input id="email" type="email" name="email" :default-value="user?.email" required autocomplete="email"
                    placeholder="email@example.com" />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="role_id">Role</Label>
                <!-- Hidden input to submit the role_id value -->
                <input type="hidden" name="role_id" :value="selectedRoleId" />

                <Select :model-value="selectedRoleId" @update:model-value="handleRoleChange">
                    <SelectTrigger id="role_id" class="w-full">
                        <SelectValue placeholder="Select a role" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="role in roles" :key="role.id" :value="role.id.toString()">
                            {{ role.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.role_id" />
            </div>

            <div class="grid gap-2">
                <Label for="password">
                    Password
                    <span v-if="user" class="text-muted-foreground">(leave blank to keep current)</span>
                </Label>
                <Input id="password" type="password" name="password" :required="!user" autocomplete="new-password"
                    placeholder="Password" />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <Input id="password_confirmation" type="password" name="password_confirmation"
                    autocomplete="new-password" placeholder="Confirm password" />
                <InputError :message="errors.password_confirmation" />
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <Button v-if="cancelHref" as-child variant="outline" type="button" :disabled="processing">
                <Link :href="cancelHref">Cancel</Link>
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