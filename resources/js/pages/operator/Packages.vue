
<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { CalendarDays, ChevronLeft, ChevronRight, Package as PackageIcon, Pencil, Plus, Ship, Wrench, Globe, GlobeLock } from '@lucide/vue';

interface TourPackage {
    id: number;
    package_name: string;
    destination: string;
    boat_name: string | null;
    price: string;
    status: string;
    under_maintenance: boolean;
    tour_dates_count: number;
}

interface PaginatedPackages {
    data: TourPackage[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

defineProps<{
    operatorName: string;
    packages: PaginatedPackages;
}>();

function formatPrice(value: string): string {
    const amount = Number(value);
    return Number.isFinite(amount)
        ? new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount)
        : value;
}

const publicationForm = useForm({ publication: '' });
const selectedPackageId = ref<number | null>(null);

function changePublication(tour: TourPackage): void {
    if (publicationForm.processing) return;

    const published = tour.status === 'active';
    if (published && !window.confirm(
        'Unpublish this package? New bookings will stop, but existing reservations remain.'
    )) {
        return;
    }

    selectedPackageId.value = tour.id;
    publicationForm.clearErrors();
    publicationForm.post(
        `/operator/packages/${tour.id}/${published ? 'unpublish' : 'publish'}`,
        { preserveScroll: true }
    );
}
</script>

<template>
    <Head title="My Tour Packages" />

    <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 text-foreground sm:px-6 lg:px-8">
        <header class="space-y-2">
            <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                <Ship class="size-5" />
                {{ operatorName }}
            </div>
            <h1 class="text-3xl font-bold tracking-tight text-foreground">My Tour Packages</h1>
            <p class="text-sm text-foreground/70">
                Publish packages after configuring future tour dates, capacity and pickup times. Unpublishing blocks new reservations without cancelling existing bookings.
            </p>
        </header>

        <section class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-foreground">Packages ({{ packages.total }})</h2>
                <Link
                    href="/operator/packages/create"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground hover:opacity-90"
                >
                    <Plus class="size-4" /> Create package
                </Link>
            </div>

            <div
                v-if="packages.data.length === 0"
                class="rounded-3xl border border-dashed border-border bg-card px-5 py-14 text-center shadow-sm"
            >
                <PackageIcon class="mx-auto mb-3 size-9 text-foreground/50" />
                <p class="font-semibold text-foreground">No packages assigned yet</p>
                <p class="mt-1 text-sm text-foreground/70">
                    Contact the Super Admin to confirm this operator's package assignments.
                </p>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="tour in packages.data"
                    :key="tour.id"
                    class="rounded-3xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-lg font-semibold text-foreground">{{ tour.package_name }}</h3>
                        <span
                            class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                            :class="tour.status === 'active'
                                ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-700 dark:text-emerald-300'
                                : 'border border-border bg-muted text-foreground'"
                        >
                            {{ tour.status }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-foreground/70">
                        {{ tour.destination }}
                    </p>
                    <p v-if="tour.boat_name" class="mt-3 flex items-center gap-2 text-sm text-foreground">
                        <Ship class="size-4 text-primary" /> {{ tour.boat_name }}
                    </p>
                    <p class="mt-2 flex items-center gap-2 text-sm text-foreground">
                        <CalendarDays class="size-4 text-primary" />
                        {{ tour.tour_dates_count }} tour date(s)
                    </p>
                    <p
                        v-if="tour.under_maintenance"
                        class="mt-2 flex items-center gap-2 text-sm font-medium text-amber-700 dark:text-amber-300"
                    >
                        <Wrench class="size-4" /> Under maintenance
                    </p>
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                        <span class="text-lg font-bold text-foreground">{{ formatPrice(tour.price) }}</span>
                        <div class="flex flex-wrap gap-2">
                            <Link
                                :href="`/operator/packages/${tour.id}/schedules`"
                                class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-foreground hover:bg-muted"
                            >
                                <CalendarDays class="size-3.5" /> Schedules
                            </Link>
                            <Link
                                :href="`/operator/packages/${tour.id}/edit`"
                                class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-xs font-semibold text-foreground hover:bg-muted"
                            >
                                <Pencil class="size-3.5" /> Edit details
                            </Link>
                        </div>
                    </div>
                    <div class="mt-3 border-t border-border pt-3">
                        <button
                            type="button"
                            :disabled="publicationForm.processing"
                            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold disabled:opacity-50"
                            :class="tour.status === 'active'
                                ? 'border border-border bg-muted text-foreground hover:bg-muted/80'
                                : 'bg-primary text-primary-foreground hover:opacity-90'"
                            @click="changePublication(tour)"
                        >
                            <GlobeLock v-if="tour.status === 'active'" class="size-4" />
                            <Globe v-else class="size-4" />
                            {{ tour.status === 'active' ? 'Unpublish' : 'Publish package' }}
                        </button>
                        <p
                            v-if="selectedPackageId === tour.id && publicationForm.errors.publication"
                            role="alert"
                            class="mt-2 text-sm text-destructive"
                        >
                            {{ publicationForm.errors.publication }}
                        </p>
                    </div>
                </article>
            </div>

            <nav
                v-if="packages.last_page > 1"
                aria-label="Package pages"
                class="flex items-center justify-between border-t border-border pt-4"
            >
                <span class="text-sm text-foreground/70">
                    Page {{ packages.current_page }} of {{ packages.last_page }}
                </span>
                <div class="flex gap-2">
                    <Link
                        v-if="packages.prev_page_url"
                        :href="packages.prev_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground hover:bg-muted"
                    >
                        <ChevronLeft class="size-4" /> Previous
                    </Link>
                    <Link
                        v-if="packages.next_page_url"
                        :href="packages.next_page_url"
                        preserve-scroll
                        class="inline-flex items-center gap-1 rounded-lg border border-border bg-card px-3 py-2 text-sm text-foreground hover:bg-muted"
                    >
                        Next <ChevronRight class="size-4" />
                    </Link>
                </div>
            </nav>
        </section>
    </main>
</template>
