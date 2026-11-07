<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Register as an operator',
        description: 'Create an Operator Admin account, then submit your business for approval.',
    },
});
</script>

<template>
    <Head title="Operator registration" />

    <Form
        action="/register/operator"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input id="name" name="name" required autocomplete="name" />
            <InputError :message="errors.name" />
        </div>
        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <Input id="email" type="email" name="email" required autocomplete="email" />
            <InputError :message="errors.email" />
        </div>
        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput id="password" name="password" required autocomplete="new-password" />
            <InputError :message="errors.password" />
        </div>
        <div class="grid gap-2">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput id="password_confirmation" name="password_confirmation" required autocomplete="new-password" />
        </div>
        <Button type="submit" class="w-full" :disabled="processing">
            <Spinner v-if="processing" class="mr-2 h-4 w-4" />
            Create operator account
        </Button>
        <p class="text-center text-sm text-muted-foreground">
            Already registered?
            <TextLink :href="login()" class="underline">Log in</TextLink>
        </p>
    </Form>
</template>
