<script setup lang="ts">
import { Sparkles, UserCheck } from '@lucide/vue';
</script>

<template>
    <div class="relative min-h-screen overflow-x-clip bg-background pb-20 text-foreground">
        <!-- Ambient Background Glows -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-[-10rem] h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute right-[-10rem] top-64 h-72 w-72 rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="mx-auto max-w-4xl px-5 py-8 lg:px-8">
            <!-- Header Badge & Title -->
            <div class="mb-8">
                <div class="inline-flex items-center gap-2 rounded-full border bg-background/80 px-3 py-1 text-xs font-medium backdrop-blur shadow-xs">
                    <Sparkles class="h-3.5 w-3.5 text-primary" />
                    <span>Account Preferences</span>
                </div>

                <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">
                    Settings
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Manage your personal profile, security credentials, and account configurations.
                </p>
            </div>

            <!-- Settings Content Container -->
            <div class="rounded-3xl border bg-card/60 p-6 backdrop-blur-md shadow-sm sm:p-8">
                <section class="space-y-10">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>