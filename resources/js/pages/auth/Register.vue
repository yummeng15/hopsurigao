<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/
import { Form, Head } from '@inertiajs/vue3';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { login } from '@/routes';
import { store } from '@/routes/register';

/*
|--------------------------------------------------------------------------
| Options & Props
|--------------------------------------------------------------------------
*/
defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    },
});
</script>

<template>
    <Head title="Register" />

    <div class="space-y-6">
        <!-- Registration Form -->
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
            <!-- Full Name -->
            <div class="space-y-2">
                <Label for="name" class="text-sm font-medium text-foreground">
                    Full name
                </Label>

                <Input
                    id="name"
                    type="text"
                    name="name"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    placeholder="Juan Dela Cruz"
                    class="h-10 transition-all focus-visible:ring-2"
                />

                <InputError :message="errors.name" />
            </div>

            <!-- Email Address -->
            <div class="space-y-2">
                <Label for="email" class="text-sm font-medium text-foreground">
                    Email address
                </Label>

                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    placeholder="name@example.com"
                    class="h-10 transition-all focus-visible:ring-2"
                />

                <InputError :message="errors.email" />
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <Label for="password" class="text-sm font-medium text-foreground">
                    Password
                </Label>

                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    placeholder="••••••••"
                    :passwordrules="passwordRules"
                    class="h-10 transition-all focus-visible:ring-2"
                />

                <InputError :message="errors.password" />
            </div>

            <!-- Confirm Password -->
            <div class="space-y-2">
                <Label for="password_confirmation" class="text-sm font-medium text-foreground">
                    Confirm password
                </Label>

                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    placeholder="••••••••"
                    :passwordrules="passwordRules"
                    class="h-10 transition-all focus-visible:ring-2"
                />

                <InputError :message="errors.password_confirmation" />
            </div>

            <!-- Submit Button -->
            <Button
                type="submit"
                size="lg"
                class="w-full font-semibold shadow-sm transition-all"
                :tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                <span>{{ processing ? 'Creating account...' : 'Create account' }}</span>
            </Button>

            <!-- Login Link -->
            <p class="pt-1 text-center text-sm text-muted-foreground">
                Registering a tour business?
                <TextLink href="/register/operator" class="font-medium text-foreground underline underline-offset-4">
                    Operator account
                </TextLink>
            </p>

            <p class="pt-1 text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    :tabindex="6"
                    class="font-medium text-foreground underline underline-offset-4 transition-colors hover:text-primary"
                >
                    Log in
                </TextLink>
            </p>
        </Form>
    </div>
</template>