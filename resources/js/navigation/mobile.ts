
import type { NavItem } from '@/types';

import {
    ClipboardList,
    CreditCard,
    History,
    LayoutGrid,
    MessageSquare,
    MoreHorizontal,
    Plus,
    QrCode,
    Sailboat,
    Users,
} from '@lucide/vue';

import { adminNavItems } from './admin';
import { operatorNavItems } from './operator';

export type MobileTabItem = NavItem & {
    tabTitle: string;
    matchPrefix?: string;
};

const moreTab: MobileTabItem = {
    title: 'More',
    tabTitle: 'More',
    href: '#more',
    icon: MoreHorizontal,
};

export const operatorMobileTabs: MobileTabItem[] = [
    {
        title: 'Dashboard',
        tabTitle: 'Home',
        href: '/operator/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'QR Scanner',
        tabTitle: 'Scan',
        href: '/operator/qr-scanner',
        icon: QrCode,
    },
    {
        title: 'Bookings',
        tabTitle: 'Bookings',
        href: '/operator/bookings',
        icon: ClipboardList,
        matchPrefix: '/operator/bookings',
    },
    {
        title: 'Walk-in',
        tabTitle: 'Walk-in',
        href: '/operator/walk-ins/create',
        icon: Plus,
    },
    moreTab,
];

const operatorPrimaryHrefs = new Set(
    operatorMobileTabs.map((item) => String(item.href)),
);

export const operatorMobileMoreItems: NavItem[] =
    operatorNavItems.filter(
        (item) => !operatorPrimaryHrefs.has(String(item.href)),
    );

// TOURIST MOBILE — UNCHANGED
export const touristMobileTabs: MobileTabItem[] = [
    { title: 'Dashboard', tabTitle: 'Home', href: '/user/dashboard', icon: LayoutGrid },
    { title: 'Island Hopping', tabTitle: 'Tours', href: '/user/packages', icon: Sailboat, matchPrefix: '/user/packages' },
    { title: 'My Bookings', tabTitle: 'Bookings', href: '/user/bookings', icon: ClipboardList, matchPrefix: '/user/bookings' },
    { title: 'Feedback', tabTitle: 'Feedback', href: '/user/feedback', icon: MessageSquare, matchPrefix: '/user/feedback' },
    { title: 'History', tabTitle: 'History', href: '/user/history', icon: History },
];

export const adminMobileTabs: MobileTabItem[] = [
    {
        title: 'Dashboard',
        tabTitle: 'Home',
        href: '/superadmin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'User Accounts',
        tabTitle: 'Users',
        href: '/admin/users',
        icon: Users,
    },
    {
        title: 'Booking Oversight',
        tabTitle: 'Bookings',
        href: '/admin/bookings',
        icon: ClipboardList,
    },
    {
        title: 'Payment Oversight',
        tabTitle: 'Payments',
        href: '/admin/payments',
        icon: CreditCard,
    },
    moreTab,
];

const adminPrimaryHrefs = new Set(
    adminMobileTabs.map((item) => String(item.href)),
);

export const adminMobileMoreItems: NavItem[] =
    adminNavItems.filter(
        (item) => !adminPrimaryHrefs.has(String(item.href)),
    );

export function isMobileTabActive(
    tab: MobileTabItem,
    url: string,
): boolean {
    if (tab.href === '#more') {
        return false;
    }

    const href = String(tab.href);

    if (href === '/admin/bookings/create') {
        return url.startsWith('/admin/bookings/create');
    }

    if (href === '/admin/bookings') {
        return (
            (url === href || url.startsWith(`${href}/`)) &&
            !url.startsWith('/admin/bookings/create') &&
            !url.startsWith('/admin/bookings/calendar')
        );
    }

    if (tab.matchPrefix) {
        return (
            url === tab.matchPrefix ||
            url.startsWith(`${tab.matchPrefix}/`)
        );
    }

    return url === href || url.startsWith(`${href}/`);
}

export function mobileTabsForRole(
    role: string | undefined,
    _url = '',
): MobileTabItem[] {
    if (role === 'Superadmin') return adminMobileTabs;
    if (role === 'Admin') return operatorMobileTabs;
    if (role === 'User') return touristMobileTabs;

    return [];
}

export function mobileMoreItemsForRole(
    role: string | undefined,
    _url = '',
): NavItem[] {
    if (role === 'Superadmin') return adminMobileMoreItems;
    if (role === 'Admin') return operatorMobileMoreItems;

    return [];
}
