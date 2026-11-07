<!-- resources/js/pages/superadmin/Dashboard.vue -->
<script setup lang="ts">
/*
|--------------------------------------------------------------------------
| Imports
|--------------------------------------------------------------------------
*/

import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SimpleTable from '@/components/tables/DataTable.vue';

import {
    Activity,
    AlertCircle,
    ArrowRight,
    ArrowUpRight,
    CalendarDays,
    CheckCircle2,
    Clock3,
    CloudSun,
    Compass,
    CreditCard,
    DollarSign,
    Edit,
    Eye,
    KeyRound,
    MapPin,
    Package,
    Plus,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Ship,
    Sparkles,
    Ticket,
    TrendingUp,
    UserCheck,
    UserPlus,
    Users,
    UserX,
} from '@lucide/vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

import {
    AreaChart,
    BarChart,
    DonutChart,
} from '@/components/ui/chart';

import WeatherAdvisoryBadge from '@/components/weather/WeatherAdvisoryBadge.vue';
import { type WeatherForecastData } from '@/components/weather/WeatherAdvisoryCard.vue';

import { dashboard } from '@/routes/superadmin';
import { index as bookingsIndex, show as bookingShow } from '@/routes/admin/bookings';
import { index as packagesIndex } from '@/routes/admin/packages';
import { index as weatherIndex } from '@/routes/admin/weather-advisories';
import { index as reportsIndex } from '@/routes/admin/reports';
import { index as usersIndex } from '@/routes/admin/users';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as permissionsIndex } from '@/routes/admin/permissions';

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
*/

interface DashboardStats {
    total_bookings: number;
    confirmed_bookings: number;
    pending_bookings: number;
    completed_bookings: number;
    cancelled_bookings: number;
    total_revenue: number;
    pending_revenue: number;
    total_guests: number;
    total_users: number;
    total_packages: number;
    upcoming_tours: number;
    weather_advisories: {
        green: number;
        yellow: number;
        orange: number;
        red: number;
    };
    verified_users?: number;
    unverified_users?: number;
    two_factor_users?: number;
    protected_users?: number;
    superadmin_count?: number;
    admin_count?: number;
    user_count?: number;
    total_roles?: number;
    total_permissions?: number;
}

interface MonthlyTrend {
    month: string;
    bookings: number;
    revenue: number;
    guests: number;
}

interface PopularPackage {
    package_name: string;
    destination: string;
    bookings_count: number;
    guests_count: number;
    price: number;
}

interface StatusItem {
    status: string;
    count: number;
    color: string;
}

interface WeatherItem {
    level: string;
    count: number;
    color: string;
}

interface RecentBooking {
    id: number;
    user_name: string;
    user_email: string;
    package_name: string;
    destination: string;
    tour_date: string;
    number_of_guests: number;
    booking_status: string;
    payment_status: string;
    total_amount: number;
    created_at: string;
}

interface UpcomingTour {
    id: number;
    tour_date: string;
    package_name: string;
    destination: string;
    capacity: number;
    booked_guests: number;
    remaining_capacity: number;
    weather_forecast?: WeatherForecastData | null;
}

interface RecentUser {
    id: number;
    name: string;
    email: string;
    role_name: string;
    is_protected: boolean;
    is_verified: boolean;
    has_2fa: boolean;
    created_at: string;
}

interface RoleSummaryItem {
    id: number;
    name: string;
    description: string;
    users_count: number;
    permissions_count: number;
}

/*
|--------------------------------------------------------------------------
| Props
|--------------------------------------------------------------------------
*/

const props = withDefaults(
    defineProps<{
        stats: DashboardStats;
        monthlyTrends: MonthlyTrend[];
        popularPackages: PopularPackage[];
        statusDistribution: StatusItem[];
        weatherDistribution: WeatherItem[];
        recentBookings?: RecentBooking[];
        upcomingTours?: UpcomingTour[];
        recentUsers?: RecentUser[];
        roleSummary?: RoleSummaryItem[];
    }>(),
    {
        recentBookings: () => [],
        upcomingTours: () => [],
        recentUsers: () => [],
        roleSummary: () => [],
    }
);

