<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-4 sm:p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-medium"
            >
                <AppLogo />
            </Link>

            <Card class="rounded-xl">
                <CardHeader
                    v-if="title || description"
                    class="px-6 pt-8 pb-0 text-center sm:px-10"
                >
                    <CardTitle v-if="title" class="text-xl">{{ title }}</CardTitle>
                    <CardDescription v-if="description">
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="px-6 py-8 sm:px-10">
                    <slot />
                </CardContent>
            </Card>
        </div>
    </div>
</template>