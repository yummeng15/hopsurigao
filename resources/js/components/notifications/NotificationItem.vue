<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Bell,
    CalendarPlus,
    CalendarCheck,
    CalendarX,
    CreditCard,
    CheckCircle2,
    AlertCircle,
    CloudLightning,
    CloudRain,
    CloudSun,
    Sun,
    Check,
    Trash2,
} from '@lucide/vue';
import type { AppNotification } from '@/types/notification';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        notification: AppNotification;
        compact?: boolean;
    }>(),
    {
        compact: false,
    }
);

const emit = defineEmits<{
    (e: 'mark-read', id: string): void;
    (e: 'delete', id: string): void;
}>();

const iconComponent = computed(() => {
    switch (props.notification.icon) {
        case 'calendar-plus':
            return CalendarPlus;
        case 'calendar-check':
            return CalendarCheck;
        case 'calendar-x':
            return CalendarX;
        case 'credit-card':
            return CreditCard;
        case 'check-circle-2':
            return CheckCircle2;
        case 'alert-circle':
            return AlertCircle;
        case 'cloud-lightning':
            return CloudLightning;
        case 'cloud-rain':
            return CloudRain;
        case 'cloud-sun':
            return CloudSun;
        case 'sun':
            return Sun;
        default:
            return Bell;
    }
});

const priorityStyles = computed(() => {
    switch (props.notification.priority) {
        case 'urgent':
            return {
                badgeVariant: 'destructive' as const,
                badgeLabel: 'URGENT',
                iconBg: 'bg-red-500/15 text-red-600 dark:bg-red-500/25 dark:text-red-400',
                border: 'border-red-500/30 dark:border-red-500/40',
                unreadGlow: 'bg-red-500',
            };
        case 'high':
            return {
                badgeVariant: 'outline' as const,
                badgeLabel: 'HIGH',
                badgeClass: 'border-amber-500/40 text-amber-600 dark:text-amber-400 bg-amber-500/10',
                iconBg: 'bg-amber-500/15 text-amber-600 dark:bg-amber-500/25 dark:text-amber-400',
                border: 'border-amber-500/30 dark:border-amber-500/40',
                unreadGlow: 'bg-amber-500',
            };
        case 'low':
            return {
                badgeVariant: 'secondary' as const,
                badgeLabel: 'LOW',
                iconBg: 'bg-muted text-muted-foreground',
                border: 'border-border/60',
                unreadGlow: 'bg-muted-foreground',
            };
        default:
            return {
                badgeVariant: 'secondary' as const,
                badgeLabel: '',
                iconBg: 'bg-primary/10 text-primary dark:bg-primary/20 dark:text-primary',
                border: 'border-border/60',
                unreadGlow: 'bg-primary',
            };
    }
});

function handleClick(): void {
    if (!props.notification.is_read) {
        emit('mark-read', props.notification.id);
    }
    if (props.notification.action_url && props.notification.action_url !== '#') {
        router.visit(props.notification.action_url);
    }
}
</script>

<template>
    <div
        :class="[
            'group relative flex items-start gap-3 transition-all duration-200 cursor-pointer select-none',
            compact
                ? 'p-3 rounded-xl hover:bg-muted/60'
                : 'p-4 rounded-2xl border shadow-xs hover:shadow-md hover:border-primary/40',
            !notification.is_read
                ? compact
                    ? 'bg-primary/5 dark:bg-primary/10'
                    : 'bg-card border-l-4 border-l-primary ' + priorityStyles.border
                : compact
                    ? ''
                    : 'bg-card/60 opacity-85 hover:opacity-100 ' + priorityStyles.border,
        ]"
        @click="handleClick"
    >
        <!-- Icon Avatar -->
        <div
            :class="[
                'flex shrink-0 items-center justify-center rounded-xl transition-transform group-hover:scale-105',
                compact ? 'size-9' : 'size-10',
                priorityStyles.iconBg,
            ]"
        >
            <component :is="iconComponent" :class="compact ? 'size-4.5' : 'size-5'" />
        </div>

        <!-- Content Area -->
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span
                        :class="[
                            'font-semibold leading-tight line-clamp-1',
                            compact ? 'text-xs text-foreground' : 'text-sm text-foreground',
                            !notification.is_read ? 'font-bold' : '',
                        ]"
                    >
                        {{ notification.title }}
                    </span>

                    <Badge
                        v-if="priorityStyles.badgeLabel"
                        :variant="priorityStyles.badgeVariant"
                        :class="[
                            'text-[10px] font-bold px-1.5 py-0 uppercase tracking-wide',
                            priorityStyles.badgeClass || '',
                        ]"
                    >
                        {{ priorityStyles.badgeLabel }}
                    </Badge>
                </div>

                <span class="text-[11px] shrink-0 text-muted-foreground whitespace-nowrap">
                    {{ notification.time_ago }}
                </span>
            </div>

            <p
                :class="[
                    'text-muted-foreground leading-relaxed',
                    compact ? 'text-[11px] line-clamp-2' : 'text-xs line-clamp-3',
                ]"
            >
                {{ notification.message }}
            </p>

            <div
                v-if="!compact && notification.action_text"
                class="pt-1.5 flex items-center gap-2 text-xs font-semibold text-primary hover:underline"
            >
                <span>{{ notification.action_text }} →</span>
            </div>
        </div>

        <!-- Unread Indicator & Quick Actions -->
        <div class="flex shrink-0 items-center gap-1 self-center" @click.stop>
            <!-- Unread Blue Dot -->
            <span
                v-if="!notification.is_read"
                :class="['size-2 rounded-full ring-2 ring-background', priorityStyles.unreadGlow]"
                title="Unread"
            />

            <!-- Mark as Read Icon Button -->
            <Button
                v-if="!notification.is_read"
                type="button"
                variant="ghost"
                size="icon"
                class="size-7 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity hover:bg-primary/10 hover:text-primary"
                title="Mark as read"
                @click.stop="emit('mark-read', notification.id)"
            >
                <Check class="size-3.5" />
            </Button>

            <!-- Delete Icon Button -->
            <Button
                v-if="!compact"
                type="button"
                variant="ghost"
                size="icon"
                class="size-7 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                title="Delete notification"
                @click.stop="emit('delete', notification.id)"
            >
                <Trash2 class="size-3.5" />
            </Button>
        </div>
    </div>
</template>

