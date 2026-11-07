<script setup lang="ts">
import { ChevronRight } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    {
        label: 'Platform',
    },
);

const page = usePage();

const pathOnly = (href: string) => href.split('#')[0] ?? href;

const currentPath = () => page.url.split('?')[0]?.split('#')[0] ?? page.url;

const isSubItemActive = (items?: { href: string }[]) =>
    items?.some((sub) => {
        const path = pathOnly(sub.href);
        const hash = sub.href.includes('#') ? `#${sub.href.split('#')[1]}` : '';

        if (currentPath() !== path) {
            return false;
        }

        if (hash !== '') {
            return typeof window !== 'undefined' && window.location.hash === hash;
        }

        return true;
    }) ?? false;

const isParentActive = (item: NavItem) =>
    pathOnly(String(item.href)) === currentPath() || isSubItemActive(item.items);

const getBadgeCount = (item: NavItem): number => {
    if (!item.badgeKey) return 0;
    const counts = page.props.badgeCounts as Record<string, number> | undefined;
    return counts?.[item.badgeKey] ?? 0;
};

const getBadgeClass = (item: NavItem): string => {
    if (item.badgeKey === 'inactive_packages' || item.badgeKey === 'inactive_pickup_locations') {
        return 'rounded-full bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30 font-bold text-[10px] min-w-5 h-5 px-1.5 flex items-center justify-center';
    }
    if (item.badgeKey === 'archived_bookings') {
        return 'rounded-full bg-slate-500/15 text-slate-700 dark:text-slate-300 border border-slate-500/30 font-bold text-[10px] min-w-5 h-5 px-1.5 flex items-center justify-center';
    }
    return 'rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 font-bold text-[10px] min-w-5 h-5 px-1.5 flex items-center justify-center';
};

// Muted highlight — used for the parent when a child (or itself) is active.
// Signals "you're in this section" without claiming to be the exact page.
const parentActiveClasses =
    'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground data-[active=true]:font-normal data-[active=true]:[&>svg]:text-sidebar-accent-foreground';

// Solid accent — used for the exact current page (top-level link or sub-item).
const currentPageClasses =
    'data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground data-[active=true]:font-normal data-[active=true]:hover:bg-sidebar-primary data-[active=true]:hover:text-sidebar-primary-foreground data-[active=true]:[&>svg]:text-sidebar-primary-foreground';
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ label }}</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Item WITH sub-items: collapsible -->
                <Collapsible v-if="item.items?.length" as-child
                    :default-open="item.isActive || isSubItemActive(item.items)" class="group/collapsible">
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :is-active="isParentActive(item)" :tooltip="item.title"
                                :class="parentActiveClasses">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                                    <SidebarMenuSubButton as-child
                                        :is-active="pathOnly(subItem.href) === currentPath() && (!subItem.href.includes('#') || (typeof window !== 'undefined' && window.location.hash === `#${subItem.href.split('#')[1]}`))"
                                        :class="[currentPageClasses, '[&>svg]:size-4 [&>svg]:text-sidebar-foreground hover:[&>svg]:text-sidebar-accent-foreground']">
                                        <Link :href="subItem.href">
                                            <component v-if="subItem.icon" :is="subItem.icon" />
                                            <span>{{ subItem.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <!-- Item WITHOUT sub-items: plain link -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton as-child :is-active="item.href === page.url" :tooltip="item.title"
                        :class="currentPageClasses">
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuBadge v-if="getBadgeCount(item) > 0" :class="getBadgeClass(item)">
                        {{ getBadgeCount(item) }}
                    </SidebarMenuBadge>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>