<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

import {
    CalendarDays,
    Compass,
    QrCode,
    ShieldCheck,
    Ship,
    Sparkles,
    Waves,
} from '@lucide/vue';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppLogo from '@/components/AppLogo.vue';
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name || 'HopSurigao';

defineProps<{
    title?: string;
    description?: string;
}>();

/*
|--------------------------------------------------------------------------
| Left Background Image & Error Fallback
|--------------------------------------------------------------------------
*/

const defaultHeroImage = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2073&q=80';
const backupHeroImage = 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&w=2000&q=80';

const bgImage = ref(defaultHeroImage);

function handleImageError() {
    if (bgImage.value !== backupHeroImage) {
        bgImage.value = backupHeroImage;
    }
}
</script>

<template>
    <div class="grid min-h-dvh grid-cols-1 bg-background lg:grid-cols-2 selection:bg-primary/20 selection:text-primary">
        <!-- ==========================================================
             LEFT SIDE: TROPICAL IMAGE BACKGROUND & BRANDING SHOWCASE
        =========================================================== -->
        <div class="relative hidden overflow-hidden bg-slate-950 lg:flex flex-col justify-between p-10 xl:p-14 text-white">
            <!-- Background Image with Cinematic Gradient Overlays -->
            <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <img
                    :src="bgImage"
                    alt="Island Hopping Auth Background"
                    class="h-full w-full object-cover object-center scale-105 filter brightness-[0.7] dark:brightness-[0.5] transition-opacity duration-700"
                    loading="eager"
                    @error="handleImageError"
                />
                <!-- Gradient Overlays for High Contrast -->
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/40 to-slate-950/70" />
                <div class="absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-950/50 to-transparent" />
            </div>

            <!-- Top Pill / Marine Trust Signal -->
            <div class="relative z-10">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-black/40 px-3.5 py-1.5 text-xs font-semibold text-white backdrop-blur-md shadow-xs">
                    <span class="flex h-2 w-2 rounded-full bg-emerald-400 animate-ping" />
                    <ShieldCheck class="h-4 w-4 text-emerald-400" />
                    <span>Coast Guard Certified Maritime System</span>
                </div>
            </div>

            <!-- Center Brand Narrative & Feature Badges -->
            <div class="relative z-10 my-auto max-w-lg space-y-6">
                <div class="space-y-3">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-[0.2em] text-cyan-300">
                        <Sparkles class="h-3.5 w-3.5" />
                        Explore • Book • Sail
                    </span>

                    <h1 class="text-4xl font-black leading-tight tracking-tight text-white xl:text-5xl font-heading">
                        Your Island Journey <br />
                        <span class="bg-gradient-to-r from-cyan-200 via-cyan-300 to-[#00FFFF] bg-clip-text text-transparent">
                            Starts Here.
                        </span>
                    </h1>

                    <p class="text-sm sm:text-base leading-relaxed text-gray-200 font-medium">
                        Discover curated island hopping tour packages, check live meteorological forecasts, select convenient pickup stations, and access instant QR boarding tickets.
                    </p>
                </div>

                <!-- 3 Feature Highlight Cards -->
                <div class="grid grid-cols-3 gap-3 pt-2">
                    <div class="rounded-2xl border border-white/15 bg-black/40 p-3.5 backdrop-blur-md space-y-1">
                        <CalendarDays class="h-4 w-4 text-cyan-300" />
                        <p class="text-xs font-bold text-white">Scheduled Dates</p>
                        <p class="text-[10px] text-gray-300">Live seat availability</p>
                    </div>

                    <div class="rounded-2xl border border-white/15 bg-black/40 p-3.5 backdrop-blur-md space-y-1">
                        <Waves class="h-4 w-4 text-emerald-300" />
                        <p class="text-xs font-bold text-white">Weather Status</p>
                        <p class="text-[10px] text-gray-300">Automated forecasts</p>
                    </div>

                    <div class="rounded-2xl border border-white/15 bg-black/40 p-3.5 backdrop-blur-md space-y-1">
                        <QrCode class="h-4 w-4 text-cyan-300" />
                        <p class="text-xs font-bold text-white">Fast QR Boarding</p>
                        <p class="text-[10px] text-gray-300">Digital boat tickets</p>
                    </div>
                </div>
            </div>

            <!-- Bottom Sub-Footer -->
            <div class="relative z-10 flex items-center justify-between text-xs text-gray-400 border-t border-white/10 pt-4">
                <span>{{ name }}</span>
                <span>Travel with confidence & safety.</span>
            </div>
        </div>

        <!-- ==========================================================
             RIGHT SIDE: APP LOGO AT THE TOP & AUTHENTICATION FORM
        =========================================================== -->
        <div class="relative flex min-h-dvh flex-col items-center justify-center px-5 py-10 sm:px-8 lg:min-h-0 lg:px-12 xl:px-20 bg-background text-foreground">
            <div class="absolute top-4 right-4 z-20 sm:top-6 sm:right-6">
                <AppearanceTabs variant="segmented" />
            </div>

            <div class="w-full max-w-md space-y-6">
                <!-- App Logo at the top of the form (Prominent on all screens) -->
                <div class="flex flex-col items-center text-center">
                    <Link :href="home()" class="inline-flex transition hover:opacity-90 mb-2">
                        <AppLogo large />
                    </Link>
                </div>

                <!-- Authentication Card Container -->
                <Card class="border-0 bg-transparent shadow-none sm:border sm:border-border/70 sm:bg-card sm:shadow-sm sm:rounded-3xl sm:p-2">
                    <CardHeader v-if="title || description" class="px-0 sm:px-6 pt-2 pb-4 text-center sm:text-left">
                        <h1 v-if="title" class="text-2xl font-bold tracking-tight text-foreground font-heading">
                            {{ title }}
                        </h1>
                        <p v-if="description" class="mt-1 text-xs sm:text-sm text-muted-foreground leading-relaxed">
                            {{ description }}
                        </p>
                    </CardHeader>

                    <CardContent class="px-0 sm:px-6 pt-0 pb-2">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>