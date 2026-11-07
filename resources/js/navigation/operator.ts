import type { NavItem } from '@/types';

import {
    CalendarDays,
    ClipboardList,
    CloudSun,
    CreditCard,
    Building2,
    Download,
    LayoutDashboard,
    MapPin,
    MessageSquare,
    Package,
    Plus,
    QrCode,
    RotateCcw,
    Settings,
    Ship,
    Wrench,
} from '@lucide/vue';

export const operatorNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/operator/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'My Business',
        href: '/operator/business',
        icon: Building2,
    },
    {
        title: 'My Tour Packages',
        href: '/operator/packages',
        icon: Package,
    },
    {
        title: 'Bookings',
        href: '/operator/bookings',
        icon: ClipboardList,
        badgeKey: 'pending_bookings',
    },
    {
        title: 'Booking Calendar',
        href: '/operator/bookings/calendar',
        icon: CalendarDays,
    },
    {
        title: 'Walk-in Booking',
        href: '/operator/walk-ins/create',
        icon: Plus,
    },
    {
        title: 'QR Scanner',
        href: '/operator/qr-scanner',
        icon: QrCode,
    },
    {
        title: 'Payments',
        href: '/operator/payments',
        icon: CreditCard,
        badgeKey: 'pending_payments',
    },
    {
        title: 'Refund Management',
        href: '/operator/refunds',
        icon: RotateCcw,
    },
    {
        title: 'Operations',
        href: '/operator/operations',
        icon: Ship,
        items: [
            {
                title: 'Boats',
                href: '/operator/operations/boats',
                icon: Wrench,
            },
            {
                title: 'Pickup Locations',
                href: '/operator/operations/pickups',
                icon: MapPin,
            },
            {
                title: 'Weather',
                href: '/operator/operations/weather',
                icon: CloudSun,
            },
            {
                title: 'Feedback',
                href: '/operator/operations/feedback',
                icon: MessageSquare,
            },
            {
                title: 'Reports',
                href: '/operator/operations/reports',
                icon: Download,
            },
        ],
    },
    {
        title: 'Settings',
        href: '/operator/settings',
        icon: Settings,
    },
];
