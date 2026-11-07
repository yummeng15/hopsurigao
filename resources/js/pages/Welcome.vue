<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import {
    ArrowRight,
    Calendar,
    CalendarDays,
    Check,
    CheckCircle2,
    Clock,
    CloudSun,
    Compass,
    ExternalLink,
    Eye,
    ImageOff,
    Images,
    Info,
    LifeBuoy,
    MapPin,
    QrCode,
    Search,
    ShieldCheck,
    Ship,
    Sparkles,
    Sun,
    Tag,
    Umbrella,
    Users,
    Waves,
    Wind,
} from '@lucide/vue';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Carousel,
    CarouselContent,
    CarouselItem,
    CarouselNext,
    CarouselPrevious,
} from '@/components/ui/carousel';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import WeatherAdvisoryBadge from '@/components/weather/WeatherAdvisoryBadge.vue';
import { useRole } from '@/composables/useRole';
import { login, register } from '@/routes';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface WeatherForecast {
    advisory_level: 'green' | 'yellow' | 'orange' | 'red';
    advisory_badge: string;
    weather_condition: string;
    temperature_max?: number;
    rainfall_probability?: number;
    wind_speed?: number;
}

interface UpcomingDate {
    id: number;
    tour_date: string;
    capacity: number;
    booked_guests: number;
    remaining_capacity: number;
    weather_forecast: WeatherForecast | null;
}

interface PackageItem {
    id: number;
    package_name: string;
    destination: string;
    description: string | null;
    price: number;
    status: string;
    latitude?: number;
    longitude?: number;
    tour_dates_count: number;
    inclusions: string[];
    reminders: string[];
    featured_image: string | null;
    images: { name: string; path: string; url: string }[];
    next_tour_date: {
        tour_date: string;
        weather_forecast: WeatherForecast | null;
        capacity?: number;
    } | null;
    upcoming_dates?: UpcomingDate[];
}

interface PickupLocationItem {
    id: number;
    name: string;
    address: string | null;
}

interface MarineConditions {
    temperature: number;
    wind_speed: number;
    rainfall_probability: number;
    weather_condition: string;
    advisory_level: 'green' | 'yellow' | 'orange' | 'red';
    advisory_badge: string;
    advisory_message: string;
    green_count: number;
    yellow_count: number;
    orange_count: number;
    red_count: number;
}

interface StatsData {
    total_bookings: number;
    total_guests: number;
    total_packages: number;
    total_pickups: number;
    upcoming_tours: number;
    satisfaction_rate: string;
}

/*
|--------------------------------------------------------------------------
| Props & Defaults
|--------------------------------------------------------------------------
*/

const props = withDefaults(
    defineProps<{
        packages?: PackageItem[];
        pickupLocations?: PickupLocationItem[];
        marineConditions?: MarineConditions;
        stats?: StatsData;
    }>(),
    {
        packages: () => [],
        pickupLocations: () => [],
        marineConditions: () => ({
            temperature: 30.0,
            wind_speed: 12.0,
            rainfall_probability: 15,
            weather_condition: 'Clear & Sunny',
            advisory_level: 'green',
            advisory_badge: 'Normal / Suitable',
            advisory_message: 'Calm maritime waters with optimal visibility across all island hopping routes.',
            green_count: 5,
            yellow_count: 0,
            orange_count: 0,
            red_count: 0,
        }),
        stats: () => ({
            total_bookings: 0,
            total_guests: 0,
            total_packages: 0,
            total_pickups: 0,
            upcoming_tours: 0,
            satisfaction_rate: '99.4%',
        }),
    }
);

/*
|--------------------------------------------------------------------------
| App Info & User Navigation
|--------------------------------------------------------------------------
*/

const appName = usePage().props.name || 'HopSurigao';
const { isSuperadmin, isAdmin, isUser } = useRole();

const homeHref = computed(() => {
    if (isSuperadmin.value) return '/superadmin/dashboard';
    if (isAdmin.value) return '/admin/qrscanner';
    if (isUser.value) return '/user/dashboard';
    return '/';
});

const dashboardLabel = computed(() => {
    if (isSuperadmin.value) return 'Superadmin Dashboard';
    if (isAdmin.value) return 'Scanner Portal';
    if (isUser.value) return 'My Dashboard';
    return 'Dashboard';
});

