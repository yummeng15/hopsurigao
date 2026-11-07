import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useDocumentVisibility } from '@vueuse/core';
import type { AppNotification } from '@/types/notification';
import { playNotificationSound, playUrgentNotificationSound } from '@/composables/useNotificationSound';

// Global reactive state shared across all components
const unreadCount = ref<number>(0);
const recentNotifications = ref<AppNotification[]>([]);
const isLoading = ref<boolean>(false);
const isFeedLoading = ref<boolean>(false);
const isOpen = ref<boolean>(false);
const hasInitialized = ref<boolean>(false);

let pollingTimer: number | null = null;
const POLL_INTERVAL_MS = 25000; // 25 seconds

/**
 * Fetch unread notification count only.
 */
async function fetchUnreadCount(notifyOnIncrease = true): Promise<void> {
    try {
        const response = await fetch('/notifications/unread-count', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) return;

        const data = (await response.json()) as { unread_count: number };
        const previousCount = unreadCount.value;
        unreadCount.value = data.unread_count ?? 0;

        // If count increased after initial load, play sound
        if (notifyOnIncrease && hasInitialized.value && data.unread_count > previousCount) {
            playNotificationSound();
        }
    } catch {
        // Network or offline, quietly ignore
    }
}

/**
 * Fetch full recent notification feed (for dropdown).
 */
async function fetchFeed(): Promise<void> {
    isFeedLoading.value = true;
    try {
        const response = await fetch('/notifications/feed', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) return;

        const data = (await response.json()) as {
            notifications: AppNotification[];
            unread_count: number;
        };

        recentNotifications.value = data.notifications || [];
        unreadCount.value = data.unread_count ?? 0;
    } catch {
        // Quietly handle network failure
    } finally {
        isFeedLoading.value = false;
    }
}

/**
 * Mark a single notification as read.
 */
async function markAsRead(id: string): Promise<void> {
    // Optimistically update local list
    const item = recentNotifications.value.find((n) => n.id === id);
    if (item && !item.is_read) {
        item.is_read = true;
        item.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/notifications/${id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const data = (await response.json()) as { unread_count: number };
            if (typeof data.unread_count === 'number') {
                unreadCount.value = data.unread_count;
            }
        }
    } catch {
        // Revert on failure if necessary
    }
}

/**
 * Mark all notifications as read.
 */
async function markAllAsRead(): Promise<void> {
    // Optimistic update
    recentNotifications.value.forEach((n) => {
        n.is_read = true;
        n.read_at = new Date().toISOString();
    });
    unreadCount.value = 0;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        await fetch('/notifications/mark-all-read', {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
    } catch {
        // Fetch fresh state on error
        await fetchUnreadCount(false);
    }
}

/**
 * Delete a notification.
 */
async function deleteNotification(id: string): Promise<void> {
    recentNotifications.value = recentNotifications.value.filter((n) => n.id !== id);

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch(`/notifications/${id}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            const data = (await response.json()) as { unread_count: number };
            if (typeof data.unread_count === 'number') {
                unreadCount.value = data.unread_count;
            }
        }
    } catch {
        // Ignore
    }
}

/**
 * Polling Manager
 */
function startPolling(): void {
    if (pollingTimer !== null || typeof window === 'undefined') return;

    pollingTimer = window.setInterval(() => {
        fetchUnreadCount(true);
    }, POLL_INTERVAL_MS);
}

function stopPolling(): void {
    if (pollingTimer !== null && typeof window !== 'undefined') {
        window.clearInterval(pollingTimer);
        pollingTimer = null;
    }
}

export function useNotifications() {
    const visibility = useDocumentVisibility();

    // Pause polling when browser tab is inactive/hidden, resume when active
    watch(
        visibility,
        (currentVisibility) => {
            if (currentVisibility === 'visible') {
                fetchUnreadCount(true);
                startPolling();
            } else {
                stopPolling();
            }
        },
        { immediate: false }
    );

    function init(): void {
        if (!hasInitialized.value) {
            fetchUnreadCount(false).then(() => {
                hasInitialized.value = true;
            });
            startPolling();
        }
    }

    return {
        unreadCount,
        recentNotifications,
        isLoading,
        isFeedLoading,
        isOpen,
        init,
        fetchUnreadCount,
        fetchFeed,
        markAsRead,
        markAllAsRead,
        deleteNotification,
        startPolling,
        stopPolling,
    };
}

