<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Forgot password',
        description: 'Enter your email to receive a 6-digit reset code',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Forgot password" />

    <div class="space-y-6">
        <div
            v-if="status"
            class="flex items-center gap-2.5 rounded-lg border border-emerald-500/20 bg-emerald-50/80 p-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
            role="status"
        >
            <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
            <span>{{ status }}</span>
        </div>

        <Form
            action="/forgot-password"
            method="post"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
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

            <Button
                type="submit"
                size="lg"
                class="w-full font-semibold shadow-sm transition-all"
                :disabled="processing"
                data-test="email-password-reset-code-button"
            >
                <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                <span>{{ processing ? 'Sending code...' : 'Send 6-digit code' }}</span>
            </Button>

            <p class="pt-1 text-center text-sm text-muted-foreground">
                Or, return to
                <TextLink
                    href="/login"
                    class="font-medium text-foreground underline underline-offset-4 transition-colors hover:text-primary"
                >
                    Log in
                </TextLink>
            </p>
        </Form>
    </div>
</template>
