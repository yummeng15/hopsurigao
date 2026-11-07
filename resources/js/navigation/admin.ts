
import type { NavItem } from '@/types';

import {
    Building2,
    ClipboardList,
    CreditCard,
    FileText,
    LayoutDashboard,
    Package,
    Settings,
    Users,
    WalletCards,
} from '@lucide/vue';

/**
 * HopSurigao - Super Admin Navigation
 *
 * These menu items are for the Superadmin role.
 *
 * Super Admin:
 * - Oversees the platform
 * - Reviews tour operator businesses
 * - Oversees tourist and staff accounts
 * - Monitors packages, bookings and payments
 * - Views financial summaries and reports
 *
 * Tour Operators and Tourists use separate
 * navigation files and role-protected routes.
 *
 * Never add Operator-specific daily operation
 * actions such as Walk-in Booking or QR Scanner
 * to the Super Admin sidebar.
 * Operator Admins register themselves; keep that
 * page out of this sidebar to avoid confusion.
 */
export const adminNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: '/superadmin/dashboard',
        icon: LayoutDashboard,
    },
    {
        title: 'Tour Operators',
        href: '/superadmin/operators',
        icon: Building2,
    },
    {
        title: 'User Accounts',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Package Oversight',
        href: '/admin/packages',
        icon: Package,
    },
    {
        title: 'Booking Oversight',
        href: '/admin/bookings',
        icon: ClipboardList,
    },
    {
        title: 'Payment Oversight',
        href: '/admin/payments',
        icon: CreditCard,
    },
    {
        title: 'Operator Finance',
        href: '/superadmin/operator-finance',
        icon: WalletCards,
    },
    {
        title: 'Platform Reports',
        href: '/admin/reports',
        icon: FileText,
    },
    {
        title: 'Settings',
        href: '/superadmin/settings',
        icon: Settings,
    },
];
