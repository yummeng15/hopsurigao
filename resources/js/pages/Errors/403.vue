<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    ArrowLeft,
    Compass,
    Home,
    KeyRound,
    LayoutGrid,
    Lock,
    LogOut,
    RotateCcw,
    ShieldAlert,
    ShieldX,
    Sparkles,
    UserCheck,
    UserX,
} from '@lucide/vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useRole } from '@/composables/useRole';
import { logout } from '@/routes';

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = withDefaults(
    defineProps<{
        status?: number;
        message?: string;
    }>(),
    {
        status: 403,
        message: 'You do not have permission to access this protected area.',
    }
);

/*
|--------------------------------------------------------------------------
| Role & Destination Handling
|--------------------------------------------------------------------------
*/

const { user, roleName, isSuperadmin, isAdmin, isUser } = useRole();

const dashboardUrl = computed(() => {
    if (isSuperadmin.value) return '/superadmin/dashboard';
    if (isAdmin.value) return '/admin/qrscanner';
    if (isUser.value) return '/user/dashboard';
    return '/';
});

const dashboardLabel = computed(() => {
    if (isSuperadmin.value) return 'Admin Dashboard';
    if (isAdmin.value) return 'Scanner Portal';
    if (isUser.value) return 'Tourist Dashboard';
    return 'Return to Home';
});

const roleBadgeColor = computed(() => {
    switch (roleName.value?.toLowerCase()) {
        case 'superadmin':
            return 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/30';
        case 'admin':
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30';
        case 'user':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
});

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(dashboardUrl.value);
    }
};

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <Head title="403 - Access Forbidden" />

    <div class="relative min-h-screen flex flex-col items-center justify-center overflow-x-clip bg-background px-4 py-12 text-foreground selection:bg-primary/20 selection:text-primary sm:px-6 lg:px-8">
        <!-- Ambient Background Glows & Aesthetics -->
        <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div class="absolute left-1/2 top-1/4 h-[32rem] w-[46rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-destructive/10 blur-3xl dark:bg-destructive/15" />
            <div class="absolute right-10 top-20 h-64 w-64 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute left-10 bottom-20 h-64 w-64 rounded-full bg-amber-500/10 blur-3xl" />
            
            <!-- Subtle Grid overlay -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(128,128,128,0.04)_1px,transparent_1px),linear-gradient(to_bottom,rgba(128,128,128,0.04)_1px,transparent_1px)] bg-[size:4rem_4rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_50%,#000_70%,transparent_100%)]" />
        </div>

        <div class="relative z-10 mx-auto w-full max-w-xl text-center space-y-8">
            <!-- Top Status Badge -->
            <div class="inline-flex items-center gap-2 rounded-full border border-destructive/30 bg-destructive/10 px-4 py-1.5 text-xs font-semibold text-destructive backdrop-blur-md shadow-xs animate-fade-in">
                <ShieldAlert class="h-4 w-4 shrink-0" />
                <span>HTTP 403 • Restricted Area</span>
            </div>

            <!-- Central Icon Graphic & Status Code -->
            <div class="relative mx-auto flex flex-col items-center justify-center">
                <!-- Floating Illustration Container -->
                <div class="relative flex h-28 w-28 items-center justify-center rounded-3xl border border-destructive/20 bg-gradient-to-b from-card/80 via-card/50 to-destructive/10 p-6 shadow-xl backdrop-blur-xl sm:h-32 sm:w-32">
                    <div class="absolute -inset-0.5 rounded-3xl bg-gradient-to-r from-destructive/30 via-primary/20 to-destructive/30 opacity-70 blur-xs" />
                    
                    <div class="relative flex h-full w-full items-center justify-center rounded-2xl bg-background/90 shadow-inner">
                        <Lock class="h-10 w-10 text-destructive sm:h-12 sm:w-12 animate-pulse" />
                    </div>

                    <!-- Decorative Orbiting Badge -->
                    <div class="absolute -bottom-2 -right-2 flex h-8 w-8 items-center justify-center rounded-xl border border-border/80 bg-background shadow-md">
                        <ShieldX class="h-4 w-4 text-destructive" />
                    </div>
                </div>

                <!-- Big 403 Numbers -->
                <h1 class="mt-6 text-6xl font-black tracking-tight sm:text-7xl lg:text-8xl bg-gradient-to-b from-foreground via-foreground/90 to-muted-foreground bg-clip-text text-transparent">
                    403
                </h1>
            </div>

            <!-- Headline & Explanatory Text -->
            <div class="space-y-2.5">
                <h2 class="text-2xl font-bold tracking-tight sm:text-3xl text-foreground">
                    Access Permission Denied
                </h2>
                <p class="mx-auto max-w-md text-sm text-muted-foreground sm:text-base">
                    {{ message || 'You do not have the required administrative clearance to access this portal or resource.' }}
                </p>
            </div>

            <!-- Current Logged In Account Info Card -->
            <Card v-if="user" class="rounded-3xl border border-border/80 bg-card/60 backdrop-blur-md shadow-xs transition duration-200 hover:border-border text-left">
                <CardContent class="p-4 sm:p-5 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary font-bold text-base shadow-xs">
                            {{ user.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-foreground truncate">{{ user.name }}</span>
                                <Badge variant="outline" class="text-[10px] font-semibold px-2 py-0.5 rounded-full" :class="roleBadgeColor">
                                    {{ roleName || 'Tourist' }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground truncate">{{ user.email }}</p>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground bg-muted/60 px-2.5 py-1 rounded-xl border border-border/60">
                            <KeyRound class="h-3 w-3 text-muted-foreground" />
                            <span>Standard Access</span>
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Action Toolbar -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <Button
                    size="lg"
                    as-child
                    class="w-full sm:w-auto rounded-2xl shadow-md gap-2 px-6 h-12 text-sm font-semibold"
                >
                    <Link :href="dashboardUrl">
                        <LayoutGrid class="h-4 w-4" />
                        <span>{{ dashboardLabel }}</span>
                    </Link>
                </Button>

                <Button
                    size="lg"
                    variant="outline"
                    class="w-full sm:w-auto rounded-2xl border-border/80 bg-background/80 backdrop-blur-md hover:bg-muted shadow-xs gap-2 px-6 h-12 text-sm font-medium"
                    @click="goBack"
                >
                    <RotateCcw class="h-4 w-4" />
                    <span>Go Back</span>
                </Button>

                <Button
                    size="lg"
                    variant="outline"
                    as-child
                    class="w-full sm:w-auto rounded-2xl border-border/80 bg-background/80 backdrop-blur-md hover:bg-muted shadow-xs gap-2 px-6 h-12 text-sm font-medium"
                >
                    <Link href="/">
                        <Compass class="h-4 w-4 text-primary" />
                        <span>Browse Tours</span>
                    </Link>
                </Button>
            </div>

            <!-- Sign out or switch account option -->
            <div v-if="user" class="pt-2">
                <Link
                    :href="logout()"
                    method="post"
                    as="button"
                    @click="handleLogout"
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-foreground transition underline underline-offset-4"
                >
                    <LogOut class="h-3.5 w-3.5" />
                    <span>Sign in with a different account</span>
                </Link>
            </div>

            <!-- Help / Support Footer Note -->
            <p class="text-xs text-muted-foreground/80">
                If you believe this is an error or need elevated privileges, please contact the island hopping management at
                <a href="mailto:support@hopsurigao.com" class="text-primary hover:underline font-medium">support@hopsurigao.com</a>.
            </p>
        </div>
    </div>
</template>