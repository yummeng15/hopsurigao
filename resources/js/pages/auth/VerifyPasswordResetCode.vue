<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';

import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Enter reset code',
        description: 'Type the 6-digit code sent to your email',
    },
});

defineProps<{
    email: string;
    status?: string;
}>();
</script>

<template>
    <Head title="Enter reset code" />

    <div class="space-y-6">
        <div
            v-if="status"
            class="flex items-center gap-2.5 rounded-lg border border-emerald-500/20 bg-emerald-50/80 p-3 text-sm font-medium text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-300"
            role="status"
        >
            <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
            <span>{{ status }}</span>
        </div>

        <p class="text-sm text-muted-foreground">
            We sent a code to
            <span class="font-medium text-foreground">{{ email }}</span>.
            It expires in 10 minutes.
        </p>

        <Form
            action="/forgot-password/verify"
            method="post"
            v-slot="{ errors, processing }"
            class="space-y-5"
        >
            <div class="space-y-2">
                <Label for="code" class="text-sm font-medium text-foreground">
                    6-digit code
                </Label>

                <Input
                    id="code"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    minlength="6"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="123456"
                    class="h-10 tracking-[0.35em] transition-all focus-visible:ring-2"
                />

                <InputError :message="errors.code" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="w-full font-semibold shadow-sm"
                :disabled="processing"
            >
                <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                <span>{{ processing ? 'Checking...' : 'Verify code' }}</span>
            </Button>

            <div class="space-y-2 pt-1 text-center text-sm text-muted-foreground">
                <p>
                    Didn’t get it?
                    <Link
                        href="/forgot-password"
                        class="font-medium text-foreground underline underline-offset-4 hover:text-primary"
                    >
                        Request a new code
                    </Link>
                </p>
                <p>
                    Or return to
                    <TextLink
                        href="/login"
                        class="font-medium text-foreground underline underline-offset-4 hover:text-primary"
                    >
                        Log in
                    </TextLink>
                </p>
            </div>
        </Form>
    </div>
</template>
