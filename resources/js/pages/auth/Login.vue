<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';

import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

import { register } from '@/routes';
import { store } from '@/routes/login';

/*
|--------------------------------------------------------------------------
| Options & Props
|--------------------------------------------------------------------------
*/
defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to continue.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div class="space-y-6">

        <!-- Status Message Banner -->
        <div
            v-if="status"
            class="flex items-center gap-2.5 rounded-lg border border-emerald-500/20 bg-emerald-50/80 p-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
            role="status"
        >
            <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
            <span>{{ status }}</span>
        </div>

        <!-- Login Form -->
        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
            <!-- Email Input -->
            <div class="space-y-2">
                <Label for="email" class="text-sm font-medium text-foreground">
                    Email address
                </Label>

                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="name@example.com"
                    class="h-10 transition-all focus-visible:ring-2"
                />

                <InputError :message="errors.email" />
            </div>

            <!-- Password Input -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label for="password" class="text-sm font-medium text-foreground">
                        Password
                    </Label>

                    <TextLink
                        v-if="canResetPassword"
                        href="/forgot-password"
                        class="text-xs font-medium text-muted-foreground hover:text-primary transition-colors"
                    >
                        Forgot password?
                    </TextLink>
                </div>

                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="h-10 transition-all focus-visible:ring-2"
                />

                <InputError :message="errors.password" />
            </div>

            <!-- Submit Button -->
            <Button
                type="submit"
                size="lg"
                class="w-full font-semibold shadow-sm transition-all"
                :disabled="processing"
            >
                <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                <span>{{ processing ? 'Signing in...' : 'Sign in' }}</span>
            </Button>

            <!-- Register / Sign Up Link -->
            <p class="text-center text-sm text-muted-foreground pt-1">
                Don't have an account?
                <TextLink
                    :href="register()"
                    class="font-medium text-foreground underline underline-offset-4 hover:text-primary transition-colors"
                >
                    Create an account
                </TextLink>
            </p>
            <p class="text-center text-sm text-muted-foreground">
                Tour operator?
                <TextLink href="/register/operator" class="font-medium text-foreground underline underline-offset-4">
                    Register an operator account
                </TextLink>
            </p>
        </Form>

    </div>
</template>