/*
|--------------------------------------------------------------------------
| Hero Background Image
|--------------------------------------------------------------------------
*/

const defaultHeroImage = 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2073&q=80';
const backupHeroImage = 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&w=2000&q=80';

const heroImage = ref(defaultHeroImage);

function handleHeroImgError() {
    if (heroImage.value !== backupHeroImage) {
        heroImage.value = backupHeroImage;
    }
}

/*
|--------------------------------------------------------------------------
| Package Image Helper
|--------------------------------------------------------------------------
*/

const fallbackImages: Record<string, string> = {
    'Siargao Tri-Island Hopping': 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80',
    'Sugba Lagoon Adventure': 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=1200&q=80',
    'Sohoton Bucas Grande Adventure': 'https://images.unsplash.com/photo-1518509562904-e7ef99cdcc86?auto=format&fit=crop&w=1200&q=80',
    'Magpupungko Rock Pools Explorer': 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80',
    'Siargao Island Land & Sea Tour': 'https://images.unsplash.com/photo-1512100356356-de1b84283e18?auto=format&fit=crop&w=1200&q=80',
};

function getPackageImage(pkg: PackageItem): string {
    if (pkg.featured_image) return pkg.featured_image;
    if (pkg.images && pkg.images.length > 0 && pkg.images[0]?.url) {
        return pkg.images[0].url;
    }
    return fallbackImages[pkg.package_name] || 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80';
}

/*
|--------------------------------------------------------------------------
| Package Filtering & Image Gallery Dialog State
|--------------------------------------------------------------------------
*/

const searchQuery = ref('');
const selectedPackage = ref<PackageItem | null>(null);
const imageDialogOpen = ref(false);

const filteredPackages = computed(() => {
    if (!searchQuery.value.trim()) return props.packages;
    const q = searchQuery.value.toLowerCase();
    return props.packages.filter(
        (p) =>
            p.package_name.toLowerCase().includes(q) ||
            p.destination.toLowerCase().includes(q) ||
            (p.description && p.description.toLowerCase().includes(q))
    );
});

const displayedPackages = computed(() => {
    if (searchQuery.value.trim().length > 0) {
        return filteredPackages.value;
    }
    return filteredPackages.value.slice(0, 6);
});

function openImages(packageItem: PackageItem) {
    selectedPackage.value = packageItem;
    imageDialogOpen.value = true;
}

function openImageInNewTab(url: string) {
    window.open(url, '_blank', 'noopener,noreferrer');
}