/*
|--------------------------------------------------------------------------
| Table Column Configurations for SimpleTable
|--------------------------------------------------------------------------
*/

const bookingColumns = [
    { key: 'id', label: 'Booking #', sortable: true, width: '100px' },
    { key: 'user_name', label: 'Customer / Guest', sortable: true },
    { key: 'package_name', label: 'Package & Destination', sortable: true },
    { key: 'tour_date', label: 'Tour Date', sortable: true, width: '120px' },
    { key: 'number_of_guests', label: 'Guests', sortable: true, width: '90px', align: 'center' },
    { key: 'total_amount', label: 'Amount', sortable: true, width: '110px', align: 'right' },
    { key: 'booking_status', label: 'Status', sortable: true, width: '110px', align: 'center' },
    { key: 'payment_status', label: 'Payment', sortable: true, width: '110px', align: 'center' },
    { key: 'actions', label: 'Action', sortable: false, width: '80px', align: 'right' },
];

const tourColumns = [
    { key: 'tour_date', label: 'Tour Date', sortable: true, width: '130px' },
    { key: 'package_name', label: 'Package & Destination', sortable: true },
    { key: 'capacity', label: 'Capacity', sortable: true, width: '95px', align: 'center' },
    { key: 'booked_guests', label: 'Booked', sortable: true, width: '95px', align: 'center' },
    { key: 'remaining_capacity', label: 'Available', sortable: true, width: '95px', align: 'center' },
    { key: 'weather_forecast', label: 'Weather Advisory', sortable: false, width: '170px', align: 'center' },
    { key: 'actions', label: 'Action', sortable: false, width: '80px', align: 'right' },
];

const userColumns = [
    { key: 'user', label: 'User Account', sortable: false },
    { key: 'role_name', label: 'Role', sortable: true, width: '130px' },
    { key: 'is_verified', label: 'Email Status', sortable: true, width: '130px' },
    { key: 'security', label: 'Security & 2FA', sortable: false, width: '150px' },
    { key: 'created_at', label: 'Joined Date', sortable: true, width: '140px' },
    { key: 'actions', label: 'Actions', sortable: false, width: '80px', align: 'right' },
];

const roleColumns = [
    { key: 'name', label: 'System Role', sortable: true },
    { key: 'users_count', label: 'Assigned Users', sortable: true, width: '140px', align: 'center' },
    { key: 'permissions_count', label: 'Permissions', sortable: true, width: '140px', align: 'center' },
    { key: 'actions', label: 'Actions', sortable: false, width: '100px', align: 'right' },
];

/*
|--------------------------------------------------------------------------
| State & Chart Formatters
|--------------------------------------------------------------------------
*/

const trendMetric = ref<'revenue' | 'bookings'>('revenue');

const currencyFormatter = (val: number) => {
    return '₱' + Number(val || 0).toLocaleString('en-PH', {
        maximumFractionDigits: 0,
    });
};

const numberFormatter = (val: number) => {
    return Number(val || 0).toLocaleString();
};

function formatDate(dateStr: string | null | undefined): string {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function statusBadgeClass(status: string) {
    switch (status?.toLowerCase()) {
        case 'confirmed':
            return 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
        case 'completed':
            return 'bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border-cyan-200 dark:border-cyan-800';
        case 'pending':
            return 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20';
        case 'cancelled':
            return 'bg-destructive/10 text-destructive border-destructive/20';
        default:
            return 'bg-muted text-foreground/70 border-border';
    }
}

function roleBadgeClass(roleName: string) {
    switch (roleName?.toLowerCase()) {
        case 'superadmin':
            return 'bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/30';
        case 'admin':
            return 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/30';
        case 'user':
            return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30';
        default:
            return 'bg-muted text-foreground/70 border-border';
    }
}

/*
|--------------------------------------------------------------------------
| Layout / Breadcrumbs
|--------------------------------------------------------------------------
*/

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: dashboard(),
                title: 'Dashboard',
            },
        ],
    },
});
</script>

