<script setup lang="ts">
import { onMounted } from 'vue';
import { Bell } from '@lucide/vue';
import { useNotifications } from '@/composables/useNotifications';
import NotificationDropdown from '@/components/notifications/NotificationDropdown.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const { unreadCount, init, fetchFeed } = useNotifications();

onMounted(() => {
    init();
});

function handleOpenChange(open: boolean): void {
    if (open) {
        fetchFeed();
    }
}
</script>

<template>
    <DropdownMenu @update:open="handleOpenChange">
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="relative size-9 rounded-xl border border-border/50 bg-background/50 backdrop-blur shadow-2xs transition hover:bg-muted/60 hover:ring-2 hover:ring-primary/20 focus-visible:ring-2 focus-visible:ring-primary/40"
                title="Notifications"
            >
                <Bell class="size-4.5 text-foreground transition-transform hover:scale-110" />

                <!-- Unread Badge Indicator with Ping Animation -->
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-1 -right-1 flex size-4.5 items-center justify-center"
                >
                    <span class="absolute inline-flex size-full animate-ping rounded-full bg-red-400 opacity-75" />
                    <span
                        class="relative inline-flex size-4.5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white shadow-xs"
                    >
                        {{ unreadCount > 99 ? '99+' : unreadCount }}
                    </span>
                </span>
                <span class="sr-only">Notifications ({{ unreadCount }} unread)</span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            :side-offset="8"
            class="p-0 border-0 bg-transparent shadow-none"
        >
            <NotificationDropdown />
        </DropdownMenuContent>
    </DropdownMenu>
</template>

