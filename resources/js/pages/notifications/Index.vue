<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    Bell,
    CheckCheck,
    Trash2,
    Volume2,
    VolumeX,
    Search,
    Filter,
    ShieldAlert,
    Inbox,
    ChevronLeft,
    ChevronRight,
} from '@lucide/vue';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useNotificationSound } from '@/composables/useNotificationSound';
import { useNotifications } from '@/composables/useNotifications';
import type { AppNotification, NotificationCounts, NotificationFilters } from '@/types/notification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                href: '/notifications',
                title: 'Notifications',
            },
        ],
    },
});

type PaginatedNotifications = {
    data: AppNotification[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

const props = defineProps<{
    notifications: PaginatedNotifications;
    filters: NotificationFilters;
    counts: NotificationCounts;
}>();

const { isSoundEnabled, toggleSound } = useNotificationSound();
const { unreadCount } = useNotifications();

const currentFilter = ref<'all' | 'unread' | 'read'>(props.filters.filter || 'all');
const currentPriority = ref<string>(props.filters.priority || 'all_priorities');
const search = ref<string>(props.filters.search || '');

let searchDebounce: number | null = null;

function applyFilters(): void {
    router.get(
        '/notifications',
        {
            filter: currentFilter.value,
            priority: currentPriority.value !== 'all_priorities' ? currentPriority.value : undefined,
            search: search.value.trim() || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function handleSearch(): void {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = window.setTimeout(() => {
        applyFilters();
    }, 300);
}

function setFilterTab(tab: 'all' | 'unread' | 'read'): void {
    currentFilter.value = tab;
    applyFilters();
}

function handlePriorityChange(val: string): void {
    currentPriority.value = val;
    applyFilters();
}

async function markSingleRead(id: string): Promise<void> {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        await fetch(`/notifications/${id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        router.reload();
    } catch {
        // Ignore
    }
}

async function markAllRead(): Promise<void> {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        await fetch('/notifications/mark-all-read', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        unreadCount.value = 0;
        router.reload();
    } catch {
        // Ignore
    }
}

async function deleteSingle(id: string): Promise<void> {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        await fetch(`/notifications/${id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        router.reload();
    } catch {
        // Ignore
    }
}

async function clearAllRead(): Promise<void> {
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        await fetch('/notifications/clear-all', {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        router.reload();
    } catch {
        // Ignore
    }
}
</script>

<template>
    <Head title="Notifications & Alerts" />

    <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <!-- Header Card -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-foreground flex items-center gap-2.5">
                        <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary dark:bg-primary/20">
                            <Bell class="size-5" />
                        </div>
                        <span>Notifications & Alerts</span>
                    </h1>
                    <p class="text-xs text-muted-foreground mt-1">
                        Track reservations, payments, maritime weather advisories, and system notices.
                    </p>
                </div>

                <!-- Action Controls -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Audio Toggle -->
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="rounded-xl border-border/60 text-xs font-medium"
                        @click="toggleSound"
                    >
                        <component :is="isSoundEnabled ? Volume2 : VolumeX" class="mr-1.5 size-3.5" :class="isSoundEnabled ? 'text-primary' : 'text-muted-foreground'" />
                        <span>{{ isSoundEnabled ? 'Sounds On' : 'Muted' }}</span>
                    </Button>

                    <!-- Mark All Read -->
                    <Button
                        v-if="counts.unread > 0"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="rounded-xl border-border/60 text-xs font-medium text-primary hover:bg-primary/10"
                        @click="markAllRead"
                    >
                        <CheckCheck class="mr-1.5 size-3.5" />
                        <span>Mark all as read</span>
                    </Button>

                    <!-- Clear Read -->
                    <Button
                        v-if="counts.total - counts.unread > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="rounded-xl text-xs font-medium text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                        @click="clearAllRead"
                    >
                        <Trash2 class="mr-1.5 size-3.5" />
                        <span>Clear read</span>
                    </Button>
                </div>
            </div>

            <!-- Overview KPI Metric Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card class="rounded-2xl border bg-card/70 backdrop-blur-md shadow-xs">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Total Notices</CardTitle>
                        <Inbox class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-foreground">{{ counts.total }}</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Total notifications in record</p>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border bg-card/70 backdrop-blur-md shadow-xs">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Unread Alerts</CardTitle>
                        <Bell class="size-4 text-primary" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-primary">{{ counts.unread }}</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">Awaiting your attention</p>
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border bg-card/70 backdrop-blur-md shadow-xs">
                    <CardHeader class="flex flex-row items-center justify-between pb-2">
                        <CardTitle class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Urgent Alerts</CardTitle>
                        <ShieldAlert class="size-4 text-red-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-black text-red-600 dark:text-red-400">{{ counts.urgent }}</div>
                        <p class="text-[11px] text-muted-foreground mt-0.5">High priority weather & system alerts</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Filters Bar -->
            <Card class="rounded-2xl border bg-card/80 backdrop-blur-md shadow-xs">
                <CardContent class="p-3.5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <!-- Status Tabs -->
                    <div class="flex items-center gap-1 bg-muted/50 p-1 rounded-xl w-fit">
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                                currentFilter === 'all'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="setFilterTab('all')"
                        >
                            All ({{ counts.total }})
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200 flex items-center gap-1.5',
                                currentFilter === 'unread'
                                    ? 'bg-background text-primary shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="setFilterTab('unread')"
                        >
                            <span>Unread</span>
                            <Badge v-if="counts.unread > 0" variant="secondary" class="text-[10px] px-1 py-0 bg-primary/15 text-primary font-bold">
                                {{ counts.unread }}
                            </Badge>
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition-all duration-200',
                                currentFilter === 'read'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="setFilterTab('read')"
                        >
                            Read ({{ Math.max(0, counts.total - counts.unread) }})
                        </button>
                    </div>

                    <!-- Right Filters: Priority Dropdown & Search -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Priority Select -->
                        <Select :model-value="currentPriority" @update:model-value="(val) => handlePriorityChange(String(val ?? 'all_priorities'))">
                            <SelectTrigger class="h-9 w-[150px] rounded-xl border-border/60 text-xs">
                                <Filter class="mr-1.5 size-3.5 text-muted-foreground" />
                                <SelectValue placeholder="Priority" />
                            </SelectTrigger>
                            <SelectContent class="rounded-xl">
                                <SelectItem value="all_priorities">All Priorities</SelectItem>
                                <SelectItem value="urgent">Urgent</SelectItem>
                                <SelectItem value="high">High</SelectItem>
                                <SelectItem value="normal">Normal</SelectItem>
                                <SelectItem value="low">Low</SelectItem>
                            </SelectContent>
                        </Select>

                        <!-- Search Input -->
                        <div class="relative w-full sm:w-[220px]">
                            <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                            <Input
                                v-model="search"
                                type="text"
                                placeholder="Search notifications..."
                                class="h-9 pl-8 rounded-xl border-border/60 text-xs"
                                @input="handleSearch"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Notifications List -->
            <div class="space-y-3">
                <template v-if="notifications.data.length > 0">
                    <NotificationItem
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        :notification="notification"
                        @mark-read="markSingleRead"
                        @delete="deleteSingle"
                    />
                </template>

                <!-- Empty State -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-border/80 bg-card/40 p-12 text-center"
                >
                    <div class="flex size-14 items-center justify-center rounded-2xl bg-muted/60 text-muted-foreground mb-3">
                        <Inbox class="size-7" />
                    </div>
                    <h3 class="text-base font-bold text-foreground">No notifications found</h3>
                    <p class="text-xs text-muted-foreground max-w-sm mt-1">
                        {{
                            search || currentPriority !== 'all_priorities' || currentFilter !== 'all'
                                ? 'No notifications match your active search or filter criteria. Try clearing filters.'
                                : 'You have no notifications yet. System and reservation updates will be delivered here.'
                        }}
                    </p>
                </div>
            </div>

            <!-- Pagination -->
            <div
                v-if="notifications.total > notifications.per_page"
                class="flex items-center justify-between border-t border-border/60 pt-4 px-2"
            >
                <div class="text-xs text-muted-foreground">
                    Showing <span class="font-bold text-foreground">{{ notifications.from }}</span> to
                    <span class="font-bold text-foreground">{{ notifications.to }}</span> of
                    <span class="font-bold text-foreground">{{ notifications.total }}</span> notifications
                </div>

                <div class="flex items-center gap-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!notifications.prev_page_url"
                        class="h-8 rounded-xl border-border/60 text-xs"
                        @click="notifications.prev_page_url && router.visit(notifications.prev_page_url)"
                    >
                        <ChevronLeft class="mr-1 size-3.5" />
                        Previous
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="!notifications.next_page_url"
                        class="h-8 rounded-xl border-border/60 text-xs"
                        @click="notifications.next_page_url && router.visit(notifications.next_page_url)"
                    >
                        Next
                        <ChevronRight class="ml-1 size-3.5" />
                    </Button>
                </div>
            </div>
        </div>
</template>
