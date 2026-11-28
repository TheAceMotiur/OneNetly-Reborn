<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    ExternalLink,
    LayoutDashboard,
    Package,
    Receipt,
    Settings,
    Users,
    Globe,
    UserRound,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard as userDashboard, home } from '@/routes';
import { analytics, dashboard } from '@/routes/admin';
import { index as adminPlansIndex } from '@/routes/admin/plans';
import { index as adminProfilesIndex } from '@/routes/admin/profiles';
import { index as adminPurchasesIndex } from '@/routes/admin/purchases';
import { edit as adminSettingsEdit } from '@/routes/admin/settings';
import { index as adminUsersIndex } from '@/routes/admin/users';
import type { NavItem } from '@/types';

const adminNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    {
        title: 'Analytics',
        href: analytics(),
        icon: BarChart3,
    },
    {
        title: 'Users',
        href: adminUsersIndex(),
        icon: Users,
    },
    {
        title: 'Bio pages',
        href: adminProfilesIndex(),
        icon: Globe,
    },
    {
        title: 'Plans',
        href: adminPlansIndex(),
        icon: Package,
    },
    {
        title: 'Purchases',
        href: adminPurchasesIndex(),
        icon: Receipt,
    },
    {
        title: 'Settings',
        href: adminSettingsEdit(),
        icon: Settings,
    },
];

const accountNavItems: NavItem[] = [
    {
        title: 'My dashboard',
        href: userDashboard(),
        icon: UserRound,
    },
    {
        title: 'View site',
        href: home(),
        icon: ExternalLink,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <p
                class="mx-2 mt-2 rounded-md bg-indigo-500/10 px-2 py-1 text-center text-[11px] font-semibold tracking-wide text-indigo-600 uppercase group-data-[collapsible=icon]:hidden dark:text-indigo-400"
            >
                Admin console
            </p>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="adminNavItems" label="Administration" />
            <NavMain :items="accountNavItems" label="My account" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