<template>
    <Head title="Superadmin Dashboard" />

    <div class="relative min-h-screen overflow-x-clip pb-20 text-foreground">
        <div class="mx-auto max-w-7xl space-y-8 px-5 py-8 lg:px-8">
            <!-- ==========================================================
                 Header & Quick Action Toolbar
            =========================================================== -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium shadow-xs">
                        <Sparkles class="h-3.5 w-3.5 text-cyan-700 dark:text-cyan-300" />
                        <span>System Administration</span>
                    </div>
                    <h1 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                        Admin Overview
                    </h1>
                    <p class="mt-1 text-sm text-foreground/70">
                        Real-time reservation analytics, revenue reporting, and live maritime meteorological health.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <Button variant="outline" as-child class="rounded-xl border-border bg-card shadow-xs hover:bg-cyan-50 dark:hover:bg-cyan-950/40">
                        <Link :href="weatherIndex()" class="gap-1.5">
                            <CloudSun class="h-4 w-4 text-cyan-700 dark:text-cyan-300" />
                            <span>Weather Monitoring</span>
                        </Link>
                    </Button>

                    <Button variant="outline" as-child class="rounded-xl border-border bg-card shadow-xs hover:bg-cyan-50 dark:hover:bg-cyan-950/40">
                        <Link :href="reportsIndex()" class="gap-1.5">
                            <Activity class="h-4 w-4 text-cyan-700 dark:text-cyan-300" />
                            <span>Reports</span>
                        </Link>
                    </Button>

                    <Button variant="outline" as-child class="rounded-xl border-border bg-card shadow-xs hover:bg-cyan-50 dark:hover:bg-cyan-950/40">
                        <Link :href="bookingsIndex()" class="gap-1.5">
                            <Ticket class="h-4 w-4 text-cyan-700 dark:text-cyan-300" />
                            <span>All Bookings</span>
                        </Link>
                    </Button>

                    <Button as-child class="rounded-xl shadow-xs">
                        <Link :href="packagesIndex()" class="gap-1.5">
                            <Compass class="h-4 w-4" />
                            <span>Manage Packages</span>
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- ==========================================================
                 Weather Health Alert Banner
            =========================================================== -->
            <div class="rounded-3xl border border-border bg-card p-6 shadow-sm sm:p-7">
                <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div class="flex items-start gap-4 sm:items-center">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-cyan-700 text-white shadow-sm">
                            <CloudSun class="h-6 w-6" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-bold text-foreground sm:text-lg">
                                    Maritime Weather & Safety Health
                                </h2>
                                <span class="rounded-full border border-cyan-200 dark:border-cyan-800 bg-cyan-50 dark:bg-cyan-950/40 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-cyan-700 dark:text-cyan-300">
                                    Next 30 Days
                                </span>
                            </div>
                            <p class="mt-0.5 text-xs text-foreground/70 sm:text-sm">
                                Automated meteorological monitoring across all scheduled island hopping destinations.
                            </p>
                        </div>
                    </div>

                    <!-- Weather Status Badges -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5 rounded-xl border border-border bg-card px-3 py-1.5 text-xs font-semibold shadow-2xs">
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                            <span class="text-emerald-600 dark:text-emerald-400">{{ stats.weather_advisories.green }} Suitable</span>
                        </div>

                        <div class="flex items-center gap-1.5 rounded-xl border border-border bg-card px-3 py-1.5 text-xs font-semibold shadow-2xs">
                            <span class="h-2 w-2 rounded-full bg-amber-500" />
                            <span class="text-amber-600 dark:text-amber-400">{{ stats.weather_advisories.yellow }} Caution</span>
                        </div>

                        <div class="flex items-center gap-1.5 rounded-xl border border-border bg-card px-3 py-1.5 text-xs font-semibold shadow-2xs">
                            <span class="h-2 w-2 rounded-full bg-orange-500" />
                            <span class="text-orange-600 dark:text-orange-400">{{ stats.weather_advisories.orange }} Unfavorable</span>
                        </div>

                        <div v-if="stats.weather_advisories.red > 0" class="flex items-center gap-1.5 rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-1.5 text-xs font-semibold shadow-2xs">
                            <span class="h-2 w-2 rounded-full bg-destructive animate-ping" />
                            <span class="text-destructive">{{ stats.weather_advisories.red }} Dangerous</span>
                        </div>

                        <Button variant="ghost" size="sm" as-child class="rounded-xl text-xs font-semibold text-cyan-700 dark:text-cyan-300 gap-1">
                            <Link :href="weatherIndex()">
                                <span>Advisories</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- ==========================================================
                 Primary Metric Cards Grid (4 Top Cards)
            =========================================================== -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- 1. Total Revenue -->
                <section class="rounded-3xl border border-border bg-card p-5 shadow-sm transition duration-200 hover:border-[#00FFFF]">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-foreground/65">
                            Verified Revenue
                        </p>
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                            <DollarSign class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold tracking-tight text-foreground">
                        {{ currencyFormatter(stats.total_revenue) }}
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-foreground/70">
                        <span class="font-semibold text-orange-600 dark:text-orange-400">{{ currencyFormatter(stats.pending_revenue) }}</span>
                        <span>pending audit</span>
                    </p>
                </section>

                <!-- 2. Total Bookings -->
                <section class="rounded-3xl border border-border bg-card p-5 shadow-sm transition duration-200 hover:border-[#00FFFF]">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-foreground/65">
                            Total Bookings
                        </p>
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300">
                            <Ticket class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold tracking-tight text-foreground">
                        {{ numberFormatter(stats.total_bookings) }}
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-foreground/70">
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ stats.confirmed_bookings }} confirmed</span>
                        <span>•</span>
                        <span class="font-semibold text-orange-600 dark:text-orange-400">{{ stats.pending_bookings }} pending</span>
                    </p>
                </section>

                <!-- 3. Serviced Guests -->
                <section class="rounded-3xl border border-border bg-card p-5 shadow-sm transition duration-200 hover:border-[#00FFFF]">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-foreground/65">
                            Serviced Guests
                        </p>
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300">
                            <Users class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold tracking-tight text-foreground">
                        {{ numberFormatter(stats.total_guests) }}
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-foreground/70">
                        <span class="font-semibold text-foreground">{{ stats.total_users }}</span>
                        <span>registered accounts</span>
                    </p>
                </section>

                <!-- 4. Active Packages & Tours -->
                <section class="rounded-3xl border border-border bg-card p-5 shadow-sm transition duration-200 hover:border-[#00FFFF]">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wider text-foreground/65">
                            Active Packages & Tours
                        </p>
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300">
                            <Compass class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold tracking-tight text-foreground">
                        {{ stats.total_packages }} Packages
                    </div>
                    <p class="mt-1 flex items-center gap-1.5 text-xs text-foreground/70">
                        <span class="font-semibold text-cyan-700 dark:text-cyan-300">{{ stats.upcoming_tours }}</span>
                        <span>upcoming tour schedules</span>
                    </p>
                </section>
            </div>

            <!-- ==========================================================
                 Primary Visual Analytics (Area Chart & Donut Chart)
            =========================================================== -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Monthly Trends Area Chart (2 Cols) -->
                <Card class="rounded-3xl border border-border bg-card shadow-sm lg:col-span-2">
                    <CardHeader class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/60">
                        <div>
                            <CardTitle class="text-lg font-bold text-foreground flex items-center gap-2">
                                <TrendingUp class="h-5 w-5 text-cyan-700 dark:text-cyan-300" />
                                <span>Booking & Revenue Performance</span>
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Monthly gross revenue and booking volume over the last 6 months
                            </CardDescription>
                        </div>

                        <!-- Metric Toggle -->
                        <div class="flex items-center rounded-xl border border-border bg-card p-1 text-xs font-medium">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1 transition"
                                :class="trendMetric === 'revenue' ? 'bg-cyan-700 text-white shadow-xs font-semibold' : 'text-foreground/70 hover:text-foreground'"
                                @click="trendMetric = 'revenue'"
                            >
                                Revenue
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1 transition"
                                :class="trendMetric === 'bookings' ? 'bg-cyan-700 text-white shadow-xs font-semibold' : 'text-foreground/70 hover:text-foreground'"
                                @click="trendMetric = 'bookings'"
                            >
                                Bookings
                            </button>
                        </div>
                    </CardHeader>

                    <CardContent class="pt-6">
                        <AreaChart
                            :data="monthlyTrends"
                            index="month"
                            :categories="[trendMetric]"
                            :colors="trendMetric === 'revenue' ? ['#10b981'] : ['var(--color-primary, #0ea5e9)']"
                            :value-formatter="trendMetric === 'revenue' ? currencyFormatter : numberFormatter"
                            height="300px"
                        />
                    </CardContent>
                </Card>

                <!-- Booking Status Distribution Donut Chart (1 Col) -->
                <Card class="rounded-3xl border border-border bg-card shadow-sm flex flex-col justify-between">
                    <CardHeader class="pb-4 border-b border-border/60">
                        <CardTitle class="text-lg font-bold text-foreground flex items-center gap-2">
                            <Activity class="h-5 w-5 text-cyan-700 dark:text-cyan-300" />
                            <span>Booking Status</span>
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            Proportional distribution across all customer bookings
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex-1 flex flex-col justify-center items-center py-6">
                        <DonutChart
                            :data="statusDistribution"
                            index="status"
                            category="count"
                            :colors="['#10b981', '#f59e0b', '#0ea5e9', '#ef4444']"
                            :central-label="stats.total_bookings.toLocaleString()"
                            central-sub-label="Total Bookings"
                            :height="190"
                        />
                    </CardContent>
                </Card>
            </div>

            <!-- ==========================================================
                 Secondary Visual Analytics (Popular Packages & Advisory Donut)
            =========================================================== -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Top Tour Packages Bar Chart (2 Cols) -->
                <Card class="rounded-3xl border border-border bg-card shadow-sm lg:col-span-2">
                    <CardHeader class="flex flex-row items-center justify-between pb-4 border-b border-border/60">
                        <div>
                            <CardTitle class="text-lg font-bold text-foreground flex items-center gap-2">
                                <Ship class="h-5 w-5 text-cyan-700 dark:text-cyan-300" />
                                <span>Top Packages by Reservations</span>
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Most popular destinations by passenger volume and booking counts
                            </CardDescription>
                        </div>

                        <Button variant="ghost" size="sm" as-child class="rounded-xl text-xs gap-1">
                            <Link :href="packagesIndex()">
                                <span>All Packages</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </CardHeader>

                    <CardContent class="pt-6">
                        <div v-if="popularPackages.length">
                            <BarChart
                                :data="popularPackages"
                                index="package_name"
                                :categories="['bookings_count', 'guests_count']"
                                :colors="['var(--color-primary, #0ea5e9)', '#10b981']"
                                :value-formatter="numberFormatter"
                                height="280px"
                            />
                        </div>
                        <div v-else class="flex h-64 flex-col items-center justify-center rounded-2xl border border-dashed border-border text-xs text-foreground/70">
                            <Ship class="h-8 w-8 text-foreground/70/60 mb-2" />
                            <p>No package booking data recorded yet.</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Weather Distribution Donut Chart (1 Col) -->
                <Card class="rounded-3xl border border-border bg-card shadow-sm flex flex-col justify-between">
                    <CardHeader class="pb-4 border-b border-border/60">
                        <CardTitle class="text-lg font-bold text-foreground flex items-center gap-2">
                            <CloudSun class="h-5 w-5 text-cyan-700 dark:text-cyan-300" />
                            <span>Advisory Levels</span>
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            Meteorological condition balance across upcoming dates
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="flex-1 flex flex-col justify-center items-center py-6">
                        <DonutChart
                            :data="weatherDistribution"
                            index="level"
                            category="count"
                            :colors="['#10b981', '#eab308', '#f97316', '#ef4444']"
                            :central-label="stats.upcoming_tours.toLocaleString()"
                            central-sub-label="Upcoming Tours"
                            :height="190"
                        />
                    </CardContent>
                </Card>
            </div>

            <!-- ==========================================================
                 1. RECENT BOOKINGS (SimpleTable)
            =========================================================== -->
            <Card class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
                <CardHeader class="flex flex-row items-center justify-between pb-4 border-b border-border p-5 sm:p-6">
                    <div>
                        <CardTitle class="text-lg font-bold text-foreground flex items-center gap-2">
                            <Ticket class="h-5 w-5 text-cyan-700 dark:text-cyan-300" />
                            <span>Recent Bookings</span>
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            Latest customer reservations placed through the platform
                        </CardDescription>
                    </div>

                    <Button variant="outline" size="sm" as-child class="rounded-xl border-border bg-card text-xs gap-1.5 shadow-2xs hover:bg-cyan-50 dark:hover:bg-cyan-950/40">
                        <Link :href="bookingsIndex()">
                            <span>View All Bookings</span>
                            <ArrowUpRight class="h-3.5 w-3.5" />
                        </Link>
                    </Button>
                </CardHeader>

                <CardContent class="p-4 sm:p-6">
                    <SimpleTable
                        :data="recentBookings"
                        :columns="bookingColumns"
                        searchable
                        :per-page="5"
                        mode="client"
                    >
                        <!-- ID Column -->
                        <template v-slot:[`cell-id`]="{ row }">
                            <span class="font-mono text-xs font-bold text-foreground">#BOOK-{{ row.id }}</span>
                        </template>

                        <!-- User / Customer Column -->
                        <template v-slot:[`cell-user_name`]="{ row }">
                            <div class="flex flex-col">
                                <span class="font-semibold text-sm text-foreground">{{ row.user_name }}</span>
                                <span class="text-xs text-foreground/70">{{ row.user_email }}</span>
                            </div>
                        </template>

                        <!-- Package & Destination Column -->
                        <template v-slot:[`cell-package_name`]="{ row }">
                            <div class="flex flex-col">
                                <span class="font-semibold text-xs text-foreground">{{ row.package_name }}</span>
                                <span class="text-[11px] text-foreground/70">{{ row.destination }}</span>
                            </div>
                        </template>

                        <!-- Tour Date Column -->
                        <template v-slot:[`cell-tour_date`]="{ row }">
                            <span class="text-xs font-medium">{{ formatDate(row.tour_date) }}</span>
                        </template>

                        <!-- Number of Guests Column -->
                        <template v-slot:[`cell-number_of_guests`]="{ row }">
                            <span class="font-bold text-sm">{{ row.number_of_guests }}</span>
                        </template>

                        <!-- Total Amount Column -->
                        <template v-slot:[`cell-total_amount`]="{ row }">
                            <span class="font-bold text-sm text-foreground">
                                ₱{{ Number(row.total_amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                            </span>
                        </template>

                        <!-- Booking Status Column -->
                        <template v-slot:[`cell-booking_status`]="{ row }">
                            <Badge
                                variant="outline"
                                :class="[
                                    'capitalize font-semibold text-[11px]',
                                    statusBadgeClass(row.booking_status)
                                ]"
                            >
                                {{ row.booking_status }}
                            </Badge>
                        </template>

                        <!-- Payment Status Column -->
                        <template v-slot:[`cell-payment_status`]="{ row }">
                            <Badge
                                variant="outline"
                                :class="[
                                    'capitalize font-semibold text-[11px]',
                                    row.payment_status === 'paid' || row.payment_status === 'completed' || row.payment_status === 'verified'
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                                        : (row.payment_status === 'partial'
                                            ? 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/30'
                                            : 'bg-muted text-foreground/70 border-border')
                                ]"
                            >
                                {{ row.payment_status }}
                            </Badge>
                        </template>

                        <!-- Action Column -->
                        <template v-slot:[`cell-actions`]="{ row }">
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                class="h-8 w-8 rounded-lg p-0 hover:bg-cyan-50 dark:hover:bg-cyan-950/40"
                                title="View Booking"
                            >
                                <Link :href="bookingShow(row.id)">
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </Button>
                        </template>
                    </SimpleTable>
                </CardContent>
            </Card>

            <!-- ==========================================================
                 2. UPCOMING TOURS (SimpleTable)
            =========================================================== -->
            <Card class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
                <CardHeader class="flex flex-row items-center justify-between pb-4 border-b border-border p-5 sm:p-6">
                    <div>
                        <CardTitle class="text-lg font-bold text-foreground flex items-center gap-2">
                            <Ship class="h-5 w-5 text-cyan-700 dark:text-cyan-300" />
                            <span>Upcoming Tours</span>
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            Scheduled departure dates, occupancy, and marine safety status
                        </CardDescription>
                    </div>

                    <Button variant="outline" size="sm" as-child class="rounded-xl border-border bg-card text-xs gap-1.5 shadow-2xs hover:bg-cyan-50 dark:hover:bg-cyan-950/40">
                        <Link :href="packagesIndex()">
                            <span>All Tour Packages</span>
                            <ArrowUpRight class="h-3.5 w-3.5" />
                        </Link>
                    </Button>
                </CardHeader>

                <CardContent class="p-4 sm:p-6">
                    <SimpleTable
                        :data="upcomingTours"
                        :columns="tourColumns"
                        searchable
                        :per-page="5"
                        mode="client"
                    >
                        <!-- Tour Date Column -->
                        <template v-slot:[`cell-tour_date`]="{ row }">
                            <div class="flex items-center gap-2">
                                <CalendarDays class="h-4 w-4 text-cyan-700 dark:text-cyan-300" />
                                <span class="font-bold text-xs text-foreground">{{ formatDate(row.tour_date) }}</span>
                            </div>
                        </template>

                        <!-- Package & Destination Column -->
                        <template v-slot:[`cell-package_name`]="{ row }">
                            <div class="flex flex-col">
                                <span class="font-semibold text-xs text-foreground">{{ row.package_name }}</span>
                                <span class="text-[11px] text-foreground/70">{{ row.destination }}</span>
                            </div>
                        </template>

                        <!-- Capacity Column -->
                        <template v-slot:[`cell-capacity`]="{ row }">
                            <span class="font-semibold text-xs">{{ row.capacity }}</span>
                        </template>

                        <!-- Booked Guests Column -->
                        <template v-slot:[`cell-booked_guests`]="{ row }">
                            <span class="font-bold text-xs text-emerald-600 dark:text-emerald-400">{{ row.booked_guests }}</span>
                        </template>

                        <!-- Remaining Capacity Column -->
                        <template v-slot:[`cell-remaining_capacity`]="{ row }">
                            <span class="font-bold text-xs text-cyan-700 dark:text-cyan-300">{{ row.remaining_capacity }}</span>
                        </template>

                        <!-- Weather Forecast Column -->
                        <template v-slot:[`cell-weather_forecast`]="{ row }">
                            <WeatherAdvisoryBadge
                                v-if="row.weather_forecast"
                                :advisory-level="row.weather_forecast.advisory_level"
                                :advisory-badge="row.weather_forecast.advisory_badge"
                                size="sm"
                            />
                            <span v-else class="text-[11px] text-foreground/70 italic">No advisory</span>
                        </template>

                        <!-- Action Column -->
                        <template v-slot:[`cell-actions`]="{ row }">
                            <Button
                                variant="ghost"
                                size="sm"
                                as-child
                                class="h-8 w-8 rounded-lg p-0 hover:bg-cyan-50 dark:hover:bg-cyan-950/40"
                                title="View Package"
                            >
                                <Link :href="packagesIndex()">
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </Button>
                        </template>
                    </SimpleTable>
                </CardContent>
            </Card>

            <!-- ==========================================================
                 3. RECENT REGISTRATIONS & 4. SYSTEM ROLES (SimpleTable)
            =========================================================== -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Recent Registrations SimpleTable -->
                <Card class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-4 border-b border-border p-5 sm:p-6">
                        <div>
                            <CardTitle class="text-base font-bold text-foreground flex items-center gap-2">
                                <Users class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                <span>Recent Registrations</span>
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                Latest registered accounts
                            </CardDescription>
                        </div>

                        <Button variant="ghost" size="sm" as-child class="rounded-xl text-xs gap-1">
                            <Link :href="usersIndex()">
                                <span>All Users</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </CardHeader>

                    <CardContent class="p-4 sm:p-6">
                        <SimpleTable
                            :data="recentUsers"
                            :columns="userColumns"
                            searchable
                            :per-page="5"
                            mode="client"
                        >
                            <template v-slot:[`cell-user`]="{ row }">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400 font-bold text-xs uppercase">
                                        {{ row.name.charAt(0) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-foreground text-xs">{{ row.name }}</span>
                                        <span class="text-[10px] text-foreground/70">{{ row.email }}</span>
                                    </div>
                                </div>
                            </template>

                            <template v-slot:[`cell-role_name`]="{ row }">
                                <Badge
                                    variant="outline"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                    :class="roleBadgeClass(row.role_name)"
                                >
                                    {{ row.role_name }}
                                </Badge>
                            </template>

                            <template v-slot:[`cell-is_verified`]="{ row }">
                                <span
                                    v-if="row.is_verified"
                                    class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium text-xs"
                                >
                                    <CheckCircle2 class="h-3 w-3" />
                                    <span>Verified</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium text-xs"
                                >
                                    <Clock3 class="h-3 w-3" />
                                    <span>Pending</span>
                                </span>
                            </template>

                            <template v-slot:[`cell-security`]="{ row }">
                                <div class="flex items-center gap-1">
                                    <Badge
                                        v-if="row.has_2fa"
                                        variant="outline"
                                        class="bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 text-[9px]"
                                    >
                                        2FA
                                    </Badge>
                                    <Badge
                                        v-if="row.is_protected"
                                        variant="outline"
                                        class="bg-purple-500/10 text-purple-700 dark:text-purple-300 border-purple-500/30 text-[9px]"
                                    >
                                        Protected
                                    </Badge>
                                    <span v-if="!row.has_2fa && !row.is_protected" class="text-foreground/70 text-xs">
                                        Standard
                                    </span>
                                </div>
                            </template>

                            <template v-slot:[`cell-created_at`]="{ row }">
                                <span class="text-xs text-foreground/70">
                                    {{ formatDate(row.created_at) }}
                                </span>
                            </template>

                            <template v-slot:[`cell-actions`]="{ row }">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="h-7 w-7 rounded-lg p-0 hover:bg-cyan-50 dark:hover:bg-cyan-950/40"
                                    title="View user accounts"
                                >
                                    <Link :href="usersIndex()">
                                        <Eye class="h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                            </template>
                        </SimpleTable>
                    </CardContent>
                </Card>

                <!-- System Roles SimpleTable -->
                <Card class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between pb-4 border-b border-border p-5 sm:p-6">
                        <div>
                            <CardTitle class="text-base font-bold text-foreground flex items-center gap-2">
                                <Shield class="h-4 w-4 text-cyan-700 dark:text-cyan-300" />
                                <span>System Roles</span>
                            </CardTitle>
                            <CardDescription class="text-xs mt-0.5">
                                RBAC privilege & permissions
                            </CardDescription>
                        </div>

                        <Button variant="ghost" size="sm" as-child class="rounded-xl text-xs gap-1">
                            <Link :href="rolesIndex()">
                                <span>Manage Roles</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </Button>
                    </CardHeader>

                    <CardContent class="p-4 sm:p-6">
                        <SimpleTable
                            :data="roleSummary"
                            :columns="roleColumns"
                            searchable
                            :per-page="5"
                            mode="client"
                        >
                            <template v-slot:[`cell-name`]="{ row }">
                                <div class="flex flex-col">
                                    <span class="font-bold text-foreground text-xs">{{ row.name }}</span>
                                    <span class="text-[10px] text-foreground/70 line-clamp-1">{{ row.description }}</span>
                                </div>
                            </template>

                            <template v-slot:[`cell-users_count`]="{ row }">
                                <Badge
                                    variant="outline"
                                    class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                    :class="roleBadgeClass(row.name)"
                                >
                                    {{ row.users_count }} users
                                </Badge>
                            </template>

                            <template v-slot:[`cell-permissions_count`]="{ row }">
                                <span class="inline-flex items-center gap-1 font-semibold text-cyan-700 dark:text-cyan-300 text-xs">
                                    <KeyRound class="h-3 w-3" />
                                    <span>{{ row.permissions_count }}</span>
                                </span>
                            </template>

                            <template v-slot:[`cell-actions`]="{ row }">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    as-child
                                    class="rounded-lg text-xs font-semibold text-cyan-700 dark:text-cyan-300 hover:bg-cyan-50 dark:hover:bg-cyan-950/40"
                                >
                                    <Link :href="rolesIndex()">
                                        <span>Edit</span>
                                        <ArrowRight class="ml-1 h-3 w-3" />
                                    </Link>
                                </Button>
                            </template>
                        </SimpleTable>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
