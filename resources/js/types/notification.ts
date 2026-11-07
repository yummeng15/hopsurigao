export type NotificationPriority = 'low' | 'normal' | 'high' | 'urgent';

export interface AppNotification {
    id: string;
    type: string;
    title: string;
    message: string;
    action_url: string;
    action_text?: string;
    related_entity?: string | null;
    related_id?: number | string | null;
    priority: NotificationPriority;
    icon?: string;
    read_at?: string | null;
    is_read: boolean;
    created_at: string;
    time_ago: string;
    metadata?: Record<string, any>;
}

export interface NotificationCounts {
    total: number;
    unread: number;
    urgent: number;
}

export interface NotificationFilters {
    filter?: 'all' | 'unread' | 'read';
    priority?: NotificationPriority | '';
    search?: string;
}