function formatPrice(price: number | string): string {
    return Number(price).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatDate(dateStr?: string | null): string {
    if (!dateStr) return 'Dates available';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function scrollToSection(id: string) {
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<template>
    <Head :title="`${appName} — Island Hopping & Tour Reservations`">
        <meta
            name="description"
            content="Official Island Hopping Reservation System for Siargao and Surigao. Book verified tour packages, select pickup points, and receive instant QR boarding passes."
        />
    </Head>

    <div class="min-h-screen bg-background text-foreground selection:bg-primary/20 selection:text-primary">
        <!-- ==========================================================
             STICKY NAVIGATION HEADER (USING APPLOGO)
        =========================================================== -->
        <header class="sticky top-0 z-50 w-full border-b border-border/60 bg-background/90 backdrop-blur-xl transition-all duration-300">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-3.5 sm:px-6 lg:px-8">
                <!-- APP LOGO -->
                <Link href="/" class="flex items-center transition hover:opacity-90">
                    <AppLogo />
                </Link>

                <!-- NAV LINKS -->
                <nav class="hidden md:flex items-center gap-2 text-sm font-medium">
                    <button
                        type="button"
                        class="rounded-xl px-3.5 py-2 text-muted-foreground transition hover:bg-muted hover:text-foreground cursor-pointer"
                        @click="scrollToSection('packages')"
                    >
                        Tour Packages ({{ packages.length }})
                    </button>
                    <button
                        type="button"
                        class="rounded-xl px-3.5 py-2 text-muted-foreground transition hover:bg-muted hover:text-foreground cursor-pointer"
                        @click="scrollToSection('pickups')"
                    >
                        Pickup Points ({{ pickupLocations.length }})
                    </button>
                    <button
                        type="button"
                        class="rounded-xl px-3.5 py-2 text-muted-foreground transition hover:bg-muted hover:text-foreground cursor-pointer"
                        @click="scrollToSection('safety')"
                    >
                        Weather & Safety
                    </button>
                </nav>

                <!-- USER AUTH ACTION BUTTONS -->
                <div class="flex items-center gap-2.5">
                    <AppearanceTabs variant="segmented" />
                    <template v-if="$page.props.auth?.user">
                        <Button as-child class="rounded-xl shadow-md gap-2 font-semibold">
                            <Link :href="homeHref">
                                <Compass class="h-4 w-4" />
                                <span>{{ dashboardLabel }}</span>
                            </Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" as-child class="rounded-xl text-sm font-medium hover:bg-muted">
                            <Link :href="login()">Log in</Link>
                        </Button>
                        <Button as-child class="rounded-xl shadow-md shadow-primary/20 gap-1.5 font-semibold">
                            <Link :href="register()">
                                <span>Book Tour</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <!-- ==========================================================
             HERO SECTION WITH BACKGROUND IMAGE & REAL DATA
        =========================================================== -->
        <section class="hero-sea relative flex min-h-[85vh] items-center justify-center overflow-hidden bg-slate-950">
            <div class="pointer-events-none absolute inset-0 z-0 overflow-hidden" aria-hidden="true">
                <img
                    :src="heroImage"
                    alt=""
                    class="hero-sea__photo"
                    loading="eager"
                    @error="handleHeroImgError"
                />
                <div class="hero-sea__shade absolute inset-0" />
                <div class="hero-sea__scrim absolute inset-0" />
                <div class="hero-sea__glint absolute -right-16 top-10 h-72 w-[28rem] rounded-full blur-3xl" />
                <svg class="hero-sea__wave hero-sea__wave--swell" viewBox="0 0 2880 160" preserveAspectRatio="none">
                    <path d="M0,90 C240,140 480,40 720,80 C960,120 1200,30 1440,90 C1680,140 1920,40 2160,80 C2400,120 2640,30 2880,90 L2880,160 L0,160 Z" />
                </svg>
                <svg class="hero-sea__wave hero-sea__wave--back" viewBox="0 0 2880 120" preserveAspectRatio="none">
                    <path d="M0,64 C240,108 480,22 720,58 C960,94 1200,16 1440,64 C1680,108 1920,22 2160,58 C2400,94 2640,16 2880,64 L2880,120 L0,120 Z" />
                </svg>
                <svg class="hero-sea__wave hero-sea__wave--front" viewBox="0 0 2880 120" preserveAspectRatio="none">
                    <path d="M0,78 C220,40 460,108 720,72 C980,36 1220,104 1440,68 C1660,32 1900,108 2160,70 C2420,34 2660,100 2880,64 L2880,120 L0,120 Z" />
                </svg>
            </div>

            <!-- Content Container -->
            <div class="relative z-10 mx-auto max-w-7xl px-5 py-20 sm:px-6 lg:px-8 w-full">
                <div class="max-w-3xl space-y-6">
                    <!-- Real-Time Weather Status Badge -->
                    <div class="inline-flex items-center gap-2.5 rounded-full border border-emerald-500/30 bg-emerald-950/40 px-4 py-1.5 text-xs font-semibold text-emerald-300 backdrop-blur-md shadow-md animate-fade-in">
                        <span class="flex h-2 w-2 rounded-full bg-emerald-400 animate-ping" />
                        <ShieldCheck class="h-4 w-4 text-emerald-400" />
                        <span>Live Marine Status: {{ marineConditions.weather_condition }} • {{ marineConditions.temperature }}°C</span>
                    </div>

                    <!-- Main Title -->
                    <h1 class="text-4xl font-black tracking-tight text-white drop-shadow-md sm:text-6xl font-heading leading-tight">
                        Reserve Your Next <br />
                        <span class="bg-gradient-to-r from-cyan-200 via-cyan-300 to-[#00FFFF] bg-clip-text text-transparent">
                            Island Hopping Tour
                        </span>
                    </h1>

                    <!-- Descriptive Subtitle with Real Metrics -->
                    <p class="max-w-2xl text-base sm:text-lg text-gray-200 font-medium leading-relaxed drop-shadow-sm">
                        Explore {{ packages.length }} active tour packages with scheduled departures across {{ stats.upcoming_tours }} upcoming dates and {{ pickupLocations.length }} coordinated pickup stations.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <Button
                            size="lg"
                            class="rounded-2xl bg-primary hover:bg-primary/90 text-primary-foreground shadow-xl shadow-primary/30 h-12 px-7 text-sm font-bold gap-2 cursor-pointer"
                            @click="scrollToSection('packages')"
                        >
                            <Ship class="h-4 w-4" />
                            <span>View {{ packages.length }} Packages</span>
                            <ArrowRight class="h-4 w-4 ml-1" />
                        </Button>

                        <Button
                            size="lg"
                            variant="outline"
                            class="rounded-2xl border-white/20 bg-black/40 text-white hover:bg-white/20 backdrop-blur-md h-12 px-6 text-sm font-semibold gap-2 cursor-pointer"
                            @click="scrollToSection('pickups')"
                        >
                            <MapPin class="h-4 w-4 text-cyan-300" />
                            <span>Pickup Stations</span>
                        </Button>
                    </div>

                    <!-- Real Data Metrics Row -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-6 border-t border-white/10 text-white">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 backdrop-blur-xs">
                                <Ship class="h-4 w-4 text-cyan-300" />
                            </div>
                            <div>
                                <p class="text-sm font-bold">{{ stats.total_packages }} Packages</p>
                                <p class="text-[11px] text-gray-300">Active Itineraries</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 backdrop-blur-xs">
                                <CalendarDays class="h-4 w-4 text-emerald-300" />
                            </div>
                            <div>
                                <p class="text-sm font-bold">{{ stats.upcoming_tours }} Dates</p>
                                <p class="text-[11px] text-gray-300">Upcoming Schedules</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 backdrop-blur-xs">
                                <MapPin class="h-4 w-4 text-amber-300" />
                            </div>
                            <div>
                                <p class="text-sm font-bold">{{ stats.total_pickups }} Hubs</p>
                                <p class="text-[11px] text-gray-300">Pickup Stations</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/10 backdrop-blur-xs">
                                <Users class="h-4 w-4 text-cyan-300" />
                            </div>
                            <div>
                                <p class="text-sm font-bold">{{ stats.total_guests }} Guests</p>
                                <p class="text-[11px] text-gray-300">{{ stats.total_bookings }} Bookings Placed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================================
             REAL TOUR PACKAGES SHOWCASE
        =========================================================== -->
        <section id="packages" class="py-16 sm:py-24 relative">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8 space-y-10">
                <!-- Section Header with Search -->
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary mb-2">
                            <Sparkles class="h-3.5 w-3.5" />
                            <span>Active System Packages</span>
                        </div>
                        <h2 class="text-3xl font-bold tracking-tight text-foreground font-heading">
                            Available Tour Packages
                        </h2>
                        <p class="text-sm text-muted-foreground mt-1 max-w-xl">
                            Select a tour package to view available departure dates, inclusions, reminders, and book your seats.
                        </p>
                    </div>

                    <!-- Search & Quick Link -->
                    <div class="flex items-center gap-3">
                        <div class="relative min-w-[240px]">
                            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Filter packages or destinations..."
                                class="w-full rounded-xl border border-border/80 bg-background pl-9 pr-4 py-2 text-xs focus:outline-hidden focus:ring-2 focus:ring-primary/40"
                            />
                        </div>

                        <Link
                            v-if="packages.length > 6"
                            href="/user/packages"
                            class="hidden sm:inline-flex items-center gap-1.5 rounded-xl border border-primary/20 bg-primary/10 px-3.5 py-2 text-xs font-semibold text-primary transition hover:bg-primary hover:text-primary-foreground shrink-0"
                        >
                            <span>View More</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </div>

                <!-- Package Grid (Matching user/packages/Index.vue) -->
                <div v-if="displayedPackages.length > 0" class="space-y-10">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="packageItem in displayedPackages"
                            :key="packageItem.id"
                            class="group flex flex-col justify-between overflow-hidden rounded-3xl border bg-card/60 backdrop-blur shadow-xs transition duration-300 hover:border-primary/40 hover:shadow-md"
                        >
                            <!-- Image Container with Gallery Trigger -->
                            <div>
                                <button
                                    type="button"
                                    class="relative block aspect-video w-full overflow-hidden bg-muted transition cursor-pointer"
                                    @click="openImages(packageItem)"
                                >
                                    <img
                                        v-if="packageItem.images?.length"
                                        :src="packageItem.images[0].url"
                                        :alt="packageItem.package_name"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    />

                                    <img
                                        v-else-if="packageItem.featured_image"
                                        :src="packageItem.featured_image"
                                        :alt="packageItem.package_name"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    />

                                    <div
                                        v-else
                                        class="flex h-full w-full flex-col items-center justify-center gap-2 bg-muted/40 text-muted-foreground"
                                    >
                                        <ImageOff class="h-6 w-6" />
                                        <span class="text-xs">No image available</span>
                                    </div>

                                    <!-- Overlay Hover Badge -->
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition duration-200 group-hover:opacity-100">
                                        <span class="flex items-center gap-1.5 rounded-full bg-background/90 px-3 py-1.5 text-xs font-semibold text-foreground backdrop-blur shadow-xs">
                                            <Eye class="h-3.5 w-3.5 text-primary" />
                                            Preview Photos
                                        </span>
                                    </div>

                                    <!-- Photo Count Badge -->
                                    <span
                                        v-if="packageItem.images?.length > 1"
                                        class="absolute bottom-3 right-3 flex items-center gap-1.5 rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white backdrop-blur"
                                    >
                                        <Images class="h-3.5 w-3.5" />
                                        <span>{{ packageItem.images.length }}</span>
                                    </span>
                                </button>

                                <!-- Package Content -->
                                <div class="space-y-4 p-6">
                                    <div>
                                        <p class="inline-flex items-center gap-1.5 text-xs font-medium text-primary">
                                            <MapPin class="h-3.5 w-3.5 shrink-0" />
                                            <span>{{ packageItem.destination }}</span>
                                        </p>

                                        <h2 class="mt-1.5 text-lg font-bold tracking-tight text-foreground transition group-hover:text-primary">
                                            {{ packageItem.package_name }}
                                        </h2>
                                    </div>

                                    <p
                                        v-if="packageItem.description"
                                        class="line-clamp-2 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {{ packageItem.description }}
                                    </p>

                                    <!-- Upcoming Weather Preview -->
                                    <div
                                        v-if="packageItem.next_tour_date?.weather_forecast"
                                        class="flex items-center justify-between gap-2 rounded-xl bg-background/80 px-3 py-2 border text-xs"
                                    >
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <CloudSun class="h-3.5 w-3.5 text-primary shrink-0" />
                                            <span class="truncate text-foreground font-medium text-[11px]">
                                                {{ packageItem.next_tour_date.weather_forecast.weather_condition }}
                                                <span v-if="packageItem.next_tour_date.weather_forecast.temperature_max" class="text-muted-foreground">
                                                    • {{ Math.round(Number(packageItem.next_tour_date.weather_forecast.temperature_max)) }}°C
                                                </span>
                                            </span>
                                        </div>
                                        <WeatherAdvisoryBadge
                                            :level="packageItem.next_tour_date.weather_forecast.advisory_level"
                                            :badge-text="packageItem.next_tour_date.weather_forecast.advisory_badge"
                                            size="sm"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Footer / Pricing & Actions -->
                            <div class="border-t bg-muted/20 p-6 pt-4">
                                <div class="mb-4 flex items-baseline justify-between">
                                    <div>
                                        <p class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                            <Tag class="h-3 w-3" />
                                            Starts from
                                        </p>
                                        <p class="mt-0.5 text-xl font-extrabold tracking-tight text-foreground">
                                            ₱{{ formatPrice(packageItem.price) }}
                                        </p>
                                    </div>

                                    <div class="text-right">
                                        <p class="flex items-center justify-end gap-1 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                                            <CalendarDays class="h-3 w-3" />
                                            Tour Dates
                                        </p>
                                        <span
                                            class="mt-0.5 inline-block text-xs font-semibold"
                                            :class="packageItem.tour_dates_count > 0 ? 'text-primary' : 'text-muted-foreground'"
                                        >
                                            {{ packageItem.tour_dates_count > 0 ? `${packageItem.tour_dates_count} available` : 'No dates set' }}
                                        </span>
                                    </div>
                                </div>

                                <Link
                                    :href="`/user/packages/${packageItem.id}`"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-3 text-xs font-semibold text-primary-foreground shadow-sm transition duration-200 hover:-translate-y-0.5 hover:opacity-90"
                                >
                                    <span>View Package Details</span>
                                    <ArrowRight class="h-3.5 w-3.5" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom View More Link when more than 6 packages exist -->
                    <div
                        v-if="filteredPackages.length > 6 && !searchQuery.trim()"
                        class="flex flex-col items-center justify-center gap-2.5 pt-4 text-center"
                    >
                        <Link
                            href="/user/packages"
                            class="inline-flex items-center gap-2.5 rounded-2xl border border-primary/25 bg-primary/10 hover:bg-primary hover:text-primary-foreground px-8 py-3.5 text-sm font-semibold text-primary transition-all duration-200 shadow-xs hover:shadow-md hover:-translate-y-0.5 group"
                        >
                            <span>View More</span>
                            <ArrowRight class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" />
                        </Link>
                        <p class="text-xs text-muted-foreground">
                            Showing 6 of {{ filteredPackages.length }} available tour packages
                        </p>
                    </div>
                </div>

                <!-- Empty Search Fallback -->
                <div v-else class="rounded-2xl border border-dashed border-border p-10 text-center space-y-2">
                    <Ship class="h-8 w-8 text-muted-foreground mx-auto" />
                    <h3 class="text-sm font-bold text-foreground">No tour packages match your search</h3>
                    <Button variant="outline" size="sm" class="rounded-xl mt-2 cursor-pointer" @click="searchQuery = ''">
                        Clear Search
                    </Button>
                </div>
            </div>
        </section>

        <!-- ==========================================================
             REAL PICKUP LOCATIONS
        =========================================================== -->
        <section id="pickups" class="py-16 bg-muted/30 border-y border-border/60 relative">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8 space-y-8">
                <div class="max-w-2xl space-y-1.5">
                    <div class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                        <MapPin class="h-3.5 w-3.5" />
                        <span>Departure Points</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground font-heading">
                        Active Pickup Locations
                    </h2>
                    <p class="text-xs sm:text-sm text-muted-foreground">
                        Coordinated pickup points available for your tour departure.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="loc in pickupLocations"
                        :key="loc.id"
                        class="rounded-2xl border border-border/80 bg-card p-4 shadow-xs flex items-start gap-3.5"
                    >
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <MapPin class="h-4 w-4" />
                        </div>
                        <div class="space-y-0.5">
                            <h4 class="font-bold text-xs sm:text-sm text-foreground">{{ loc.name }}</h4>
                            <p class="text-[11px] text-muted-foreground leading-relaxed">{{ loc.address || 'Active pickup station' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ==========================================================
             REAL MARINE SAFETY & METEOROLOGICAL STATUS
        =========================================================== -->
        <section id="safety" class="py-16 relative">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8">
                <div class="rounded-3xl border border-border/80 bg-card p-6 sm:p-8 shadow-md space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/60 pb-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Waves class="h-5 w-5 text-primary" />
                                <h3 class="font-bold text-base text-foreground font-heading">
                                    Current Meteorological & Maritime Status
                                </h3>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Live meteorological forecasts synced across upcoming departure dates.
                            </p>
                        </div>

                        <WeatherAdvisoryBadge
                            :advisory-level="marineConditions.advisory_level"
                            :advisory-badge="marineConditions.advisory_badge"
                        />
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div class="rounded-2xl border border-border/60 bg-muted/30 p-3.5 space-y-1">
                            <div class="flex items-center gap-1.5 text-muted-foreground text-[11px]">
                                <Sun class="h-3.5 w-3.5 text-amber-500" />
                                <span>Temperature</span>
                            </div>
                            <p class="text-base font-bold text-foreground">{{ marineConditions.temperature }}°C</p>
                            <p class="text-[10px] text-muted-foreground">{{ marineConditions.weather_condition }}</p>
                        </div>

                        <div class="rounded-2xl border border-border/60 bg-muted/30 p-3.5 space-y-1">
                            <div class="flex items-center gap-1.5 text-muted-foreground text-[11px]">
                                <Wind class="h-3.5 w-3.5 text-cyan-500" />
                                <span>Wind Speed</span>
                            </div>
                            <p class="text-base font-bold text-foreground">{{ marineConditions.wind_speed }} km/h</p>
                            <p class="text-[10px] text-muted-foreground">Average forecast</p>
                        </div>

                        <div class="rounded-2xl border border-border/60 bg-muted/30 p-3.5 space-y-1">
                            <div class="flex items-center gap-1.5 text-muted-foreground text-[11px]">
                                <Umbrella class="h-3.5 w-3.5 text-primary" />
                                <span>Rain Probability</span>
                            </div>
                            <p class="text-base font-bold text-foreground">{{ marineConditions.rainfall_probability }}%</p>
                            <p class="text-[10px] text-muted-foreground">Precipitation index</p>
                        </div>

                        <div class="rounded-2xl border border-border/60 bg-muted/30 p-3.5 space-y-1">
                            <div class="flex items-center gap-1.5 text-muted-foreground text-[11px]">
                                <ShieldCheck class="h-3.5 w-3.5 text-emerald-500" />
                                <span>Suitable Dates</span>
                            </div>
                            <p class="text-base font-bold text-emerald-600 dark:text-emerald-400">{{ marineConditions.green_count }} Dates</p>
                            <p class="text-[10px] text-muted-foreground">Normal sailing</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="border-t border-border/60 bg-muted/40 py-10 text-foreground text-xs">
            <div class="mx-auto max-w-7xl px-5 sm:px-6 lg:px-8 space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <AppLogo compact />

                    <!-- Direct Navigation Links -->
                    <div class="flex items-center gap-5 text-xs text-muted-foreground font-medium">
                        <button type="button" class="hover:text-foreground cursor-pointer" @click="scrollToSection('packages')">Packages</button>
                        <button type="button" class="hover:text-foreground cursor-pointer" @click="scrollToSection('pickups')">Pickups</button>
                        <button type="button" class="hover:text-foreground cursor-pointer" @click="scrollToSection('safety')">Weather</button>
                    </div>
                </div>

                <div class="border-t border-border/60 pt-4 text-[11px] text-muted-foreground">
                    <p>© {{ new Date().getFullYear() }} {{ appName }}. All rights reserved.</p>
                </div>
            </div>
        </footer>

        <!-- ==========================================================
             PACKAGE GALLERY PREVIEW MODAL (MATCHING USER PACKAGES)
        =========================================================== -->
        <Dialog :open="imageDialogOpen" @update:open="(value: boolean) => (imageDialogOpen = value)">
            <DialogContent
                class="max-h-[92vh] overflow-y-auto rounded-3xl border bg-card/95 p-6 backdrop-blur-md sm:max-w-4xl sm:p-8"
            >
                <!-- Ambient Glow for Dialog -->
                <div class="pointer-events-none absolute -right-16 -top-16 -z-10 h-48 w-48 rounded-full bg-primary/10 blur-3xl" />

                <!-- =====================================================
                     HEADER
                ====================================================== -->
                <DialogHeader class="pb-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full border bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary">
                            <Images class="h-3.5 w-3.5" />
                            <span>Gallery Preview</span>
                        </span>
                    </div>

                    <DialogTitle class="mt-2 text-xl font-bold tracking-tight sm:text-2xl">
                        {{ selectedPackage?.package_name ?? 'Package Gallery' }}
                    </DialogTitle>
                </DialogHeader>

                <!-- =====================================================
                     CAROUSEL SHOWCASE
                ====================================================== -->
                <div v-if="selectedPackage && selectedPackage.images && selectedPackage.images.length" class="mt-2 w-full">
                    <Carousel class="w-full">
                        <CarouselContent>
                            <CarouselItem
                                v-for="(image, imageIndex) in selectedPackage.images"
                                :key="image.path"
                            >
                                <div class="group relative aspect-video w-full overflow-hidden rounded-2xl border bg-muted/50 shadow-xs">
                                    <img
                                        :src="image.url"
                                        :alt="`${selectedPackage.package_name} preview ${imageIndex + 1}`"
                                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                                    />

                                    <!-- Hover Backdrop Overlay with Action -->
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition duration-200 group-hover:opacity-100">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-2 rounded-xl bg-background/90 px-4 py-2.5 text-xs font-semibold text-foreground backdrop-blur shadow-sm transition hover:scale-105 hover:bg-background cursor-pointer"
                                            @click="openImageInNewTab(image.url)"
                                        >
                                            <ExternalLink class="h-3.5 w-3.5 text-primary" />
                                            <span>Open Full Image</span>
                                        </button>
                                    </div>

                                    <!-- Photo Indicator Pill -->
                                    <span class="absolute bottom-3.5 right-3.5 rounded-full bg-black/60 px-3 py-1 text-xs font-medium text-white backdrop-blur">
                                        {{ imageIndex + 1 }} / {{ selectedPackage.images.length }}
                                    </span>
                                </div>
                            </CarouselItem>
                        </CarouselContent>

                        <!-- Carousel Controls -->
                        <template v-if="selectedPackage.images.length > 1">
                            <CarouselPrevious
                                class="left-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur transition hover:bg-background"
                            />
                            <CarouselNext
                                class="right-3 rounded-xl border-border/80 bg-background/80 text-foreground backdrop-blur transition hover:bg-background"
                            />
                        </template>
                    </Carousel>
                </div>

                <!-- SINGLE FEATURED IMAGE / FALLBACK -->
                <div v-else-if="selectedPackage && getPackageImage(selectedPackage)" class="mt-2 w-full">
                    <div class="group relative aspect-video w-full overflow-hidden rounded-2xl border bg-muted/50 shadow-xs">
                        <img
                            :src="getPackageImage(selectedPackage)"
                            :alt="selectedPackage.package_name"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                        />
                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition duration-200 group-hover:opacity-100">
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl bg-background/90 px-4 py-2.5 text-xs font-semibold text-foreground backdrop-blur shadow-sm transition hover:scale-105 hover:bg-background cursor-pointer"
                                @click="openImageInNewTab(getPackageImage(selectedPackage))"
                            >
                                <ExternalLink class="h-3.5 w-3.5 text-primary" />
                                <span>Open Full Image</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- =====================================================
                     EMPTY STATE
                ====================================================== -->
                <div
                    v-else
                    class="mt-2 flex aspect-video flex-col items-center justify-center rounded-2xl border border-dashed bg-muted/20 p-8 text-center"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <ImageOff class="h-6 w-6" />
                    </div>
                    <h3 class="mt-3 text-sm font-semibold">No images available</h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        There are no showcase photos attached to this package yet.
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.hero-sea__photo {
    height: 100%;
    width: 100%;
    object-fit: cover;
    object-position: center 72%;
    filter: brightness(0.96) saturate(1.18);
    transform: scale(1.08);
}

.hero-sea__shade {
    background: linear-gradient(to top, var(--background) 0%, rgb(2 6 23 / 6%) 24%, rgb(2 6 23 / 22%) 100%);
}

.hero-sea__scrim {
    background: linear-gradient(to right, rgb(2 6 23 / 68%) 0%, rgb(2 6 23 / 22%) 38%, transparent 64%);
}

.hero-sea__glint {
    background: rgb(127 240 246 / 28%);
}

.hero-sea__wave {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 200%;
    fill: rgb(255 255 255 / 16%);
}

.hero-sea__wave--swell {
    bottom: 8%;
    height: 7.5rem;
    fill: rgb(186 248 252 / 22%);
}

.hero-sea__wave--back {
    height: 6.5rem;
    fill: rgb(79 224 232 / 18%);
}

.hero-sea__wave--front {
    height: 4.25rem;
    fill: rgb(255 255 255 / 24%);
}

:global(.dark) .hero-sea__photo {
    filter: brightness(0.86) saturate(1.16);
}

:global(.dark) .hero-sea__shade {
    background: linear-gradient(to top, var(--background) 0%, rgb(2 6 23 / 10%) 30%, rgb(2 6 23 / 28%) 100%);
}

@media (prefers-reduced-motion: no-preference) {
    .hero-sea__photo {
        animation: hero-swell 32s ease-in-out infinite alternate;
    }

    .hero-sea__glint {
        animation: hero-glint 14s ease-in-out infinite alternate;
    }

    .hero-sea__wave--swell {
        animation: hero-drift 28s linear infinite;
    }

    .hero-sea__wave--back {
        animation: hero-drift 18s linear infinite reverse;
    }

    .hero-sea__wave--front {
        animation: hero-drift 12s linear infinite;
    }
}

@keyframes hero-swell {
    from {
        transform: scale(1.06) translate3d(0, 0, 0);
    }

    to {
        transform: scale(1.14) translate3d(-2%, -1.2%, 0);
    }
}

@keyframes hero-glint {
    from {
        transform: translate3d(0, 0, 0);
    }

    to {
        transform: translate3d(-8%, 6%, 0);
    }
}

@keyframes hero-drift {
    from {
        transform: translateX(0);
    }

    to {
        transform: translateX(-50%);
    }
}
</style>