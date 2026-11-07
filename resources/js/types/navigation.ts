// types/navigation.ts
import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    badgeKey?: 'pending_bookings' | 'inactive_packages' | 'inactive_pickup_locations' | 'pending_payments' | string;
    badgeVariant?: 'default' | 'warning' | 'destructive' | 'secondary' | 'outline';
    items?: NavSubItem[];
};

export type NavSubItem = {
    title: string;
    href: string;
    icon?: LucideIcon;
    badgeKey?: string;
};
