import type { NavItem } from '@/types';
import {
    LayoutGrid,
    Sailboat,
    ClipboardList,
    History,
    MessageSquare,
    Settings,
} from '@lucide/vue';

export const touristNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/user/dashboard',
        icon: LayoutGrid,
    },

    {
        title: 'Island Hopping',
        href: '/user/packages',
        icon: Sailboat,
    },

    {
        title: 'My Bookings',
        href: '/user/bookings',
        icon: ClipboardList,
        badgeKey: 'pending_bookings',
    },

    {
        title: 'History',
        href: '/user/history',
        icon: History,
    },

    {
        title: 'Feedback',
        href: '/user/feedback',
        icon: MessageSquare,
    },

    {
        title: 'Settings',
        href: '/user/settings',
        icon: Settings,
    },
];