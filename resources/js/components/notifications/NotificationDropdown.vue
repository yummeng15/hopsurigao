<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    BellOff,
    CheckCheck,
    Volume2,
    VolumeX,
    ExternalLink,
    Loader2,
} from '@lucide/vue';
import { useNotifications } from '@/composables/useNotifications';
import { useNotificationSound } from '@/composables/useNotificationSound';
import NotificationItem from '@/components/notifications/NotificationItem.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';

const {
    unreadCount,
    recentNotifications,
    isFeedLoading,
    markAsRead,
    markAllAsRead,
} = useNotifications();

const { isSoundEnabled, toggleSound } = useNotificationSound();
</script>

<template>
    <div class="flex flex-col max-h-[85vh] w-[360px] sm:w-[400px] overflow-hidden rounded-2xl border bg-card/95 backdrop-blur-md shadow-2xl">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-border/60 bg-muted/20">
            <div class="flex items-center gap-2">
                <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <Bell class="size-4" />
                </div>
                <h3 class="text-sm font-bold text-foreground">Notifications</h3>
                <Badge
                    v-if="unreadCount > 0"
                    variant="secondary"
                    class="rounded-full bg-primary/15 text-primary text-[10px] font-bold px-2 py-0.5"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }} new
                </Badge>
            </div>

            <!-- Controls: Sound Toggle & Mark All As Read -->
            <div class="flex items-center gap-1">
                <!-- Sound Toggle -->
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    :class="[
                        'size-7.5 rounded-lg transition-colors',
                        isSoundEnabled ? 'text-primary hover:bg-primary/10' : 'text-muted-foreground hover:bg-muted',
                    ]"
                    :title="isSoundEnabled ? 'Notification sounds enabled (Click to mute)' : 'Notification sounds muted (Click to enable)'"
                    @click.stop="toggleSound"
                >
                    <component :is="isSoundEnabled ? Volume2 : VolumeX" class="size-3.5" />
                    <span class="sr-only">Toggle notification sound</span>
                </Button>

                <!-- Mark All As Read -->
                <Button
                    v-if="unreadCount > 0"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-7.5 rounded-lg text-muted-foreground hover:bg-primary/10 hover:text-primary transition-colors"
                    title="Mark all as read"
                    @click.stop="markAllAsRead"
                >
                    <CheckCheck class="size-3.5" />
                    <span class="sr-only">Mark all as read</span>
                </Button>
            </div>
        </div>

        <!-- Scrollable Notification List -->
        <div class="flex-1 overflow-y-auto divide-y divide-border/40 p-1.5 max-h-[380px] scrollbar-thin">
            <!-- Loading State -->
            <div v-if="isFeedLoading" class="flex flex-col items-center justify-center py-10 text-muted-foreground space-y-2">
                <Loader2 class="size-6 animate-spin text-primary" />
                <span class="text-xs">Loading notifications...</span>
            </div>

            <!-- Notifications Feed -->
            <template v-else-if="recentNotifications.length > 0">
                <NotificationItem
                    v-for="notification in recentNotifications"
                    :key="notification.id"
                    :notification="notification"
                    compact
                    @mark-read="markAsRead"
                />
            </template>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-12 px-4 text-center space-y-2.5">
                <div class="flex size-11 items-center justify-center rounded-2xl bg-muted/60 text-muted-foreground">
                    <BellOff class="size-5.5" />
                </div>
                <div class="space-y-0.5">
                    <p class="text-xs font-semibold text-foreground">No new notifications</p>
                    <p class="text-[11px] text-muted-foreground">You're all caught up! Important system and booking updates will appear here.</p>
                </div>
            </div>
        </div>

        <Separator class="bg-border/60" />

        <!-- Footer: View All Link -->
        <div class="p-2 bg-muted/10">
            <Link
                href="/notifications"
                class="flex w-full items-center justify-center gap-1.5 rounded-xl py-2 text-xs font-semibold text-primary transition-colors hover:bg-primary/10"
            >
                <span>View all notifications</span>
                <ExternalLink class="size-3" />
            </Link>
        </div>
    </div>
</template>

